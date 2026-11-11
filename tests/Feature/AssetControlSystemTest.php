<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceLog;
use App\Models\StockCheck;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class AssetControlSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_excel_seed_imports_exact_samnak_palad_counts_and_sums(): void
    {
        $this->assertSame(194, Asset::approved()->count());
        $this->assertSame(123, Asset::pendingReview()->count());

        $totalSum = round((float) Asset::approved()->sum('price'), 2);
        $this->assertEquals(6235389.22, $totalSum);

        $fy2568 = Asset::approved()->where('fiscal_year', 2568);
        $this->assertSame(186, $fy2568->count());
        $this->assertEquals(5950689.22, round((float) $fy2568->sum('price'), 2));

        // Verify ditto resolution and formula evaluation edge cases
        $dittoCat = Asset::where('asset_code', '433-62-0019')->firstOrFail();
        $this->assertSame('ครุภัณฑ์สำนักงาน', $dittoCat->category);

        $dittoDate = Asset::where('asset_code', '711-63-0002')->firstOrFail();
        $this->assertSame('13 มย.63', $dittoDate->acquired_date_text);

        $formulaAsset = Asset::where('asset_code', '408-49-0001')->firstOrFail();
        $this->assertEquals(4205.61, (float) $formulaAsset->price);

        $subComponent = Asset::where('asset_code', '464-62-0004')->firstOrFail();
        $this->assertSame('ลำโพงเคลื่อนที่ สีดำ', $subComponent->name);
        $this->assertSame('สำนักงานปลัด', $subComponent->responsible_person);
    }

    public function test_dashboard_and_main_pages_render_for_authenticated_admin(): void
    {
        $admin = User::where('email', 'admin@fangkham.go.th')->firstOrFail();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('6,235,389.22')
            ->assertSee('194');

        $this->actingAs($admin)->get(route('assets.index'))
            ->assertOk()
            ->assertSee('400-42-0013');

        $this->actingAs($admin)->get(route('assets.index', ['review_status' => 'pending']))
            ->assertOk()
            ->assertSee('123');

        $this->actingAs($admin)->get(route('scan.index'))->assertOk();
        $this->actingAs($admin)->get(route('stock-checks.index'))->assertOk();
        $this->actingAs($admin)->get(route('maintenances.index'))->assertOk();
        $this->actingAs($admin)->get(route('movements.index'))->assertOk();
        $this->actingAs($admin)->get(route('qr.print'))->assertOk();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk();
    }

    public function test_qr_scan_public_view_and_authenticated_stock_check_in(): void
    {
        $asset = Asset::approved()->firstOrFail();
        $stockCheck = StockCheck::where('status', 'open')->firstOrFail();
        $staff = User::where('email', 'staff@fangkham.go.th')->firstOrFail();

        // Guest can view public QR tag read-only with responsible person & position
        $this->get(route('scan.show', ['token' => $asset->qr_token]))
            ->assertOk()
            ->assertSee($asset->name)
            ->assertSee('ผู้รับผิดชอบ')
            ->assertSee('ตำแหน่ง')
            ->assertSee('เข้าสู่ระบบเพื่อตรวจเช็ค');

        // Authenticated staff can perform QR check-in linked to stock check & key in position
        $this->actingAs($staff)->post(route('scan.check', ['token' => $asset->qr_token]), [
            'condition_status' => 'usable',
            'stock_check_id' => $stockCheck->id,
            'location_found' => 'ห้องสำนักปลัด ชั้น 1',
            'actual_responsible' => 'นายสมชาย ใจดี',
            'actual_position' => 'หัวหน้าสำนักปลัด',
            'scan_method' => 'qr',
            'note' => 'สแกนตรวจสอบสภาพปกติ',
        ])->assertRedirect(route('scan.show', ['token' => $asset->qr_token]));

        $asset->refresh();
        $this->assertSame('นายสมชาย ใจดี', $asset->responsible_person);
        $this->assertSame('หัวหน้าสำนักปลัด', $asset->position_title);
        $this->assertNotNull($asset->last_checked_at);
        $this->assertSame($staff->id, $asset->last_checked_by);
        $this->assertDatabaseHas('stock_check_items', [
            'stock_check_id' => $stockCheck->id,
            'asset_id' => $asset->id,
            'check_status' => 'found_usable',
            'actual_position' => 'หัวหน้าสำนักปลัด',
        ]);

        // Edit page works and returns to scan page when return_to=scan
        $this->actingAs($staff)->get(route('assets.edit', ['asset' => $asset, 'return_to' => 'scan']))
            ->assertOk()
            ->assertSee('หัวหน้าสำนักปลัด');

        $this->actingAs($staff)->put(route('assets.update', $asset), [
            'name' => $asset->name,
            'fiscal_year' => $asset->fiscal_year,
            'price' => $asset->price,
            'quantity' => $asset->quantity,
            'unit' => $asset->unit,
            'status' => 'usable',
            'responsible_person' => 'นางสาวกนกวรรณ',
            'position_title' => 'นักทรัพยากรบุคคล',
            'location' => 'ห้องธุรการ',
            'return_to' => 'scan',
        ])->assertRedirect(route('scan.show', ['token' => $asset->qr_token]));

        $this->assertSame('นักทรัพยากรบุคคล', $asset->fresh()->position_title);
        $this->assertDatabaseHas('asset_inspections', [
            'asset_id' => $asset->id,
            'stock_check_id' => $stockCheck->id,
        ]);
    }

    public function test_continuous_ajax_stock_check_scan_updates_progress(): void
    {
        $admin = User::where('email', 'admin@fangkham.go.th')->firstOrFail();
        $stockCheck = StockCheck::where('status', 'open')->firstOrFail();
        $asset = Asset::approved()->skip(2)->firstOrFail();

        $res = $this->actingAs($admin)->postJson(route('stock-checks.scan', $stockCheck), [
            'code' => $asset->scanUrl(),
            'check_status' => 'found_repair',
            'scan_method' => 'qr',
        ]);

        $res->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('asset.qr_token', $asset->qr_token)
            ->assertJsonPath('progress.checked', 1)
            ->assertJsonPath('progress.total', 194);

        $this->assertSame('repair', $asset->fresh()->status);
    }

    public function test_create_asset_assign_pending_and_maintenance_lifecycle(): void
    {
        $admin = User::where('email', 'admin@fangkham.go.th')->firstOrFail();

        // 1. Create new asset
        $this->actingAs($admin)->post(route('assets.store'), [
            'asset_code' => '416-69-9999',
            'name' => 'เครื่องสแกนเอกสารความเร็วสูง',
            'category' => 'ครุภัณฑ์คอมพิวเตอร์',
            'fiscal_year' => 2569,
            'price' => 15500,
            'responsible_person' => 'สำนักปลัด(ผช.ธุรการ)',
            'location' => 'ห้องธุรการ',
            'quantity' => 1,
            'unit' => 'เครื่อง',
            'status' => 'usable',
        ])->assertRedirect();

        $newAsset = Asset::where('asset_code', '416-69-9999')->firstOrFail();
        $this->assertStringStartsWith('FKSP-', $newAsset->qr_token);

        // Verify SVG QR endpoint
        $this->actingAs($admin)->get(route('assets.qr.svg', $newAsset))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');

        // 2. Open maintenance ticket & complete it
        $this->actingAs($admin)->post(route('maintenances.store'), [
            'asset_id' => $newAsset->id,
            'issue_description' => 'ฟีดกระดาษติด',
            'repair_cost' => 1200,
        ])->assertRedirect();

        $this->assertSame('repair', $newAsset->fresh()->status);
        $ticket = MaintenanceLog::where('asset_id', $newAsset->id)->firstOrFail();

        $this->actingAs($admin)->patch(route('maintenances.update', $ticket), [
            'maintenance_status' => 'completed',
            'repair_cost' => 950,
            'resolution_note' => 'เปลี่ยนลูกยางดึงกระดาษเรียบร้อย',
        ])->assertRedirect();

        $this->assertSame('usable', $newAsset->fresh()->status);

        // 3. Approve a pending item with position name
        $pending = Asset::pendingReview()->firstOrFail();
        $this->actingAs($admin)->post(route('assets.approve', $pending), [
            'responsible_person' => 'สำนักปลัด(นักทรัพย์)',
            'location' => 'ห้องสำนักปลัด',
        ])->assertRedirect();

        $pending->refresh();
        $this->assertSame('approved', $pending->review_status);
        $this->assertSame('สำนักปลัด(นักทรัพย์)', $pending->responsible_person);
    }

    public function test_excel_export_produces_valid_xlsx_file(): void
    {
        $admin = User::where('email', 'admin@fangkham.go.th')->firstOrFail();
        $response = $this->actingAs($admin)->get(route('reports.export'));

        $response->assertOk();
        $file = $response->getFile();
        $this->assertFileExists($file->getPathname());

        $reader = new Reader();
        $reader->open($file->getPathname());
        $rowCount = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rowCount++;
            }
            break;
        }
        $reader->close();

        // 2 title rows + 1 header row + 194 asset rows + 1 total row = 198 rows
        $this->assertSame(198, $rowCount);
    }

    public function test_search_api_movements_stock_check_export_and_batch_qr_print(): void
    {
        $admin = User::where('email', 'admin@fangkham.go.th')->firstOrFail();
        $asset = Asset::approved()->firstOrFail();
        $stockCheck = StockCheck::where('status', 'open')->firstOrFail();

        // Search JSON API
        $this->actingAs($admin)->getJson(route('api.assets.search', ['q' => $asset->asset_code]))
            ->assertOk()
            ->assertJsonFragment(['qr_token' => $asset->qr_token]);

        // Transfer / Movement
        $this->actingAs($admin)->post(route('movements.store'), [
            'asset_id' => $asset->id,
            'movement_type' => 'transfer',
            'to_responsible' => 'สำนักปลัด(หน.สป.)',
            'to_location' => 'ห้องหัวหน้าสำนักปลัด',
            'to_status' => 'usable',
            'reference_doc' => 'บค.01/2569',
            'remark' => 'ย้ายจุดใช้งาน',
        ])->assertRedirect();

        $this->assertSame('สำนักปลัด(หน.สป.)', $asset->fresh()->responsible_person);

        // Batch QR print all 194 labels by default at 15mm (1.5x1.5 cm)
        $this->actingAs($admin)->get(route('qr.print'))
            ->assertOk()
            ->assertSee('194 ดวง')
            ->assertSee('--qr-mm: 15mm;')
            ->assertSee('แผ่นที่ 5 / 5');

        // Print with skip slots & legacy grid-24 alias
        $this->actingAs($admin)->get(route('qr.print', ['layout' => 'grid-24', 'skip' => 4, 'qr_mm' => 15]))
            ->assertOk()
            ->assertSee('is-blank');

        // Stock check export
        $exportRes = $this->actingAs($admin)->get(route('stock-checks.export', $stockCheck));
        $exportRes->assertOk();
        $this->assertFileExists($exportRes->getFile()->getPathname());

        // Custom pagination (no giant Tailwind SVG) & All-items Checklist & Bulk Edit
        $this->actingAs($admin)->get(route('stock-checks.show', $stockCheck))
            ->assertOk()
            ->assertSee('custom-pagination')
            ->assertDontSee('w-5 h-5');

        $this->actingAs($admin)->get(route('assets.index', ['per_page' => 'all']))
            ->assertOk()
            ->assertSee('FKSP-0194');

        $this->actingAs($admin)->get(route('assets.checklist'))
            ->assertOk()
            ->assertSee('194')
            ->assertSee('FKSP-0001')
            ->assertSee('FKSP-0194');

        $this->actingAs($admin)->get(route('assets.bulk-edit'))
            ->assertOk()
            ->assertSee('FKSP-0194');

        $this->actingAs($admin)->post(route('assets.bulk-update'), [
            'rows' => [
                $asset->id => [
                    'responsible_person' => 'นายทดสอบ ระบบ',
                    'position_title' => 'นักวิชาการคอมพิวเตอร์',
                    'location' => 'ห้องไอที',
                    'status' => 'usable',
                ],
            ],
        ])->assertRedirect();

        $this->assertSame('นายทดสอบ ระบบ', $asset->fresh()->responsible_person);
        $this->assertSame('นักวิชาการคอมพิวเตอร์', $asset->fresh()->position_title);
    }
}
