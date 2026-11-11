<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\MaintenanceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = trim($request->string('status')->toString());
        $search = trim($request->string('search')->toString());

        $logs = MaintenanceLog::with(['asset', 'reporter'])
            ->when($statusFilter !== '', fn ($q) => $q->where('maintenance_status', $statusFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('issue_description', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%")
                    ->orWhereHas('asset', fn ($aq) => $aq
                        ->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('qr_token', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            }))
            ->latest('reported_at')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'pending' => MaintenanceLog::where('maintenance_status', 'pending')->count(),
            'in_progress' => MaintenanceLog::where('maintenance_status', 'in_progress')->count(),
            'completed' => MaintenanceLog::where('maintenance_status', 'completed')->count(),
            'total_cost' => (float) MaintenanceLog::sum('repair_cost'),
        ];

        $assets = Asset::approved()->orderBy('excel_seq')->orderBy('id')->get(['id', 'qr_token', 'asset_code', 'name', 'responsible_person', 'status']);

        return view('maintenances.index', compact('logs', 'summary', 'assets', 'statusFilter', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'issue_description' => ['required', 'string', 'max:2000'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
        ], [
            'asset_id.required' => 'กรุณาเลือกครุภัณฑ์ที่ต้องการแจ้งซ่อม',
            'issue_description.required' => 'กรุณาระบุอาการชำรุดหรือสาเหตุที่แจ้งซ่อม',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);

        DB::transaction(function () use ($asset, $data) {
            $user = Auth::user();
            $oldStatus = $asset->status;

            MaintenanceLog::create([
                'asset_id' => $asset->id,
                'reported_by' => $user->id,
                'reporter_name' => $user->name,
                'issue_description' => $data['issue_description'],
                'maintenance_status' => 'pending',
                'vendor_name' => $data['vendor_name'] ?? null,
                'repair_cost' => $data['repair_cost'] ?? 0,
                'reported_at' => now(),
            ]);

            if ($oldStatus !== 'repair') {
                $asset->update(['status' => 'repair']);

                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => $user->id,
                    'movement_type' => 'status_change',
                    'from_status' => $oldStatus,
                    'to_status' => 'repair',
                    'remark' => 'แจ้งซ่อม: ' . $data['issue_description'],
                    'moved_at' => now(),
                ]);
            }
        });

        return back()->with('success', "เปิดใบแจ้งซ่อมสำหรับ {$asset->displayCode()} เรียบร้อยแล้ว");
    }

    public function update(Request $request, MaintenanceLog $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_status' => ['required', 'in:pending,in_progress,completed,unrepairable'],
            'vendor_name' => ['nullable', 'string', 'max:200'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($maintenance, $data) {
            $isDone = in_array($data['maintenance_status'], ['completed', 'unrepairable'], true);

            $maintenance->update([
                'maintenance_status' => $data['maintenance_status'],
                'vendor_name' => $data['vendor_name'] ?? $maintenance->vendor_name,
                'repair_cost' => $data['repair_cost'] ?? $maintenance->repair_cost,
                'resolution_note' => $data['resolution_note'] ?? $maintenance->resolution_note,
                'completed_at' => $isDone ? ($maintenance->completed_at ?? now()) : null,
            ]);

            $asset = $maintenance->asset;
            $oldStatus = $asset->status;
            $newAssetStatus = match ($data['maintenance_status']) {
                'completed' => 'usable',
                'unrepairable' => 'damaged',
                default => 'repair',
            };

            if ($oldStatus !== $newAssetStatus) {
                $asset->update(['status' => $newAssetStatus]);

                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => Auth::id(),
                    'movement_type' => 'status_change',
                    'from_status' => $oldStatus,
                    'to_status' => $newAssetStatus,
                    'remark' => 'อัปเดตงานซ่อม (' . $maintenance->statusLabel() . '): ' . ($data['resolution_note'] ?? '-'),
                    'moved_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'อัปเดตสถานะงานซ่อมเรียบร้อยแล้ว');
    }
}
