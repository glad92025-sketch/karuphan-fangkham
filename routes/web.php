<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\QrPrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StockCheckController;
use Illuminate\Support\Facades\Route;

// Public QR Landing Page (read-only when guest, interactive check-in when logged in)
Route::get('/scan/{token}', [ScanController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('scan.show');

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Assets CRUD & Position Assignment
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/checklist', [AssetController::class, 'checklist'])->name('assets.checklist');
    Route::get('/assets/bulk-edit', [AssetController::class, 'bulkEdit'])->name('assets.bulk-edit');
    Route::post('/assets/bulk-edit', [AssetController::class, 'bulkUpdate'])->name('assets.bulk-update');
    Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
    Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::post('/assets/{asset}/approve', [AssetController::class, 'approvePending'])->name('assets.approve');
    Route::get('/assets/{asset}/qr.svg', [AssetController::class, 'qrSvg'])->name('assets.qr.svg');
    Route::get('/api/assets/search', [AssetController::class, 'searchJson'])->name('api.assets.search');

    // QR Scanner & Check-in
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::post('/scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup');
    Route::post('/scan/{token}/check', [ScanController::class, 'checkIn'])
        ->where('token', '[A-Za-z0-9\-_]+')
        ->name('scan.check');

    // Stock Check / Annual Audit Campaigns
    Route::get('/stock-checks', [StockCheckController::class, 'index'])->name('stock-checks.index');
    Route::post('/stock-checks', [StockCheckController::class, 'store'])->name('stock-checks.store');
    Route::get('/stock-checks/{stockCheck}', [StockCheckController::class, 'show'])->name('stock-checks.show');
    Route::post('/stock-checks/{stockCheck}/scan', [StockCheckController::class, 'scan'])->name('stock-checks.scan');
    Route::patch('/stock-checks/{stockCheck}/toggle', [StockCheckController::class, 'toggleStatus'])->name('stock-checks.toggle');
    Route::get('/stock-checks/{stockCheck}/export', [StockCheckController::class, 'export'])->name('stock-checks.export');

    // Maintenance & Repairs
    Route::get('/maintenances', [MaintenanceController::class, 'index'])->name('maintenances.index');
    Route::post('/maintenances', [MaintenanceController::class, 'store'])->name('maintenances.store');
    Route::patch('/maintenances/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenances.update');

    // Asset Movements / Transfers / Disposals
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
    Route::post('/movements', [MovementController::class, 'store'])->name('movements.store');

    // Batch QR Sticker Printing
    Route::get('/qr/print', [QrPrintController::class, 'index'])->name('qr.print');

    // Reports & Excel Import / Export
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'exportExcel'])->name('reports.export');
    Route::post('/reports/import', [ReportController::class, 'importExcel'])->name('reports.import');
});
