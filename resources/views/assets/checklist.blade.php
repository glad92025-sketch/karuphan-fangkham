@extends('layouts.app')

@section('title', 'พิมพ์ใบรายการครุภัณฑ์ทั้งหมดสำหรับติด QR Code | สำนักปลัด อบต.ฝางคำ')

@push('styles')
<style>
  .checklist-header {
    text-align: center;
    margin-bottom: 12px;
  }
  .checklist-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 2px;
  }
  .checklist-header p {
    font-size: 13px;
    color: #475569;
  }

  .checklist-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
    background: white;
  }
  .checklist-table th, .checklist-table td {
    border: 1px solid #94a3b8;
    padding: 6px 8px;
    vertical-align: middle;
  }
  .checklist-table th {
    background: #eff6ff;
    color: #1e3a8a;
    font-weight: 700;
    text-align: center;
    border-radius: 0 !important;
  }
  .checklist-table tr {
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .chk-box {
    width: 15px;
    height: 15px;
    border: 1.5px solid #334155;
    display: inline-block;
    border-radius: 3px;
  }
  .mini-qr svg {
    width: 13mm !important;
    height: 13mm !important;
    display: block;
    margin: 0 auto;
  }
  .write-line {
    border-bottom: 1px dotted #94a3b8;
    min-height: 18px;
    margin-top: 2px;
  }

  @media print {
    @page {
      size: A4 landscape;
      margin: 8mm;
    }
    .checklist-table { font-size: 11px; }
    .checklist-table thead { display: table-header-group; }
    .checklist-table th { background: #eff6ff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .checklist-table th, .checklist-table td { padding: 4px 6px; border: 1px solid #64748b; }
  }
</style>
@endpush

@section('content')
  <div class="panel no-print">
    <div class="section-title">
      <div class="title-left">
        <i class="fa-solid fa-list-check"></i>
        <span>ใบพิมพ์รายการครุภัณฑ์ทั้งหมดสำหรับเดินติดสติกเกอร์ QR &amp; จดชื่อผู้รับผิดชอบ ({{ number_format($assets->count()) }} รายการ)</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('assets.bulk-edit', request()->query()) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-table-cells"></i> ไปหน้าคีย์ข้อมูลแบบตาราง
        </a>
        <a href="{{ route('qr.print', request()->query()) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-qrcode"></i> พิมพ์สติกเกอร์ QR (1.5×1.5 ซม.)
        </a>
        <button type="button" onclick="window.print()" class="btn btn-primary">
          <i class="fa-solid fa-print"></i> สั่งพิมพ์ใบรายการ ({{ number_format($assets->count()) }} รายการ)
        </button>
      </div>
    </div>

    <form method="GET" action="{{ route('assets.checklist') }}" class="filter-bar">
      <div class="form-group">
        <label class="form-label">ชุดข้อมูล</label>
        <select name="review_status" class="form-select">
          <option value="approved" {{ $reviewStatus === 'approved' ? 'selected' : '' }}>เฉพาะสำนักปลัด (194 รายการ)</option>
          <option value="pending" {{ $reviewStatus === 'pending' ? 'selected' : '' }}>รอจัดสรรตำแหน่ง (123 รายการ)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ปีงบประมาณ</label>
        <select name="fiscal_year" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="2568" {{ request('fiscal_year') == '2568' ? 'selected' : '' }}>ปี 2568</option>
          <option value="2569" {{ request('fiscal_year') == '2569' ? 'selected' : '' }}>ปี 2569</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">หมวดหมู่</label>
        <select name="category" class="form-select">
          <option value="">ทุกหมวดหมู่</option>
          @foreach($categories as $c)
            <option value="{{ $c }}" {{ request('category') === $c ? 'selected' : '' }}>{{ $c }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ผู้รับผิดชอบ (เดิม)</label>
        <select name="responsible_person" class="form-select">
          <option value="">ทั้งหมด</option>
          @foreach($responsibles as $r)
            <option value="{{ $r }}" {{ request('responsible_person') === $r ? 'selected' : '' }}>{{ $r }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">รูป QR ในตาราง</label>
        <select name="with_qr" class="form-select">
          <option value="1" {{ $withQr ? 'selected' : '' }}>แสดงรูป QR จิ๋วคู่รายการ (ช่วยเทียบดวงสติกเกอร์)</option>
          <option value="0" {{ !$withQr ? 'selected' : '' }}>ไม่แสดงรูป QR (ประหยัดหน้ากระดาษ)</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm">กรองรายการ</button>
        <a href="{{ route('assets.checklist') }}" class="btn btn-outline btn-sm">รีเซ็ต</a>
      </div>
    </form>
  </div>

  <div class="panel" style="padding:18px;">
    <div class="checklist-header">
      <h2>ทะเบียนรายการครุภัณฑ์สำหรับติดป้าย QR Code และบันทึกผู้รับผิดชอบ / ตำแหน่ง</h2>
      <p>
        องค์การบริหารส่วนตำบลฝางคำ (สำนักปลัด) •
        ทั้งหมด <strong>{{ number_format($assets->count()) }}</strong> รายการ •
        มูลค่ารวม <strong>{{ number_format($totalSum, 2) }}</strong> บาท •
        พิมพ์วันที่ {{ now()->format('d/m/') . (now()->year + 543) }}
      </p>
    </div>

    <table class="checklist-table">
      <thead>
        <tr>
          <th style="width:42px;">ลำดับ</th>
          @if($withQr)
            <th style="width:58px;">QR</th>
          @endif
          <th style="width:110px;">รหัส QR / พัสดุ</th>
          <th>ชื่อพัสดุ / ยี่ห้อ / ลักษณะ</th>
          <th style="width:130px;">ข้อมูลเดิมในทะเบียน</th>
          <th style="width:52px;">ติด QR</th>
          <th style="width:165px;">ผู้รับผิดชอบจริง (เขียน)</th>
          <th style="width:145px;">ตำแหน่ง (เขียน)</th>
          <th style="width:135px;">สถานที่ตั้ง / หมายเหตุ</th>
        </tr>
      </thead>
      <tbody>
        @foreach($assets as $idx => $asset)
          <tr>
            <td style="text-align:center;font-weight:700;">{{ $idx + 1 }}</td>
            @if($withQr)
              <td style="text-align:center;"><div class="mini-qr">{!! $qrSvgs[$asset->id] !!}</div></td>
            @endif
            <td style="white-space:nowrap;">
              <div style="font-weight:700;color:#1e3a8a;">{{ $asset->qr_token }}</div>
              <div>{{ $asset->asset_code ?? '(ไม่มีรหัส)' }}</div>
            </td>
            <td>
              <div style="font-weight:700;">{{ $asset->name }}</div>
              @if($asset->specification && $asset->specification !== $asset->name)
                <div style="font-size:11px;color:#475569;">{{ Str::limit($asset->specification, 85) }}</div>
              @endif
              <div style="font-size:10.5px;color:#64748b;">ราคา {{ number_format((float)$asset->price, 2) }} บ. • {{ $asset->category ?? '-' }}</div>
            </td>
            <td>
              <div>{{ $asset->responsible_person ?? '-' }}</div>
              @if($asset->position_title)
                <div style="font-size:11px;color:#6d28d9;font-weight:600;">{{ $asset->position_title }}</div>
              @endif
            </td>
            <td style="text-align:center;"><span class="chk-box"></span></td>
            <td><div class="write-line"></div></td>
            <td><div class="write-line"></div></td>
            <td><div class="write-line"></div></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection
