<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceLog;
use App\Models\StockCheck;
use App\Services\ExcelAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        $totalCount = Asset::approved()->count();
        $totalValue = (float) Asset::approved()->sum('price');

        $byCategory = Asset::approved()
            ->selectRaw('COALESCE(category, "ไม่ระบุหมวดหมู่") as label, COUNT(*) as qty, COALESCE(SUM(price), 0) as amount')
            ->groupBy('label')
            ->orderByDesc('amount')
            ->get();

        $byResponsible = Asset::approved()
            ->selectRaw('COALESCE(responsible_person, "ไม่ระบุตำแหน่ง") as label, COUNT(*) as qty, COALESCE(SUM(price), 0) as amount')
            ->groupBy('label')
            ->orderByDesc('qty')
            ->get();

        $byYear = Asset::approved()
            ->selectRaw('fiscal_year, COUNT(*) as qty, COALESCE(SUM(price), 0) as amount')
            ->groupBy('fiscal_year')
            ->orderBy('fiscal_year')
            ->get();

        $byStatus = Asset::approved()
            ->selectRaw('status, COUNT(*) as qty, COALESCE(SUM(price), 0) as amount')
            ->groupBy('status')
            ->get();

        $repairTotalCost = (float) MaintenanceLog::sum('repair_cost');
        $stockChecks = StockCheck::withCount('items')->latest('id')->get();
        $pendingCount = Asset::pendingReview()->count();

        return view('reports.index', compact(
            'totalCount',
            'totalValue',
            'byCategory',
            'byResponsible',
            'byYear',
            'byStatus',
            'repairTotalCost',
            'stockChecks',
            'pendingCount'
        ));
    }

    public function exportExcel(Request $request, ExcelAssetService $excel): BinaryFileResponse
    {
        $query = Asset::approved();

        if ($fiscalYear = $request->integer('fiscal_year')) {
            $query->where('fiscal_year', $fiscalYear);
        }
        if ($category = trim($request->string('category')->toString())) {
            $query->where('category', $category);
        }
        if ($responsible = trim($request->string('responsible_person')->toString())) {
            $query->where('responsible_person', $responsible);
        }
        if ($status = trim($request->string('status')->toString())) {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('excel_seq')->orderBy('id')->get();
        $path = $excel->exportAssetsToXlsx($assets);

        $filename = 'ทะเบียนครุภัณฑ์_สำนักปลัด_' . now()->format('Ymd_His') . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request, ExcelAssetService $excel): RedirectResponse
    {
        if (! Auth::user()->isAdmin()) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถนำเข้าไฟล์ Excel ได้');
        }

        $request->validate([
            'excel_file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'excel_file.required' => 'กรุณาเลือกไฟล์ .xlsx ที่ต้องการนำเข้า',
            'excel_file.mimes' => 'รองรับเฉพาะไฟล์ Excel นามสกุล .xlsx เท่านั้น',
        ]);

        $stored = $request->file('excel_file')->store('imports');
        $fullPath = Storage::path($stored);

        try {
            $result = $excel->importFromWorkbook($fullPath, Auth::id());
        } finally {
            Storage::delete($stored);
        }

        return back()->with(
            'success',
            "นำเข้าข้อมูลจาก Excel สำเร็จ (สำนักปลัด {$result['approved']} รายการ, รอตรวจสอบ {$result['pending']} รายการ)"
        );
    }
}
