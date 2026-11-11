<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\AssetMovement;
use App\Models\MaintenanceLog;
use App\Models\StockCheck;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $approvedQuery = Asset::approved();

        $stats = [
            'total' => (clone $approvedQuery)->count(),
            'total_value' => (float) (clone $approvedQuery)->sum('price'),
            'usable' => (clone $approvedQuery)->where('status', 'usable')->count(),
            'repair' => (clone $approvedQuery)->where('status', 'repair')->count(),
            'damaged' => (clone $approvedQuery)->whereIn('status', ['damaged', 'lost', 'disposed'])->count(),
            'pending_review' => Asset::pendingReview()->count(),
            'fy2568' => (clone $approvedQuery)->where('fiscal_year', 2568)->count(),
            'fy2569' => (clone $approvedQuery)->where('fiscal_year', 2569)->count(),
        ];

        $activeStockCheck = StockCheck::where('status', 'open')->latest('id')->first();
        $stockCheckProgress = null;
        if ($activeStockCheck) {
            $checkedCount = $activeStockCheck->items()
                ->whereHas('asset', fn ($q) => $q->where('review_status', 'approved'))
                ->count();
            $totalTarget = max($stats['total'], 1);
            $stockCheckProgress = [
                'campaign' => $activeStockCheck,
                'checked' => $checkedCount,
                'total' => $stats['total'],
                'percent' => round(($checkedCount / $totalTarget) * 100, 1),
            ];
        }

        $recentAssets = Asset::approved()
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $recentInspections = AssetInspection::with(['asset', 'inspector'])
            ->latest('inspected_at')
            ->limit(6)
            ->get();

        $recentMovements = AssetMovement::with(['asset', 'user'])
            ->latest('moved_at')
            ->limit(6)
            ->get();

        $openMaintenances = MaintenanceLog::with('asset')
            ->whereIn('maintenance_status', ['pending', 'in_progress'])
            ->latest('reported_at')
            ->limit(5)
            ->get();

        $categorySummary = Asset::approved()
            ->selectRaw('COALESCE(category, "ไม่ระบุหมวดหมู่") as category_name, COUNT(*) as total_count, COALESCE(SUM(price), 0) as total_price')
            ->groupBy('category_name')
            ->orderByDesc('total_count')
            ->get();

        $sampleAsset = Asset::approved()->orderBy('id')->first();

        return view('dashboard', compact(
            'stats',
            'stockCheckProgress',
            'recentAssets',
            'recentInspections',
            'recentMovements',
            'openMaintenances',
            'categorySummary',
            'sampleAsset'
        ));
    }
}
