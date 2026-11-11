<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MovementController extends Controller
{
    public function index(Request $request): View
    {
        $typeFilter = trim($request->string('type')->toString());
        $search = trim($request->string('search')->toString());

        $movements = AssetMovement::with(['asset', 'user'])
            ->when($typeFilter !== '', fn ($q) => $q->where('movement_type', $typeFilter))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('to_responsible', 'like', "%{$search}%")
                    ->orWhere('from_responsible', 'like', "%{$search}%")
                    ->orWhere('reference_doc', 'like', "%{$search}%")
                    ->orWhere('remark', 'like', "%{$search}%")
                    ->orWhereHas('asset', fn ($aq) => $aq
                        ->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('qr_token', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%"));
            }))
            ->latest('moved_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $assets = Asset::approved()->orderBy('excel_seq')->orderBy('id')->get(['id', 'qr_token', 'asset_code', 'name', 'responsible_person', 'location', 'status']);
        $responsibles = Asset::approved()->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');

        return view('movements.index', compact('movements', 'assets', 'responsibles', 'typeFilter', 'search'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'movement_type' => ['required', 'in:transfer,status_change,dispose'],
            'to_responsible' => ['nullable', 'string', 'max:200'],
            'to_location' => ['nullable', 'string', 'max:200'],
            'to_status' => ['required', 'in:usable,repair,damaged,disposed,lost'],
            'reference_doc' => ['nullable', 'string', 'max:150'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset = Asset::findOrFail($data['asset_id']);

        DB::transaction(function () use ($asset, $data) {
            $oldResp = $asset->responsible_person;
            $oldLoc = $asset->location;
            $oldStatus = $asset->status;

            $newStatus = $data['movement_type'] === 'dispose' ? 'disposed' : $data['to_status'];
            $newResp = $data['to_responsible'] ? trim($data['to_responsible']) : $oldResp;
            $newLoc = $data['to_location'] ? trim($data['to_location']) : $oldLoc;

            $asset->update([
                'responsible_person' => $newResp,
                'location' => $newLoc,
                'status' => $newStatus,
            ]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id' => Auth::id(),
                'movement_type' => $data['movement_type'],
                'from_responsible' => $oldResp,
                'to_responsible' => $newResp,
                'from_location' => $oldLoc,
                'to_location' => $newLoc,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'reference_doc' => $data['reference_doc'] ?? null,
                'remark' => $data['remark'] ?? null,
                'moved_at' => now(),
            ]);
        });

        return back()->with('success', "บันทึกความเคลื่อนไหวของ {$asset->displayCode()} เรียบร้อยแล้ว");
    }
}
