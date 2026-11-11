<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\StockCheck;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

class ExcelAssetService
{
    private const THAI_MONTHS = [
        'มค' => 1, 'มกราคม' => 1,
        'กพ' => 2, 'กุมภาพันธ์' => 2,
        'มีค' => 3, 'มีนาคม' => 3,
        'เมย' => 4, 'เมษายน' => 4,
        'พค' => 5, 'พฤษภาคม' => 5,
        'มิย' => 6, 'มิถุนายน' => 6,
        'กค' => 7, 'กรกฎาคม' => 7,
        'สค' => 8, 'สิงหาคม' => 8,
        'กย' => 9, 'กันยายน' => 9,
        'ตค' => 10, 'ตุลาคม' => 10,
        'พย' => 11, 'พฤศจิกายน' => 11,
        'ธค' => 12, 'ธันวาคม' => 12,
    ];

    /**
     * Import assets from the standard Thai municipal Excel workbook.
     *
     * @return array{approved: int, pending: int, skipped: int}
     */
    public function importFromWorkbook(string $filePath, ?int $userId = null): array
    {
        if (! is_file($filePath)) {
            throw new \RuntimeException("ไม่พบไฟล์ Excel ที่ระบุ: {$filePath}");
        }

        $sheet2568Keys = [];
        $rows2569 = [];
        $rowsPending = [];
        $has2569Sheet = false;
        $has2568Sheet = false;

        $reader = new Reader();
        $reader->open($filePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = trim($sheet->getName());
            if ($sheetName === 'รวมครุภัณฑ์2568') {
                $has2568Sheet = true;
                foreach ($this->extractSheetRows($sheet) as $item) {
                    $sheet2568Keys[$this->rowSignature($item)] = true;
                }
            } elseif ($sheetName === 'รวมครุภัณฑ์เพิ่มปี2569') {
                $has2569Sheet = true;
                $rows2569 = $this->extractSheetRows($sheet);
            } elseif (str_starts_with($sheetName, 'รอตรวจสอบ')) {
                $rowsPending = $this->extractSheetRows($sheet, hasNoteColumnK: true);
            }
        }
        $reader->close();

        // Fallback if user uploads a single-sheet asset Excel file
        if (! $has2569Sheet && $has2568Sheet) {
            $reader2 = new Reader();
            $reader2->open($filePath);
            foreach ($reader2->getSheetIterator() as $sheet) {
                if (trim($sheet->getName()) === 'รวมครุภัณฑ์2568') {
                    $rows2569 = $this->extractSheetRows($sheet);
                    break;
                }
            }
            $reader2->close();
        } elseif (! $has2569Sheet && ! $has2568Sheet) {
            $reader2 = new Reader();
            $reader2->open($filePath);
            foreach ($reader2->getSheetIterator() as $sheet) {
                $rows2569 = $this->extractSheetRows($sheet);
                break;
            }
            $reader2->close();
        }

        $approvedCount = 0;
        $pendingCount = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $rows2569,
            $rowsPending,
            $sheet2568Keys,
            $has2568Sheet,
            $userId,
            &$approvedCount,
            &$pendingCount,
            &$skipped
        ) {
            $nextTokenNum = (int) (Asset::withTrashed()->max('id') ?? 0) + 1;
            $prevApproved = null;

            foreach ($rows2569 as $item) {
                if (! $item['has_content']) {
                    $skipped++;
                    continue;
                }

                $sig = $this->rowSignature($item);
                $fiscalYear = $has2568Sheet
                    ? (isset($sheet2568Keys[$sig]) ? 2568 : 2569)
                    : $this->inferFiscalYear($item['asset_code'], $item['acquired_date']);

                $responsible = $item['responsible_person'];
                $note = $item['extra_note'];
                if ($responsible === null && $item['asset_code'] === '464-62-0004' && $prevApproved !== null) {
                    $responsible = $prevApproved['responsible_person'];
                    $note = trim(($note ? $note . ' | ' : '') . 'ส่วนประกอบของชุดเครื่องเสียงเคลื่อนที่');
                }

                $existing = $this->findExistingAsset($item['asset_code'], $item['name'], $item['excel_seq'], 'approved');
                $payload = [
                    'excel_seq' => $item['excel_seq'],
                    'asset_code' => $item['asset_code'],
                    'name' => $item['name'],
                    'specification' => $item['specification'],
                    'category' => $item['category'],
                    'acquired_date_text' => $item['acquired_date_text'],
                    'acquired_date' => $item['acquired_date'],
                    'fiscal_year' => $fiscalYear,
                    'price' => $item['price'],
                    'responsible_person' => $responsible,
                    'position_title' => $this->inferPositionTitle($responsible),
                    'department' => 'สำนักปลัด',
                    'location' => $this->inferLocation($responsible, $item['specification']),
                    'quantity' => 1,
                    'unit' => $this->inferUnit($item['name']),
                    'status' => $item['status'],
                    'review_status' => 'approved',
                    'note' => $note,
                    'created_by' => $userId,
                ];

                if ($existing) {
                    $existing->update($payload);
                } else {
                    while (Asset::withTrashed()->where('qr_token', sprintf('FKSP-%04d', $nextTokenNum))->exists()) {
                        $nextTokenNum++;
                    }
                    $payload['qr_token'] = sprintf('FKSP-%04d', $nextTokenNum++);
                    $asset = Asset::create($payload);
                    AssetMovement::create([
                        'asset_id' => $asset->id,
                        'user_id' => $userId,
                        'movement_type' => 'register',
                        'to_responsible' => $asset->responsible_person,
                        'to_location' => $asset->location,
                        'to_status' => $asset->status,
                        'remark' => 'นำเข้าฐานข้อมูลจาก Excel (ปี ' . $fiscalYear . ')',
                        'moved_at' => now(),
                    ]);
                }

                $prevApproved = $payload;
                $approvedCount++;
            }

            foreach ($rowsPending as $item) {
                if (! $item['has_content']) {
                    $skipped++;
                    continue;
                }

                $existing = $this->findExistingAsset($item['asset_code'], $item['name'], $item['excel_seq'], 'pending');
                $note = trim(implode(' | ', array_filter([
                    $item['review_note'] ?? null,
                    $item['extra_note'] ?? null,
                ])));

                $payload = [
                    'excel_seq' => $item['excel_seq'],
                    'asset_code' => $item['asset_code'],
                    'name' => $item['name'],
                    'specification' => $item['specification'],
                    'category' => $item['category'],
                    'acquired_date_text' => $item['acquired_date_text'],
                    'acquired_date' => $item['acquired_date'],
                    'fiscal_year' => $this->inferFiscalYear($item['asset_code'], $item['acquired_date']),
                    'price' => $item['price'],
                    'responsible_person' => $item['responsible_person'],
                    'position_title' => $this->inferPositionTitle($item['responsible_person']),
                    'department' => 'รอตรวจสอบ',
                    'location' => $this->inferLocation($item['responsible_person'], $item['specification']),
                    'quantity' => 1,
                    'unit' => $this->inferUnit($item['name']),
                    'status' => $item['status'],
                    'review_status' => 'pending',
                    'note' => $note ?: null,
                    'created_by' => $userId,
                ];

                if ($existing) {
                    $existing->update($payload);
                } else {
                    while (Asset::withTrashed()->where('qr_token', sprintf('FKSP-%04d', $nextTokenNum))->exists()) {
                        $nextTokenNum++;
                    }
                    $payload['qr_token'] = sprintf('FKSP-%04d', $nextTokenNum++);
                    Asset::create($payload);
                }
                $pendingCount++;
            }
        });

        return [
            'approved' => $approvedCount,
            'pending' => $pendingCount,
            'skipped' => $skipped,
        ];
    }

    /**
     * Export assets to a temporary XLSX file and return its path.
     */
    public function exportAssetsToXlsx(Collection $assets, string $title = 'ทะเบียนครุภัณฑ์ สำนักปลัด อบต.ฝางคำ'): string
    {
        $tempPath = storage_path('app/exports/assets_' . now()->format('Ymd_His') . '_' . uniqid() . '.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new Writer();
        $writer->openToFile($tempPath);
        $writer->getCurrentSheet()->setName('ครุภัณฑ์สำนักปลัด');

        $titleStyle = (new Style())
            ->withFontBold(true)
            ->withFontSize(14)
            ->withFontColor(Color::DARK_BLUE);

        $headerStyle = (new Style())
            ->withFontBold(true)
            ->withFontSize(11)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor('1E40AF')
            ->withCellAlignment(CellAlignment::CENTER);

        $moneyStyle = (new Style())
            ->withCellAlignment(CellAlignment::RIGHT)
            ->withFormat('#,##0.00');

        $totalStyle = (new Style())
            ->withFontBold(true)
            ->withBackgroundColor('EFF6FF')
            ->withCellAlignment(CellAlignment::RIGHT)
            ->withFormat('#,##0.00');

        $writer->addRow(Row::fromValuesWithStyle([$title], $titleStyle));
        $writer->addRow(Row::fromValues(['องค์การบริหารส่วนตำบลฝางคำ อำเภอสิรินธร จังหวัดอุบลราชธานี | ส่งออกวันที่ ' . now()->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValuesWithStyle([
            'ลำดับ',
            'รหัส QR',
            'รหัสพัสดุ',
            'ชื่อพัสดุ',
            'ยี่ห้อ/แบบ/ชนิด/ลักษณะ',
            'หมวดหมู่',
            'ได้มาเมื่อวันที่',
            'ปีงบประมาณ',
            'ราคา (บาท)',
            'ผู้รับผิดชอบ',
            'ตำแหน่ง',
            'สถานที่ตั้ง',
            'สภาพ/สถานะ',
            'ตรวจเช็คล่าสุด',
            'หมายเหตุ',
        ], $headerStyle));

        $sumPrice = 0.0;
        foreach ($assets->values() as $idx => $asset) {
            $price = (float) $asset->price;
            $sumPrice += $price;
            $writer->addRow(Row::fromValuesWithStyles([
                $idx + 1,
                $asset->qr_token,
                $asset->asset_code ?? '-',
                $asset->name,
                $asset->specification ?? '',
                $asset->category ?? '',
                $asset->acquired_date_text ?? '',
                $asset->fiscal_year,
                $price,
                $asset->responsible_person ?? '',
                $asset->position_title ?? '',
                $asset->location ?? '',
                $asset->statusLabel(),
                $asset->last_checked_at ? $asset->last_checked_at->format('d/m/Y H:i') : 'ยังไม่ตรวจ',
                $asset->note ?? '',
            ], [8 => $moneyStyle]));
        }

        $writer->addRow(Row::fromValuesWithStyles([
            '', '', '', 'รวมทั้งสิ้น ' . $assets->count() . ' รายการ', '', '', '', '', $sumPrice, '', '', '', '', '', '',
        ], [3 => $totalStyle, 8 => $totalStyle]));

        $writer->close();

        return $tempPath;
    }

    /**
     * Export a stock check audit report to XLSX.
     */
    public function exportStockCheckToXlsx(StockCheck $stockCheck, Collection $allAssets): string
    {
        $tempPath = storage_path('app/exports/stock_check_' . $stockCheck->code . '_' . now()->format('Ymd_His') . '.xlsx');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $itemsByAsset = $stockCheck->items()->with('checker')->get()->keyBy('asset_id');

        $writer = new Writer();
        $writer->openToFile($tempPath);
        $writer->getCurrentSheet()->setName('ผลการตรวจนับ');

        $titleStyle = (new Style())->withFontBold(true)->withFontSize(14)->withFontColor(Color::DARK_BLUE);
        $headerStyle = (new Style())
            ->withFontBold(true)
            ->withFontColor(Color::WHITE)
            ->withBackgroundColor('1E40AF')
            ->withCellAlignment(CellAlignment::CENTER);

        $writer->addRow(Row::fromValuesWithStyle([$stockCheck->title . " ({$stockCheck->code})"], $titleStyle));
        $writer->addRow(Row::fromValues(['สถานะรอบ: ' . ($stockCheck->isOpen() ? 'กำลังตรวจนับ' : 'ปิดรอบแล้ว') . ' | ส่งออกเมื่อ ' . now()->format('d/m/Y H:i')]));
        $writer->addRow(Row::fromValuesWithStyle([
            'ลำดับ',
            'รหัส QR',
            'รหัสพัสดุ',
            'ชื่อพัสดุ',
            'ผู้รับผิดชอบ (ทะเบียน)',
            'ราคา (บาท)',
            'สถานะการตรวจนับ',
            'ผู้ถือครองจริงที่พบ',
            'สถานที่พบ',
            'ช่องทาง',
            'ผู้ตรวจนับ',
            'วันเวลาที่ตรวจ',
            'หมายเหตุ',
        ], $headerStyle));

        foreach ($allAssets->values() as $idx => $asset) {
            $checked = $itemsByAsset->get($asset->id);
            $writer->addRow(Row::fromValues([
                $idx + 1,
                $asset->qr_token,
                $asset->asset_code ?? '-',
                $asset->name,
                $asset->responsible_person ?? '-',
                (float) $asset->price,
                $checked ? $checked->statusLabel() : 'ยังไม่ตรวจนับ',
                $checked?->actual_responsible ?? '-',
                $checked?->actual_location ?? '-',
                $checked ? ($checked->scan_method === 'qr' ? 'สแกน QR' : 'บันทึกเอง') : '-',
                $checked?->checker_name ?? $checked?->checker?->name ?? '-',
                $checked?->checked_at?->format('d/m/Y H:i') ?? '-',
                $checked?->remark ?? '-',
            ]));
        }

        $writer->close();

        return $tempPath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSheetRows(object $sheet, bool $hasNoteColumnK = false): array
    {
        $items = [];
        $rowIndex = 0;
        $prevDateText = null;
        $prevCategory = null;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex++;
            if ($rowIndex <= 4) {
                continue;
            }

            $cells = $row->cells;
            $seq = $this->cellScalar($cells[0] ?? null);
            $code = $this->cleanString($this->cellScalar($cells[1] ?? null));
            $name = $this->cleanString($this->cellScalar($cells[2] ?? null));
            $spec = $this->cleanString($this->cellScalar($cells[3] ?? null));
            $dateRaw = $this->cellValueRaw($cells[4] ?? null);
            $price = $this->cellNumeric($cells[5] ?? null);
            $responsible = $this->cleanString($this->cellScalar($cells[6] ?? null));
            $usableMark = $this->cleanString($this->cellScalar($cells[7] ?? null));
            $unusableMark = $this->cleanString($this->cellScalar($cells[8] ?? null));
            $rawCat = $this->cleanString($this->cellScalar($cells[9] ?? null));
            $reviewNote = $hasNoteColumnK ? $this->cleanString($this->cellScalar($cells[10] ?? null)) : null;

            // Skip SUM footer or empty trailing rows
            $hasContent = ($name !== null || $code !== null || $spec !== null);
            if (! $hasContent) {
                continue;
            }

            // Resolve ditto marks '"' from preceding row
            [$dateText, $parsedDate] = $this->parseAcquiredDate($dateRaw, $prevDateText);
            if ($dateText !== null) {
                $prevDateText = $dateText;
            }

            if ($rawCat === '"') {
                $rawCat = $prevCategory;
            } elseif ($rawCat !== null) {
                $prevCategory = $rawCat;
            }

            [$category, $extraNote] = $this->normalizeCategory($rawCat);

            $status = ($unusableMark !== null && $unusableMark !== '') ? 'damaged' : 'usable';
            $resolvedName = $name ?? $spec ?? 'ไม่ระบุชื่อพัสดุ';

            $items[] = [
                'has_content' => true,
                'excel_seq' => is_numeric($seq) ? (int) $seq : null,
                'asset_code' => $code,
                'name' => $resolvedName,
                'specification' => $spec,
                'acquired_date_text' => $dateText,
                'acquired_date' => $parsedDate,
                'price' => $price,
                'responsible_person' => $responsible,
                'status' => $status,
                'category' => $category,
                'extra_note' => $extraNote,
                'review_note' => $reviewNote,
            ];
        }

        return $items;
    }

    private function cellValueRaw(?object $cell): mixed
    {
        if ($cell === null) {
            return null;
        }
        if ($cell instanceof FormulaCell) {
            return $cell->getComputedValue() ?? $cell->getValue();
        }
        return $cell->getValue();
    }

    private function cellScalar(?object $cell): ?string
    {
        $val = $this->cellValueRaw($cell);
        if ($val === null) {
            return null;
        }
        if ($val instanceof DateTimeInterface) {
            return $val->format('d/m/Y');
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        return (string) $val;
    }

    private function cellNumeric(?object $cell): float
    {
        if ($cell === null) {
            return 0.0;
        }
        if ($cell instanceof FormulaCell) {
            $computed = $cell->getComputedValue();
            if (is_numeric($computed)) {
                return round((float) $computed, 2);
            }
            $expr = ltrim($cell->getValue(), '=');
            if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $expr, $m) && (float) $m[2] !== 0.0) {
                return round((float) $m[1] / (float) $m[2], 2);
            }
            return 0.0;
        }
        $val = $cell->getValue();
        return is_numeric($val) ? round((float) $val, 2) : 0.0;
    }

    private function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{0: ?string, 1: ?string} [displayText, Y-m-d CE date or null]
     */
    private function parseAcquiredDate(mixed $raw, ?string $prevDateText): array
    {
        if ($raw === null) {
            return [null, null];
        }

        if ($raw instanceof DateTimeInterface) {
            $year = (int) $raw->format('Y');
            $month = (int) $raw->format('m');
            $day = (int) $raw->format('d');
            if ($year > 2400) {
                $display = sprintf('%02d/%02d/%04d', $day, $month, $year);
                $ceYear = $year - 543;
            } else {
                $display = sprintf('%02d/%02d/%04d', $day, $month, $year + 543);
                $ceYear = $year;
            }
            $iso = checkdate($month, $day, $ceYear) ? sprintf('%04d-%02d-%02d', $ceYear, $month, $day) : null;
            return [$display, $iso];
        }

        $text = $this->cleanString((string) $raw);
        if ($text === null) {
            return [null, null];
        }
        if ($text === '"') {
            $text = $prevDateText;
            if ($text === null) {
                return [null, null];
            }
        }

        // Format d/m/Y (e.g. 3/09/2569 or 14/9/2569)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2500;
            }
            $ceYear = $year > 2400 ? $year - 543 : $year;
            $iso = checkdate($month, $day, $ceYear) ? sprintf('%04d-%02d-%02d', $ceYear, $month, $day) : null;
            return [$text, $iso];
        }

        // Thai date e.g. '18 มิ.ย. 2542', '13 มย.63', '29 ก.ย.2547'
        if (preg_match('/^(\d{1,2})\s*([ก-๙\.]+)\s*(\d{2,4})$/u', $text, $m)) {
            $day = (int) $m[1];
            $mClean = str_replace('.', '', $m[2]);
            $month = self::THAI_MONTHS[$mClean] ?? null;
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2500;
            }
            $ceYear = $year > 2400 ? $year - 543 : $year;
            $iso = ($month !== null && checkdate($month, $day, $ceYear))
                ? sprintf('%04d-%02d-%02d', $ceYear, $month, $day)
                : null;
            return [$text, $iso];
        }

        return [$text, null];
    }

    /**
     * @return array{0: ?string, 1: ?string} [normalizedCategory, extraNote]
     */
    private function normalizeCategory(?string $raw): array
    {
        if ($raw === null) {
            return [null, null];
        }

        return match ($raw) {
            'ครุภํณฑ์คอมพิวเตอร์', 'ครุภัณฑ์คอมฯ' => ['ครุภัณฑ์คอมพิวเตอร์', null],
            'บันทึกรับเข้าจากการตรวจครุภัณฑ์ ปี 2562' => ['ครุภัณฑ์สำนักงาน', $raw],
            default => [$raw, null],
        };
    }

    private function inferFiscalYear(?string $assetCode, ?string $acquiredDate): int
    {
        if ($assetCode && preg_match('/^\d+-(\d{2})-\d+$/', $assetCode, $m)) {
            $yy = (int) $m[1];
            return $yy >= 69 ? 2569 : 2568;
        }
        if ($acquiredDate) {
            $c = Carbon::parse($acquiredDate);
            $be = $c->year + 543;
            return ($c->month >= 10 ? $be + 1 : $be) >= 2569 ? 2569 : 2568;
        }
        return 2569;
    }

    private function inferPositionTitle(?string $responsible): ?string
    {
        if ($responsible && preg_match('/\(([^)]+)\)/u', $responsible, $m)) {
            $inside = trim($m[1]);
            if ($inside !== '' && ! str_contains($inside, 'หมู่ที่')) {
                return $inside;
            }
        }
        return null;
    }

    private function inferLocation(?string $responsible, ?string $spec): ?string
    {
        if ($responsible) {
            if (str_contains($responsible, 'หมู่ที่')) {
                return trim(preg_replace('/.*(หมู่ที่\s*\d+).*/u', '$1', $responsible) ?? $responsible);
            }
            if (str_contains($responsible, 'ห้อง')) {
                return $responsible;
            }
            if (str_contains($responsible, 'สภา')) {
                return 'ห้องประชุมสภา อบต.';
            }
        }
        if ($spec && preg_match('/(บ้าน[^\s\)]+|หมู่ที่\s*\d+|ห้อง[^\)]+)/u', $spec, $m)) {
            return trim($m[1]);
        }
        return $responsible ? 'สำนักปลัด อบต.ฝางคำ' : null;
    }

    private function inferUnit(string $name): string
    {
        return match (true) {
            str_contains($name, 'โต๊ะ'), str_contains($name, 'เก้าอี้') => 'ตัว',
            str_contains($name, 'ตู้') => 'ตู้',
            str_contains($name, 'รถ') => 'คัน',
            str_contains($name, 'ชุด'), str_contains($name, 'ระบบ'), str_contains($name, 'ซุ้ม'), str_contains($name, 'เวที') => 'ชุด',
            str_contains($name, 'ผ้าม่าน') => 'ชุด',
            str_contains($name, 'เครื่อง'), str_contains($name, 'คอมพิวเตอร์'), str_contains($name, 'พัดลม'), str_contains($name, 'กล้อง'), str_contains($name, 'โปรเจค'), str_contains($name, 'ปั้ม') => 'เครื่อง',
            default => 'หน่วย',
        };
    }

    private function rowSignature(array $item): string
    {
        if ($item['asset_code']) {
            return 'CODE:' . mb_strtolower($item['asset_code']);
        }
        return 'NAME:' . mb_strtolower($item['name']) . '|' . number_format((float) $item['price'], 2, '.', '');
    }

    private function findExistingAsset(?string $assetCode, string $name, ?int $excelSeq, string $reviewStatus): ?Asset
    {
        if ($assetCode) {
            return Asset::withTrashed()
                ->where('asset_code', $assetCode)
                ->where('review_status', $reviewStatus)
                ->first();
        }
        return Asset::withTrashed()
            ->whereNull('asset_code')
            ->where('name', $name)
            ->where('excel_seq', $excelSeq)
            ->where('review_status', $reviewStatus)
            ->first();
    }
}
