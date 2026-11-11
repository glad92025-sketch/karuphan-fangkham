<?php

namespace Database\Seeders;

use App\Models\StockCheck;
use App\Models\User;
use App\Services\ExcelAssetService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@fangkham.go.th'],
            [
                'name' => 'สมชาย ใจดี',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'position' => 'เจ้าหน้าที่พัสดุ (สำนักปลัด)',
                'department' => 'สำนักปลัด',
                'phone' => '045-000-001',
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@fangkham.go.th'],
            [
                'name' => 'กนกวรรณ รักษ์งาน',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'position' => 'คณะกรรมการตรวจนับครุภัณฑ์',
                'department' => 'สำนักปลัด',
                'phone' => '045-000-002',
            ]
        );

        $seedExcel = storage_path('app/seed/ตารางครุภัณฑ์69_สำนักปลัด.xlsx');
        if (is_file($seedExcel)) {
            app(ExcelAssetService::class)->importFromWorkbook($seedExcel, $admin->id);
        }

        StockCheck::firstOrCreate(
            ['code' => 'CHK-2569-01'],
            [
                'title' => 'ตรวจนับครุภัณฑ์ประจำปีงบประมาณ 2569 (สำนักปลัด)',
                'fiscal_year' => 2569,
                'status' => 'open',
                'started_at' => now()->toDateString(),
                'created_by' => $admin->id,
                'note' => 'รอบตรวจนับครุภัณฑ์และเช็คสต็อกประจำปี สำนักปลัด อบต.ฝางคำ',
            ]
        );
    }
}
