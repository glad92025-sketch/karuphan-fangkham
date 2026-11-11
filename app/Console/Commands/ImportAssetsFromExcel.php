<?php

namespace App\Console\Commands;

use App\Services\ExcelAssetService;
use Illuminate\Console\Command;

class ImportAssetsFromExcel extends Command
{
    protected $signature = 'assets:import {path? : Path to the Excel (.xlsx) file}';

    protected $description = 'นำเข้าข้อมูลครุภัณฑ์จากไฟล์ Excel เข้าสู่ฐานข้อมูล';

    public function handle(ExcelAssetService $excel): int
    {
        $path = $this->argument('path')
            ?? storage_path('app/seed/ตารางครุภัณฑ์69_สำนักปลัด.xlsx');

        $this->info("กำลังนำเข้าข้อมูลจาก: {$path}");
        $result = $excel->importFromWorkbook($path);

        $this->info("สำเร็จ! สำนักปลัด: {$result['approved']} รายการ, รอตรวจสอบ: {$result['pending']} รายการ, ข้าม: {$result['skipped']}");

        return self::SUCCESS;
    }
}
