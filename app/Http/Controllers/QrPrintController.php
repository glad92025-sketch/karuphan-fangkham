<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrPrintController extends Controller
{
    public const LAYOUTS = [
        'a4-40' => ['label' => 'A4 ประหยัด (4×10 = 40 ดวง/แผ่น)', 'cols' => 4, 'rows' => 10, 'per_page' => 40, 'compact' => true],
        'a4-30' => ['label' => 'A4 มาตรฐาน (3×10 = 30 ดวง/แผ่น)', 'cols' => 3, 'rows' => 10, 'per_page' => 30, 'compact' => true],
        'a4-24' => ['label' => 'A4 ไดคัท (3×8 = 24 ดวง/แผ่น)', 'cols' => 3, 'rows' => 8, 'per_page' => 24, 'compact' => false],
        'a4-21' => ['label' => 'A4 ไดคัท (3×7 = 21 ดวง/แผ่น)', 'cols' => 3, 'rows' => 7, 'per_page' => 21, 'compact' => false],
        'a4-65' => ['label' => 'A4 ดวงจิ๋ว (5×13 = 65 ดวง/แผ่น)', 'cols' => 5, 'rows' => 13, 'per_page' => 65, 'compact' => true],
        'a4-10' => ['label' => 'A4 ป้ายใหญ่ (2×5 = 10 ดวง/แผ่น)', 'cols' => 2, 'rows' => 5, 'per_page' => 10, 'compact' => false],
        'thermal' => ['label' => 'สติ๊กเกอร์ม้วนเดี่ยว (Thermal)', 'cols' => 4, 'rows' => 0, 'per_page' => 9999, 'compact' => true],
    ];

    public function index(Request $request, QrCodeService $qr): View
    {
        $ids = array_filter((array) $request->input('ids', []), fn ($v) => is_numeric($v));
        $scope = $request->string('scope')->toString() ?: 'approved';
        $fiscalYear = $request->integer('fiscal_year');
        $responsible = trim($request->string('responsible_person')->toString());
        $category = trim($request->string('category')->toString());
        $search = trim($request->string('search')->toString());
        $seqFrom = $request->integer('seq_from') ?: null;
        $seqTo = $request->integer('seq_to') ?: null;

        $layout = $request->string('layout')->toString() ?: 'a4-40';
        $layout = match ($layout) {
            'grid-24' => 'a4-24',
            'grid-10' => 'a4-10',
            default => $layout,
        };
        if (! array_key_exists($layout, self::LAYOUTS)) {
            $layout = 'a4-40';
        }
        $layoutMeta = self::LAYOUTS[$layout];

        $qrMm = (int) ($request->input('qr_mm', 15));
        if (! in_array($qrMm, [12, 15, 18, 20, 25], true)) {
            $qrMm = 15;
        }

        $skip = max(0, min(64, (int) $request->input('skip', 0)));
        $borderMode = $request->string('border_mode')->toString() ?: 'dashed';
        if (! in_array($borderMode, ['dashed', 'solid', 'none'], true)) {
            $borderMode = 'dashed';
        }
        $pureBlack = $request->boolean('pure_black', true);
        $offsetTop = max(-15, min(15, (float) $request->input('offset_top', 0)));
        $offsetLeft = max(-15, min(15, (float) $request->input('offset_left', 0)));

        $query = $scope === 'all_with_pending' ? Asset::query() : Asset::approved();

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } else {
            if ($fiscalYear) {
                $query->where('fiscal_year', $fiscalYear);
            }
            if ($responsible !== '') {
                $query->where('responsible_person', $responsible);
            }
            if ($category !== '') {
                $query->where('category', $category);
            }
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('qr_token', 'like', "%{$search}%")
                        ->orWhere('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('responsible_person', 'like', "%{$search}%");
                });
            }
            if ($seqFrom !== null) {
                $query->where('excel_seq', '>=', $seqFrom);
            }
            if ($seqTo !== null) {
                $query->where('excel_seq', '<=', $seqTo);
            }
        }

        $assets = $query
            ->orderByRaw("CASE WHEN review_status = 'approved' THEN 0 ELSE 1 END")
            ->orderBy('excel_seq')
            ->orderBy('id')
            ->get();

        $qrSvgs = [];
        foreach ($assets as $asset) {
            $qrSvgs[$asset->id] = $qr->svg($asset->scanUrl(), 120, 1, $pureBlack);
        }

        // Build sticker slots (including skipped blank slots for partially used A4 sheets)
        $slots = [];
        for ($i = 0; $i < $skip; $i++) {
            $slots[] = null;
        }
        foreach ($assets as $asset) {
            $slots[] = $asset;
        }

        $pages = $layout === 'thermal'
            ? [$slots]
            : array_chunk($slots, $layoutMeta['per_page']);

        $responsibles = Asset::approved()->whereNotNull('responsible_person')->distinct()->orderBy('responsible_person')->pluck('responsible_person');
        $categories = Asset::approved()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return view('qr.print', [
            'assets' => $assets,
            'pages' => $pages,
            'qrSvgs' => $qrSvgs,
            'layouts' => self::LAYOUTS,
            'layout' => $layout,
            'layoutMeta' => $layoutMeta,
            'qrMm' => $qrMm,
            'skip' => $skip,
            'borderMode' => $borderMode,
            'pureBlack' => $pureBlack,
            'offsetTop' => $offsetTop,
            'offsetLeft' => $offsetLeft,
            'scope' => $scope,
            'fiscalYear' => $fiscalYear,
            'responsible' => $responsible,
            'category' => $category,
            'search' => $search,
            'seqFrom' => $seqFrom,
            'seqTo' => $seqTo,
            'responsibles' => $responsibles,
            'categories' => $categories,
        ]);
    }
}
