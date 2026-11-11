@extends('layouts.app')

@section('title', 'รอบตรวจนับสต็อกประจำปี | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="two-col-grid">
    <div class="panel">
      <div class="section-title">
        <div class="title-left"><i class="fa-solid fa-clipboard-check"></i> รอบการตรวจนับครุภัณฑ์ทั้งหมด</div>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;">
        @forelse($stockChecks as $sc)
          @php
            $pct = $totalApproved > 0 ? round(($sc->checked_count / $totalApproved) * 100, 1) : 0;
          @endphp
          <div style="border:1.5px solid var(--border);border-radius:14px;padding:18px;background:#f8fafc;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
              <div>
                <div style="font-size:12.5px;color:var(--primary);font-weight:700;">{{ $sc->code }} • ปีงบประมาณ {{ $sc->fiscal_year }}</div>
                <h3 style="font-size:17px;">{{ $sc->title }}</h3>
                @if($sc->note)
                  <p style="font-size:13px;color:var(--text-light);">{{ $sc->note }}</p>
                @endif
              </div>
              <span class="badge {{ $sc->isOpen() ? 'badge-ok' : 'badge-muted' }}">
                {{ $sc->isOpen() ? 'กำลังตรวจนับ' : 'ปิดรอบแล้ว' }}
              </span>
            </div>

            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
              <span>ตรวจแล้ว <strong>{{ number_format($sc->checked_count) }}</strong> / {{ number_format($totalApproved) }} รายการ (ปกติ {{ $sc->usable_count }} • พบปัญหา {{ $sc->issue_count }})</span>
              <strong>{{ $pct }}%</strong>
            </div>
            <div class="progress-wrap" style="margin-bottom:12px;">
              <div class="progress-bar" style="width:{{ $pct }}%;"></div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <a href="{{ route('stock-checks.show', $sc) }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-qrcode"></i> เปิดหน้าตรวจนับ &amp; สแกน
              </a>
              <a href="{{ route('stock-checks.export', $sc) }}" class="btn btn-outline btn-sm">
                <i class="fa-solid fa-file-excel" style="color:var(--success);"></i> ส่งออก Excel
              </a>
              <form method="POST" action="{{ route('stock-checks.toggle', $sc) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline btn-sm">
                  <i class="fa-solid {{ $sc->isOpen() ? 'fa-lock' : 'fa-lock-open' }}"></i>
                  {{ $sc->isOpen() ? 'ปิดรอบตรวจนับ' : 'เปิดรอบอีกครั้ง' }}
                </button>
              </form>
            </div>
          </div>
        @empty
          <p style="color:var(--text-light);">ยังไม่มีรอบตรวจนับ</p>
        @endforelse
      </div>
    </div>

    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-plus-circle"></i> เปิดรอบตรวจนับใหม่</h3>
        <p>สร้างรอบตรวจนับประจำปีหรือตรวจเช็คเฉพาะกิจ</p>
      </div>

      <form method="POST" action="{{ route('stock-checks.store') }}">
        @csrf
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ชื่อรอบตรวจนับ *</label>
          <input type="text" name="title" class="form-control" required placeholder="เช่น ตรวจนับครุภัณฑ์ รอบ 2/2569">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ปีงบประมาณ *</label>
          <input type="number" name="fiscal_year" class="form-control" required value="2569">
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">วันที่เริ่ม</label>
          <input type="date" name="started_at" class="form-control" value="{{ now()->toDateString() }}">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">หมายเหตุ</label>
          <textarea name="note" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-plus"></i> สร้างรอบตรวจนับ
        </button>
      </form>
    </div>
  </div>
@endsection
