<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\StockCheck;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $reviewStatus = $this->resolveReviewStatus($request);
        $query = $this->buildFilteredQuery($request, $reviewStatus);

        $filteredCount = (clone $query)->count();
        $filteredSum = (float) (clone $query)->sum('price');

        $perPageParam = $request->string('per_page')->toString() ?: '25';
        $perPage = match ($perPageParam) {
            '50' => 50,
            '100' => 100,
            'all' => max($filteredCount, 1),
            default => 25,
        };

        $assets = $query
            ->orderByRaw('CASE WHEN excel_seq IS NULL THEN 1 ELSE 0 END')
            ->orderBy('excel_seq')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = Asset::where('review_status', $reviewStatus)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $responsibles = Asset::where('review_status', $reviewStatus)
            ->whereNotNull('responsible_person')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');

        $approvedResponsibles = Asset::approved()
            ->whereNotNull('responsible_person')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');

        $pendingCount = Asset::pendingReview()->count();
        $approvedCount = Asset::approved()->count();
        $positions = Asset::positionSuggestions();

        return view('assets.index', compact(
            'assets',
            'reviewStatus',
            'perPageParam',
            'categories',
            'responsibles',
            'approvedResponsibles',
            'positions',
            'filteredCount',
            'filteredSum',
            'pendingCount',
            'approvedCount'
        ));
    }

    public function checklist(Request $request, QrCodeService $qr): View
    {
        $reviewStatus = $this->resolveReviewStatus($request);
        $withQr = $request->boolean('with_qr', true);

        $assets = $this->buildFilteredQuery($request, $reviewStatus)
            ->orderByRaw('CASE WHEN excel_seq IS NULL THEN 1 ELSE 0 END')
            ->orderBy('excel_seq')
            ->orderBy('id')
            ->get();

        $qrSvgs = [];
        if ($withQr) {
            foreach ($assets as $asset) {
                $qrSvgs[$asset->id] = $qr->svg($asset->scanUrl(), 80, 1, true);
            }
        }

        $categories = Asset::where('review_status', $reviewStatus)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $responsibles = Asset::where('review_status', $reviewStatus)->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');
        $totalSum = (float) $assets->sum('price');

        return view('assets.checklist', compact(
            'assets',
            'reviewStatus',
            'withQr',
            'qrSvgs',
            'categories',
            'responsibles',
            'totalSum'
        ));
    }

    public function bulkEdit(Request $request): View
    {
        $reviewStatus = $this->resolveReviewStatus($request);
        $onlyMissingPosition = $request->boolean('missing_position');

        $query = $this->buildFilteredQuery($request, $reviewStatus);
        if ($onlyMissingPosition) {
            $query->where(fn ($q) => $q->whereNull('position_title')->orWhere('position_title', ''));
        }

        $assets = $query
            ->orderByRaw('CASE WHEN excel_seq IS NULL THEN 1 ELSE 0 END')
            ->orderBy('excel_seq')
            ->orderBy('id')
            ->get();

        $categories = Asset::where('review_status', $reviewStatus)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $responsibles = Asset::approved()->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');
        $positions = Asset::positionSuggestions();

        return view('assets.bulk-edit', compact(
            'assets',
            'reviewStatus',
            'onlyMissingPosition',
            'categories',
            'responsibles',
            'positions'
        ));
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.responsible_person' => ['nullable', 'string', 'max:200'],
            'rows.*.position_title' => ['nullable', 'string', 'max:200'],
            'rows.*.location' => ['nullable', 'string', 'max:200'],
            'rows.*.status' => ['required', 'in:usable,repair,damaged,disposed,lost'],
        ]);

        $ids = array_keys($data['rows']);
        $assets = Asset::whereIn('id', $ids)->get()->keyBy('id');
        $updatedCount = 0;

        DB::transaction(function () use ($data, $assets, &$updatedCount) {
            foreach ($data['rows'] as $id => $row) {
                $asset = $assets->get((int) $id);
                if (! $asset) {
                    continue;
                }

                $newResp = trim((string) ($row['responsible_person'] ?? '')) ?: null;
                $newPos = trim((string) ($row['position_title'] ?? '')) ?: null;
                $newLoc = trim((string) ($row['location'] ?? '')) ?: null;
                $newStatus = $row['status'];

                if (
                    $asset->responsible_person === $newResp
                    && $asset->position_title === $newPos
                    && $asset->location === $newLoc
                    && $asset->status === $newStatus
                ) {
                    continue;
                }

                $oldResp = $asset->responsible_person;
                $oldPos = $asset->position_title;
                $oldLoc = $asset->location;
                $oldStatus = $asset->status;

                $asset->update([
                    'responsible_person' => $newResp,
                    'position_title' => $newPos,
                    'location' => $newLoc,
                    'status' => $newStatus,
                ]);

                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => Auth::id(),
                    'movement_type' => ($oldResp !== $newResp || $oldPos !== $newPos) ? 'transfer' : 'status_change',
                    'from_responsible' => $oldResp,
                    'to_responsible' => $newResp,
                    'from_location' => $oldLoc,
                    'to_location' => $newLoc,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'remark' => 'บันทึกคีย์ข้อมูลแบบตาราง' . ($newPos ? " (ตำแหน่ง: {$newPos})" : ''),
                    'moved_at' => now(),
                ]);

                $updatedCount++;
            }
        });

        return back()->with('success', "บันทึกการปรับปรุงข้อมูลสำเร็จ {$updatedCount} รายการ");
    }

    private function resolveReviewStatus(Request $request): string
    {
        $reviewStatus = $request->string('review_status')->toString() ?: 'approved';
        return in_array($reviewStatus, ['approved', 'pending'], true) ? $reviewStatus : 'approved';
    }

    private function buildFilteredQuery(Request $request, string $reviewStatus)
    {
        $query = Asset::query()->where('review_status', $reviewStatus);

        if ($search = trim($request->string('search')->toString())) {
            $query->search($search);
        }

        if ($fiscalYear = $request->integer('fiscal_year')) {
            $query->where('fiscal_year', $fiscalYear);
        }

        if ($category = trim($request->string('category')->toString())) {
            $query->where('category', $category);
        }

        if ($responsible = trim($request->string('responsible_person')->toString())) {
            if ($responsible === '__EMPTY__') {
                $query->whereNull('responsible_person');
            } else {
                $query->where('responsible_person', $responsible);
            }
        }

        if ($status = trim($request->string('status')->toString())) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function create(): View
    {
        $categories = Asset::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $responsibles = Asset::approved()->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');
        $positions = Asset::positionSuggestions();
        $nextToken = Asset::generateNextToken();

        return view('assets.create', compact('categories', 'responsibles', 'positions', 'nextToken'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAsset($request);
        $validated['qr_token'] = Asset::generateNextToken();
        $validated['department'] = 'สำนักปลัด';
        $validated['review_status'] = 'approved';
        $validated['created_by'] = Auth::id();
        $validated['excel_seq'] = (int) (Asset::approved()->max('excel_seq') ?? 0) + 1;

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('assets', 'public');
        }

        $asset = DB::transaction(function () use ($validated) {
            $asset = Asset::create($validated);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id' => Auth::id(),
                'movement_type' => 'register',
                'to_responsible' => $asset->responsible_person,
                'to_location' => $asset->location,
                'to_status' => $asset->status,
                'remark' => 'ลงทะเบียนครุภัณฑ์ใหม่เข้าระบบ',
                'moved_at' => now(),
            ]);

            return $asset;
        });

        return redirect()->route('assets.show', $asset)
            ->with('success', "ลงทะเบียนครุภัณฑ์ {$asset->displayCode()} สำเร็จ (QR: {$asset->qr_token})");
    }

    public function show(Asset $asset, QrCodeService $qr): View
    {
        $asset->load([
            'movements.user',
            'maintenanceLogs.reporter',
            'inspections.inspector',
            'inspections.stockCheck',
            'lastChecker',
        ]);

        $qrSvg = $qr->svg($asset->scanUrl(), 200);
        $openStockChecks = StockCheck::where('status', 'open')->latest('id')->get();
        $responsibles = Asset::approved()
            ->whereNotNull('responsible_person')
            ->distinct()
            ->orderBy('responsible_person')
            ->pluck('responsible_person');
        $positions = Asset::positionSuggestions();

        return view('assets.show', compact('asset', 'qrSvg', 'openStockChecks', 'responsibles', 'positions'));
    }

    public function edit(Request $request, Asset $asset): View
    {
        $categories = Asset::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $responsibles = Asset::approved()->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');
        $positions = Asset::positionSuggestions();
        $returnTo = $request->string('return_to')->toString();

        return view('assets.edit', compact('asset', 'categories', 'responsibles', 'positions', 'returnTo'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $this->validateAsset($request);

        if ($request->hasFile('photo')) {
            if ($asset->photo_path) {
                Storage::disk('public')->delete($asset->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('assets', 'public');
        }

        $oldResp = $asset->responsible_person;
        $oldPos = $asset->position_title;
        $oldLoc = $asset->location;
        $oldStatus = $asset->status;

        DB::transaction(function () use ($asset, $validated, $oldResp, $oldPos, $oldLoc, $oldStatus) {
            $asset->update($validated);

            if ($oldResp !== $asset->responsible_person || $oldPos !== $asset->position_title || $oldLoc !== $asset->location || $oldStatus !== $asset->status) {
                $posNote = $oldPos !== $asset->position_title ? ' (ตำแหน่ง: ' . ($asset->position_title ?? '-') . ')' : '';
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id' => Auth::id(),
                    'movement_type' => ($oldResp !== $asset->responsible_person || $oldPos !== $asset->position_title) ? 'transfer' : 'status_change',
                    'from_responsible' => $oldResp,
                    'to_responsible' => $asset->responsible_person,
                    'from_location' => $oldLoc,
                    'to_location' => $asset->location,
                    'from_status' => $oldStatus,
                    'to_status' => $asset->status,
                    'remark' => 'แก้ไขข้อมูลทะเบียนครุภัณฑ์' . $posNote,
                    'moved_at' => now(),
                ]);
            }
        });

        if ($request->string('return_to')->toString() === 'scan') {
            return redirect()->route('scan.show', ['token' => $asset->qr_token])
                ->with('success', 'อัปเดตข้อมูลครุภัณฑ์เรียบร้อยแล้ว');
        }

        return redirect()->route('assets.show', $asset)
            ->with('success', 'อัปเดตข้อมูลครุภัณฑ์เรียบร้อยแล้ว');
    }

    public function approvePending(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'responsible_person' => ['required', 'string', 'max:200'],
            'position_title' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'responsible_person.required' => 'กรุณาระบุผู้รับผิดชอบก่อนนำเข้าทะเบียนสำนักปลัด',
        ]);

        DB::transaction(function () use ($asset, $data) {
            $oldResp = $asset->responsible_person;
            $nextSeq = (int) (Asset::approved()->max('excel_seq') ?? 0) + 1;

            $asset->update([
                'responsible_person' => trim($data['responsible_person']),
                'position_title' => ! empty($data['position_title']) ? trim($data['position_title']) : $asset->position_title,
                'location' => ! empty($data['location']) ? trim($data['location']) : ($asset->location ?: 'สำนักปลัด อบต.ฝางคำ'),
                'department' => 'สำนักปลัด',
                'review_status' => 'approved',
                'excel_seq' => $nextSeq,
                'note' => $data['note'] ?? $asset->note,
            ]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id' => Auth::id(),
                'movement_type' => 'assign',
                'from_responsible' => $oldResp ?? 'ยังไม่ระบุผู้รับผิดชอบ',
                'to_responsible' => $asset->responsible_person,
                'to_location' => $asset->location,
                'to_status' => $asset->status,
                'remark' => 'จัดสรรเข้าทะเบียนครุภัณฑ์สำนักปลัด',
                'moved_at' => now(),
            ]);
        });

        return redirect()->route('assets.index', ['review_status' => 'pending'])
            ->with('success', "นำเข้า {$asset->name} เข้าสู่ทะเบียนสำนักปลัดเรียบร้อยแล้ว");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if (! Auth::user()->isAdmin()) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบครุภัณฑ์ได้');
        }

        $asset->delete();

        return redirect()->route('assets.index')
            ->with('success', 'ลบรายการครุภัณฑ์เรียบร้อยแล้ว');
    }

    public function qrSvg(Asset $asset, QrCodeService $qr): Response
    {
        $svg = $qr->svg($asset->scanUrl(), 320);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="qr-' . $asset->qr_token . '.svg"',
        ]);
    }

    public function searchJson(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        $assets = Asset::approved()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('qr_token', 'like', "%{$q}%")
                        ->orWhere('asset_code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('responsible_person', 'like', "%{$q}%");
                });
            })
            ->orderBy('id')
            ->limit(15)
            ->get(['id', 'qr_token', 'asset_code', 'name', 'responsible_person', 'status', 'price']);

        return response()->json($assets->map(fn (Asset $a) => [
            'id' => $a->id,
            'qr_token' => $a->qr_token,
            'asset_code' => $a->asset_code,
            'display_code' => $a->displayCode(),
            'name' => $a->name,
            'responsible_person' => $a->responsible_person,
            'status' => $a->status,
            'status_label' => $a->statusLabel(),
            'price' => number_format((float) $a->price, 2),
            'scan_url' => $a->scanUrl(),
            'detail_url' => route('assets.show', $a),
        ]));
    }

    private function validateAsset(Request $request): array
    {
        return $request->validate([
            'asset_code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:500'],
            'specification' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:150'],
            'acquired_date_text' => ['nullable', 'string', 'max:100'],
            'acquired_date' => ['nullable', 'date'],
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2600'],
            'price' => ['required', 'numeric', 'min:0'],
            'responsible_person' => ['nullable', 'string', 'max:200'],
            'position_title' => ['nullable', 'string', 'max:200'],
            'location' => ['nullable', 'string', 'max:200'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit' => ['required', 'string', 'max:50'],
            'status' => ['required', 'in:usable,repair,damaged,disposed,lost'],
            'note' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ], [
            'name.required' => 'กรุณาระบุชื่อครุภัณฑ์/พัสดุ',
            'price.required' => 'กรุณาระบุราคาครุภัณฑ์',
        ]);
    }
}
