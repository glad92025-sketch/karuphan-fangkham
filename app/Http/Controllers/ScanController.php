<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetMovement;
use App\Models\StockCheck;
use App\Models\StockCheckItem;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(): View
    {
        $openStockChecks = StockCheck::where('status', 'open')->latest('id')->get();
        $recentScans = AssetInspection::with(['asset', 'inspector'])
            ->latest('inspected_at')
            ->limit(10)
            ->get();

        return view('scan.index', compact('openStockChecks', 'recentScans'));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $raw = trim($request->string('code')->toString());
        if ($raw === '') {
            return back()->with('error', 'กรุณาระบุรหัส QR หรือรหัสพัสดุ');
        }

        // Extract token if full URL was scanned/pasted (e.g., https://.../scan/FKSP-0001)
        if (preg_match('/\/scan\/([A-Za-z0-9\-_]+)/', $raw, $m)) {
            $raw = $m[1];
        }

        $asset = Asset::where('qr_token', $raw)
            ->orWhere('asset_code', $raw)
            ->orWhere('asset_code', 'like', "%{$raw}%")
            ->first();

        if (! $asset) {
            return back()->with('error', "ไม่พบครุภัณฑ์ที่ตรงกับรหัส \"{$raw}\"");
        }

        return redirect()->route('scan.show', ['token' => $asset->qr_token]);
    }

    public function show(string $token, QrCodeService $qr): View
    {
        $asset = Asset::with([
            'inspections.inspector',
            'maintenanceLogs',
            'movements',
            'lastChecker',
        ])->where('qr_token', $token)->firstOrFail();

        $qrSvg = $qr->svg($asset->scanUrl(), 170);
        $openStockChecks = StockCheck::where('status', 'open')->latest('id')->get();
        $responsibles = Asset::approved()
            ->whereNotNull('responsible_person')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');

        return view('scan.show', compact('asset', 'qrSvg', 'openStockChecks', 'responsibles'));
    }

    public function checkIn(Request $request, string $token): RedirectResponse
    {
        $asset = Asset::where('qr_token', $token)->firstOrFail();

        $data = $request->validate([
            'condition_status' => ['required', 'in:usable,repair,damaged,lost'],
            'stock_check_id' => ['nullable', 'exists:stock_checks,id'],
            'location_found' => ['nullable', 'string', 'max:200'],
            'actual_responsible' => ['nullable', 'string', 'max:200'],
            'scan_method' => ['nullable', 'in:qr,manual'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($asset, $data) {
            $user = Auth::user();
            $oldStatus = $asset->status;
            $oldLocation = $asset->location;
            $oldResp = $asset->responsible_person;

            $newLocation = ! empty($data['location_found']) ? trim($data['location_found']) : $oldLocation;
            $newResp = ! empty($data['actual_responsible']) ? trim($data['actual_responsible']) : $oldResp;

            AssetInspection::create([
                'asset_id' => $asset->id,
                'stock_check_id' => $data['stock_check_id'] ?? null,
                'inspector_id' => $user->id,
                'inspector_name' => $user->name,
                'condition_status' => $data['condition_status'],
                'location_found' => $newLocation,
                'scan_method' => $data['scan_method'] ?? 'qr',
                'note' => $data['note'] ?? null,
                'inspected_at' => now(),
            ]);

            if (! empty($data['stock_check_id'])) {
                $checkStatus = match ($data['condition_status']) {
                    'usable' => 'found_usable',
                    'repair' => 'found_repair',
                    'damaged' => 'found_damaged',
                    'lost' => 'not_found',
                };

                StockCheckItem::updateOrCreate(
                    [
                        'stock_check_id' => $data['stock_check_id'],
                        'asset_id' => $asset->id,
                    ],
                    [
                        'checked_by' => $user->id,
                        'checker_name' => $user->name,
                        'check_status' => $checkStatus,
                        'actual_responsible' => $newResp,
                        'actual_location' => $newLocation,
                        'scan_method' => $data['scan_method'] ?? 'qr',
                        'remark' => $data['note'] ?? null,
                        'checked_at' => now(),
                    ]
                );
            }

            $asset->update([
                'status' => $data['condition_status'],
                'location' => $newLocation,
                'responsible_person' => $newResp,
                'last_checked_at' => now(),
                'last_checked_by' => $user->id,
            ]);

            if ($oldStatus !== $data['condition_status'] || $oldResp !== $newResp || $oldLocation !== $newLocation) {
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => $user->id,
                    'movement_type' => $oldResp !== $newResp ? 'transfer' : 'status_change',
                    'from_responsible' => $oldResp,
                    'to_responsible' => $newResp,
                    'from_location' => $oldLocation,
                    'to_location' => $newLocation,
                    'from_status' => $oldStatus,
                    'to_status' => $data['condition_status'],
                    'remark' => 'บันทึกจากการตรวจเช็ค QR: ' . ($data['note'] ?? '-'),
                    'moved_at' => now(),
                ]);
            }
        });

        return redirect()->route('scan.show', ['token' => $asset->qr_token])
            ->with('success', 'บันทึกผลการตรวจเช็คครุภัณฑ์เรียบร้อยแล้ว');
    }
}
