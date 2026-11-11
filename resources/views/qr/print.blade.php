@extends('layouts.app')

@section('title', 'พิมพ์ QR Code ทั้งหมดลงกระดาษสติ๊กเกอร์ | ระบบควบคุมครุภัณฑ์')

@push('styles')
<style>
  :root {
    --qr-mm: {{ $qrMm }}mm;
    --offset-top: {{ $offsetTop }}mm;
    --offset-left: {{ $offsetLeft }}mm;
  }

  .sheet-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
  }

  .sheet-meta-bar {
    width: 210mm;
    max-width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-dark);
    padding: 0 4px;
    margin-bottom: -16px;
  }

  .a4-sheet {
    width: 210mm;
    height: 297mm;
    background: #ffffff;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.12);
    border-radius: 6px;
    padding: calc(6mm + var(--offset-top)) 6mm 6mm calc(6mm + var(--offset-left));
    overflow: hidden;
    display: grid;
    box-sizing: border-box;
  }

  .a4-sheet.layout-a4-40 {
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: repeat(10, 1fr);
    gap: 1.2mm;
  }
  .a4-sheet.layout-a4-30 {
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(10, 1fr);
    gap: 1.5mm;
  }
  .a4-sheet.layout-a4-24 {
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(8, 1fr);
    gap: 2mm;
  }
  .a4-sheet.layout-a4-21 {
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(7, 1fr);
    gap: 2mm;
  }
  .a4-sheet.layout-a4-65 {
    grid-template-columns: repeat(5, 1fr);
    grid-template-rows: repeat(13, 1fr);
    gap: 0.8mm;
  }
  .a4-sheet.layout-a4-10 {
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(5, 1fr);
    gap: 3mm;
  }

  .thermal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(54mm, 1fr));
    gap: 4mm;
    width: 100%;
  }

  .sticker-card {
    background: #ffffff;
    display: flex;
    align-items: center;
    gap: 2mm;
    padding: 1.5mm 2mm;
    overflow: hidden;
    box-sizing: border-box;
    min-width: 0;
    min-height: 0;
  }

  .sticker-card.border-dashed { border: 0.35mm dashed #94a3b8; border-radius: 1.5mm; }
  .sticker-card.border-solid { border: 0.4mm solid #1e40af; border-radius: 2mm; }
  .sticker-card.border-none { border: 0.25mm dotted transparent; }

  .sticker-card.is-blank {
    background: repeating-linear-gradient(45deg, #f8fafc, #f8fafc 3px, #f1f5f9 3px, #f1f5f9 6px);
    border-color: #cbd5e1;
  }

  .sticker-qr {
    flex-shrink: 0;
    width: var(--qr-mm);
    height: var(--qr-mm);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .sticker-qr svg {
    width: var(--qr-mm) !important;
    height: var(--qr-mm) !important;
    display: block;
  }

  .sticker-info {
    flex: 1;
    min-width: 0;
    line-height: 1.2;
    overflow: hidden;
  }

  .sticker-org {
    font-weight: 700;
    color: #1e40af;
    font-size: 7.5pt;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .sticker-code {
    font-weight: 700;
    font-size: 8.5pt;
    color: #0f172a;
    margin: 0.5mm 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .sticker-name {
    font-size: 7.5pt;
    color: #1e293b;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .sticker-resp {
    font-size: 6.8pt;
    color: #475569;
    margin-top: 0.5mm;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* Compact adjustment for 65-per-sheet */
  .layout-a4-65 .sticker-card { padding: 0.8mm 1mm; gap: 1.2mm; }
  .layout-a4-65 .sticker-org { font-size: 6pt; }
  .layout-a4-65 .sticker-code { font-size: 7pt; margin: 0; }
  .layout-a4-65 .sticker-name { font-size: 6.2pt; -webkit-line-clamp: 1; }
  .layout-a4-65 .sticker-resp { font-size: 5.8pt; }

  /* Large adjustment for 10-per-sheet */
  .layout-a4-10 .sticker-card { padding: 3mm 4mm; gap: 4mm; }
  .layout-a4-10 .sticker-org { font-size: 10pt; }
  .layout-a4-10 .sticker-code { font-size: 12pt; }
  .layout-a4-10 .sticker-name { font-size: 10pt; -webkit-line-clamp: 3; }
  .layout-a4-10 .sticker-resp { font-size: 9pt; }

  @media screen and (max-width: 860px) {
    .a4-sheet {
      width: 100%;
      height: auto;
      aspect-ratio: 210 / 297;
    }
  }

  @media print {
    @page {
      size: A4 portrait;
      margin: 0;
    }
    html, body {
      background: #ffffff !important;
      margin: 0 !important;
      padding: 0 !important;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .sheet-wrapper {
      display: block !important;
      gap: 0 !important;
    }
    .sheet-meta-bar { display: none !important; }
    .a4-sheet {
      width: 210mm !important;
      height: 297mm !important;
      box-shadow: none !important;
      border-radius: 0 !important;
      margin: 0 !important;
      break-after: page;
      page-break-after: always;
    }
    .a4-sheet:last-child {
      break-after: auto;
      page-break-after: auto;
    }
    .sticker-card.is-blank {
      background: transparent !important;
      border-color: transparent !important;
    }
    .sticker-card.border-none {
      border-color: transparent !important;
    }
    .thermal-grid {
      display: block !important;
    }
    .thermal-grid .sticker-card {
      width: 52mm;
      height: 30mm;
      break-after: page;
      page-break-after: always;
    }
  }
</style>
@endpush

@section('content')
  <div class="panel no-print">
    <div class="section-title">
      <div class="title-left">
        <i class="fa-solid fa-print"></i>
        <span>พิมพ์ป้ายสติกเกอร์ QR Code ทั้งหมด ({{ number_format($assets->count()) }} ดวง • {{ count($pages) }} แผ่น)</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('qr.print') }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-rotate-left"></i> รีเซ็ตเป็นทั้งหมด
        </a>
        <button type="button" onclick="window.print()" class="btn btn-primary">
          <i class="fa-solid fa-print"></i> สั่งพิมพ์ลงกระดาษสติ๊กเกอร์ ({{ number_format($assets->count()) }} ดวง)
        </button>
      </div>
    </div>

    <form method="GET" action="{{ route('qr.print') }}" class="filter-bar">
      <div class="form-group">
        <label class="form-label">รูปแบบกระดาษสติ๊กเกอร์</label>
        <select name="layout" class="form-select">
          @foreach($layouts as $key => $meta)
            <option value="{{ $key }}" {{ $layout === $key ? 'selected' : '' }}>{{ $meta['label'] }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ขนาด QR Code</label>
        <select name="qr_mm" class="form-select">
          <option value="12" {{ $qrMm === 12 ? 'selected' : '' }}>1.2 × 1.2 ซม. (เล็กพิเศษ)</option>
          <option value="15" {{ $qrMm === 15 ? 'selected' : '' }}>1.5 × 1.5 ซม. (แนะนำ)</option>
          <option value="18" {{ $qrMm === 18 ? 'selected' : '' }}>1.8 × 1.8 ซม. (กลาง)</option>
          <option value="20" {{ $qrMm === 20 ? 'selected' : '' }}>2.0 × 2.0 ซม. (ใหญ่)</option>
          <option value="25" {{ $qrMm === 25 ? 'selected' : '' }}>2.5 × 2.5 ซม. (ใหญ่มาก)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">เส้นขอบ / รอยตัด</label>
        <select name="border_mode" class="form-select">
          <option value="dashed" {{ $borderMode === 'dashed' ? 'selected' : '' }}>เส้นประ (สำหรับตัดเอง)</option>
          <option value="solid" {{ $borderMode === 'solid' ? 'selected' : '' }}>กรอบสีน้ำเงินชัดเจน</option>
          <option value="none" {{ $borderMode === 'none' ? 'selected' : '' }}>ไม่มีเส้นขอบ (สติ๊กเกอร์ไดคัท)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">สี QR Code</label>
        <select name="pure_black" class="form-select">
          <option value="1" {{ $pureBlack ? 'selected' : '' }}>สีดำคมชัด (เหมาะกับสติ๊กเกอร์)</option>
          <option value="0" {{ !$pureBlack ? 'selected' : '' }}>สีน้ำเงินกรมท่า</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ชุดข้อมูล</label>
        <select name="scope" class="form-select">
          <option value="approved" {{ $scope === 'approved' ? 'selected' : '' }}>เฉพาะสำนักปลัด (194 รายการ)</option>
          <option value="all_with_pending" {{ $scope === 'all_with_pending' ? 'selected' : '' }}>รวมรอตรวจสอบด้วย (317 รายการ)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ปีงบประมาณ</label>
        <select name="fiscal_year" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="2568" {{ $fiscalYear == 2568 ? 'selected' : '' }}>ปี 2568</option>
          <option value="2569" {{ $fiscalYear == 2569 ? 'selected' : '' }}>ปี 2569</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ผู้รับผิดชอบ / ตำแหน่ง</label>
        <select name="responsible_person" class="form-select">
          <option value="">ทั้งหมด</option>
          @foreach($responsibles as $r)
            <option value="{{ $r }}" {{ $responsible === $r ? 'selected' : '' }}>{{ $r }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">หมวดหมู่</label>
        <select name="category" class="form-select">
          <option value="">ทั้งหมด</option>
          @foreach($categories as $c)
            <option value="{{ $c }}" {{ $category === $c ? 'selected' : '' }}>{{ $c }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ค้นหารหัส / ชื่อ</label>
        <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="เช่น 400-42 หรือ โต๊ะ">
      </div>

      <div class="form-group">
        <label class="form-label">ช่วงลำดับ (เริ่ม - ถึง)</label>
        <div style="display:flex;gap:6px;">
          <input type="number" min="1" name="seq_from" class="form-control" value="{{ $seqFrom }}" placeholder="1">
          <input type="number" min="1" name="seq_to" class="form-control" value="{{ $seqTo }}" placeholder="194">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">เว้นช่องว่างเริ่มต้น (ดวง)</label>
        <input type="number" min="0" max="64" name="skip" class="form-control" value="{{ $skip }}" title="กรณีใช้กระดาษสติ๊กเกอร์ที่เหลือจากแผ่นก่อน">
      </div>

      <div class="form-group">
        <label class="form-label">จูนระยะขอบพิมพ์ บน / ซ้าย (มม.)</label>
        <div style="display:flex;gap:6px;">
          <input type="number" step="0.5" min="-15" max="15" name="offset_top" class="form-control" value="{{ $offsetTop }}" title="ขยับบน-ล่าง (มม.)">
          <input type="number" step="0.5" min="-15" max="15" name="offset_left" class="form-control" value="{{ $offsetLeft }}" title="ขยับซ้าย-ขวา (มม.)">
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center;">
        <button type="submit" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-sliders"></i> อัปเดตแบบพิมพ์
        </button>
      </div>
    </form>

    <div class="alert alert-success" style="margin-bottom:0;font-size:13px;">
      <i class="fa-solid fa-lightbulb"></i>
      <span>
        <strong>คำแนะนำการพิมพ์ลงกระดาษสติ๊กเกอร์ A4:</strong>
        ขนาด QR ปัจจุบันตั้งไว้ที่ <strong>{{ number_format($qrMm / 10, 1) }} × {{ number_format($qrMm / 10, 1) }} ซม.</strong> •
        ตอนสั่งพิมพ์ใน Chrome/Edge ให้ตั้ง <strong>Scale = 100% (Actual size)</strong> และ <strong>Margins = None</strong> เพื่อให้ขนาด {{ number_format($qrMm / 10, 1) }} ซม. ตรงเป๊ะกับสติ๊กเกอร์จริง
      </span>
    </div>
  </div>

  @if($assets->isEmpty())
    <div class="panel" style="text-align:center;color:var(--text-light);padding:40px;">
      ไม่พบรายการครุภัณฑ์ตามเงื่อนไขที่เลือก
    </div>
  @elseif($layout === 'thermal')
    <div class="thermal-grid">
      @foreach($assets as $asset)
        <div class="sticker-card border-{{ $borderMode }}">
          <div class="sticker-qr">{!! $qrSvgs[$asset->id] !!}</div>
          <div class="sticker-info">
            <div class="sticker-org">อบต.ฝางคำ • สำนักปลัด</div>
            <div class="sticker-code">{{ $asset->displayCode() }}</div>
            <div class="sticker-name">{{ $asset->name }}</div>
            <div class="sticker-resp">{{ $asset->qr_token }} • {{ $asset->responsible_person ?? '-' }}</div>
          </div>
        </div>
      @endforeach
    </div>
  @else
    <div class="sheet-wrapper">
      @foreach($pages as $pageIdx => $pageSlots)
        @php
          $filledInPage = collect($pageSlots)->filter()->count();
        @endphp
        <div class="sheet-meta-bar no-print">
          <span><i class="fa-regular fa-file-lines"></i> แผ่นที่ {{ $pageIdx + 1 }} / {{ count($pages) }} ({{ $layoutMeta['label'] }})</span>
          <span>จำนวน {{ $filledInPage }} ป้ายในแผ่นนี้</span>
        </div>

        <div class="a4-sheet layout-{{ $layout }}">
          @foreach($pageSlots as $slotAsset)
            @if($slotAsset === null)
              <div class="sticker-card border-{{ $borderMode }} is-blank"></div>
            @else
              <div class="sticker-card border-{{ $borderMode }}">
                <div class="sticker-qr">{!! $qrSvgs[$slotAsset->id] !!}</div>
                <div class="sticker-info">
                  <div class="sticker-org">อบต.ฝางคำ • สำนักปลัด</div>
                  <div class="sticker-code">{{ $slotAsset->displayCode() }}</div>
                  <div class="sticker-name">{{ $slotAsset->name }}</div>
                  <div class="sticker-resp">{{ $slotAsset->qr_token }} • {{ $slotAsset->responsible_person ?? '-' }}</div>
                </div>
              </div>
            @endif
          @endforeach
        </div>
      @endforeach
    </div>
  @endif
@endsection
