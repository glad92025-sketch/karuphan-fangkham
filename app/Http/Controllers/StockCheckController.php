<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetMovement;
use App\Models\StockCheck;
use App\Models\StockCheckItem;
use App\Services\ExcelAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockCheckController extends Controller
{
    public function index(): View
    {
        $totalApproved = Asset::approved()->count();
        $stockChecks = StockCheck::withCount([
            'items as checked_count' => fn ($q) => $q->whereHas('asset', fn ($aq) => $aq->where('review_status', 'approved')),
            'items as usable_count' => fn ($q) => $q->where('check_status', 'found_usable'),
            'items as issue_count' => fn ($q) => $q->whereIn('check_status', ['found_repair', 'found_damaged', 'not_found']),
        ])->latest('id')->get();

        return view('stock-checks.index', compact('stockChecks', 'totalApproved'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2600'],
            'started_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'title.required' => 'กรุณาระบุชื่อรอบการตรวจนับ',
        ]);

        $nextNum = (int) (StockCheck::max('id') ?? 0) + 1;
        $code = sprintf('CHK-%d-%02d', $data['fiscal_year'], $nextNum);

        $stockCheck = StockCheck::create([
            'code' => $code,
            'title' => $data['title'],
            'fiscal_year' => $data['fiscal_year'],
            'status' => 'open',
            'started_at' => $data['started_at'] ?? now()->toDateString(),
            'created_by' => Auth::id(),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('stock-checks.show', $stockCheck)
            ->with('success', "สร้างรอบตรวจนับ {$stockCheck->code} เรียบร้อยแล้ว");
    }

    public function show(Request $request, StockCheck $stockCheck): View
    {
        $tab = $request->string('tab')->toString() ?: 'unchecked';
        $search = trim($request->string('search')->toString());
        $responsibleFilter = trim($request->string('responsible_person')->toString());

        $approvedBase = Asset::approved();
        $totalAssets = (clone $approvedBase)->count();

        $checkedItemsAll = $stockCheck->items()
            ->with(['asset', 'checker'])
            ->whereHas('asset', fn ($q) => $q->where('review_status', 'approved'))
            ->get();

        $checkedIds = $checkedItemsAll->pluck('asset_id')->all();

        $summary = [
            'total' => $totalAssets,
            'checked' => $checkedItemsAll->count(),
            'unchecked' => max(0, $totalAssets - $checkedItemsAll->count()),
            'found_usable' => $checkedItemsAll->where('check_status', 'found_usable')->count(),
            'found_repair' => $checkedItemsAll->where('check_status', 'found_repair')->count(),
            'found_damaged' => $checkedItemsAll->where('check_status', 'found_damaged')->count(),
            'not_found' => $checkedItemsAll->where('check_status', 'not_found')->count(),
            'percent' => $totalAssets > 0 ? round(($checkedItemsAll->count() / $totalAssets) * 100, 1) : 0,
        ];

        $uncheckedAssets = Asset::approved()
            ->whereNotIn('id', $checkedIds ?: [0])
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('qr_token', 'like', "%{$search}%")
                ->orWhere('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('responsible_person', 'like', "%{$search}%")))
            ->when($responsibleFilter !== '', fn ($q) => $q->where('responsible_person', $responsibleFilter))
            ->orderBy('excel_seq')
            ->orderBy('id')
            ->paginate(25, ['*'], 'unchecked_page')
            ->withQueryString();

        $checkedItems = $stockCheck->items()
            ->with(['asset', 'checker'])
            ->whereHas('asset', function ($q) use ($search, $responsibleFilter) {
                $q->where('review_status', 'approved');
                if ($search !== '') {
                    $q->where(fn ($sub) => $sub
                        ->where('qr_token', 'like', "%{$search}%")
                        ->orWhere('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('responsible_person', 'like', "%{$search}%"));
                }
                if ($responsibleFilter !== '') {
                    $q->where('responsible_person', $responsibleFilter);
                }
            })
            ->when($tab === 'issues', fn ($q) => $q->whereIn('check_status', ['found_repair', 'found_damaged', 'not_found']))
            ->latest('checked_at')
            ->paginate(25, ['*'], 'checked_page')
            ->withQueryString();

        $responsibles = Asset::approved()
            ->whereNotNull('responsible_person')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');

        return view('stock-checks.show', compact(
            'stockCheck',
            'tab',
            'summary',
            'uncheckedAssets',
            'checkedItems',
            'responsibles',
            'search',
            'responsibleFilter'
        ));
    }

    public function scan(Request $request, StockCheck $stockCheck): JsonResponse|RedirectResponse
    {
        if (! $stockCheck->isOpen()) {
            $msg = 'รอบตรวจนับนี้ปิดแล้ว ไม่สามารถบันทึกเพิ่มได้';
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : back()->with('error', $msg);
        }

        $data = $request->validate([
            'code' => ['required_without:asset_id', 'nullable', 'string', 'max:255'],
            'asset_id' => ['required_without:code', 'nullable', 'exists:assets,id'],
            'check_status' => ['required', 'in:found_usable,found_repair,found_damaged,not_found'],
            'actual_responsible' => ['nullable', 'string', 'max:200'],
            'actual_location' => ['nullable', 'string', 'max:200'],
            'scan_method' => ['nullable', 'in:qr,manual'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);

        $asset = null;
        if (! empty($data['asset_id'])) {
            $asset = Asset::find($data['asset_id']);
        } else {
            $raw = trim((string) ($data['code'] ?? ''));
            if (preg_match('/\/scan\/([A-Za-z0-9\-_]+)/', $raw, $m)) {
                $raw = $m[1];
            }
            $asset = Asset::where('qr_token', $raw)
                ->orWhere('asset_code', $raw)
                ->first();
        }

        if (! $asset) {
            $msg = 'ไม่พบครุภัณฑ์ตามรหัสที่ระบุ: ' . ($data['code'] ?? '');
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 404)
                : back()->with('error', $msg);
        }

        $item = DB::transaction(function () use ($stockCheck, $asset, $data) {
            $user = Auth::user();
            $mappedAssetStatus = StockCheckItem::mapCheckStatusToAssetStatus($data['check_status']);
            $oldStatus = $asset->status;
            $oldResp = $asset->responsible_person;
            $oldLoc = $asset->location;

            $actualResp = ! empty($data['actual_responsible']) ? trim($data['actual_responsible']) : $asset->responsible_person;
            $actualLoc = ! empty($data['actual_location']) ? trim($data['actual_location']) : $asset->location;

            $item = StockCheckItem::updateOrCreate(
                [
                    'stock_check_id' => $stockCheck->id,
                    'asset_id' => $asset->id,
                ],
                [
                    'checked_by' => $user->id,
                    'checker_name' => $user->name,
                    'check_status' => $data['check_status'],
                    'actual_responsible' => $actualResp,
                    'actual_location' => $actualLoc,
                    'scan_method' => $data['scan_method'] ?? 'qr',
                    'remark' => $data['remark'] ?? null,
                    'checked_at' => now(),
                ]
            );

            AssetInspection::create([
                'asset_id' => $asset->id,
                'stock_check_id' => $stockCheck->id,
                'inspector_id' => $user->id,
                'inspector_name' => $user->name,
                'condition_status' => $mappedAssetStatus,
                'location_found' => $actualLoc,
                'scan_method' => $data['scan_method'] ?? 'qr',
                'note' => $data['remark'] ?? "ตรวจนับรอบ {$stockCheck->code}",
                'inspected_at' => now(),
            ]);

            $asset->update([
                'status' => $mappedAssetStatus,
                'location' => $actualLoc,
                'responsible_person' => $actualResp,
                'last_checked_at' => now(),
                'last_checked_by' => $user->id,
            ]);

            if ($oldStatus !== $mappedAssetStatus || $oldResp !== $actualResp || $oldLoc !== $actualLoc) {
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => $user->id,
                    'movement_type' => $oldResp !== $actualResp ? 'transfer' : 'status_change',
                    'from_responsible' => $oldResp,
                    'to_responsible' => $actualResp,
                    'from_location' => $oldLoc,
                    'to_location' => $actualLoc,
                    'from_status' => $oldStatus,
                    'to_status' => $mappedAssetStatus,
                    'reference_doc' => $stockCheck->code,
                    'remark' => 'ปรับปรุงจากการตรวจนับรอบ ' . $stockCheck->code,
                    'moved_at' => now(),
                ]);
            }

            return $item;
        });

        $total = Asset::approved()->count();
        $checked = $stockCheck->items()->whereHas('asset', fn ($q) => $q->where('review_status', 'approved'))->count();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "ตรวจนับ {$asset->displayCode()} ({$asset->name}) เรียบร้อย",
                'asset' => [
                    'id' => $asset->id,
                    'qr_token' => $asset->qr_token,
                    'display_code' => $asset->displayCode(),
                    'name' => $asset->name,
                    'responsible_person' => $asset->responsible_person,
                    'status_label' => $item->statusLabel(),
                    'checked_at' => $item->checked_at->format('d/m/Y H:i:s'),
                ],
                'progress' => [
                    'checked' => $checked,
                    'total' => $total,
                    'unchecked' => max(0, $total - $checked),
                    'percent' => $total > 0 ? round(($checked / $total) * 100, 1) : 0,
                ],
            ]);
        }

        return back()->with('success', "บันทึกตรวจนับ {$asset->displayCode()} ({$asset->name}) สำเร็จ");
    }

    public function toggleStatus(StockCheck $stockCheck): RedirectResponse
    {
        if ($stockCheck->isOpen()) {
            $stockCheck->update([
                'status' => 'closed',
                'closed_at' => now()->toDateString(),
            ]);
            return back()->with('success', "ปิดรอบตรวจนับ {$stockCheck->code} เรียบร้อยแล้ว");
        }

        $stockCheck->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return back()->with('success', "เปิดรอบตรวจนับ {$stockCheck->code} อีกครั้งแล้ว");
    }

    public function export(StockCheck $stockCheck, ExcelAssetService $excel): BinaryFileResponse
    {
        $assets = Asset::approved()->orderBy('excel_seq')->orderBy('id')->get();
        $path = $excel->exportStockCheckToXlsx($stockCheck, $assets);

        return response()->download($path, "รายงานตรวจนับ_{$stockCheck->code}.xlsx")->deleteFileAfterSend(true);
    }
}
