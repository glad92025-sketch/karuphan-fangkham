@extends('layouts.app')

@section('title', 'ข้อมูล QR: ' . $asset->displayCode())

@section('content')
  <div class="two-col-grid">
    <div>
      <div class="panel">
        <div class="section-title">
          <div class="title-left">
            <i class="fa-solid fa-qrcode"></i>
            <span>ข้อมูลครุภัณฑ์จากการสแกน QR</span>
          </div>
          <span class="badge {{ $asset->badgeClass() }}">{{ $asset->statusLabel() }}</span>
        </div>

        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:center;margin-bottom:18px;padding:16px;background:#f8fafc;border-radius:14px;border:1px solid var(--border);">
          <div class="qr-svg-wrap">{!! $qrSvg !!}</div>
          <div style="flex:1;min-width:220px;">
            <div style="font-size:13px;color:var(--primary);font-weight:700;">{{ $asset->qr_token }} • ปีงบประมาณ {{ $asset->fiscal_year }}</div>
            <h2 style="font-size:20px;margin:4px 0;">{{ $asset->name }}</h2>
            <div style="font-size:14px;color:var(--text-light);margin-bottom:6px;">
              รหัสพัสดุ: <strong>{{ $asset->asset_code ?? 'ไม่มีรหัสพัสดุ' }}</strong>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <span class="badge badge-blue">{{ $asset->responsible_person ?? 'ยังไม่ระบุผู้รับผิดชอบ' }}</span>
              @if($asset->location)
                <span class="badge badge-muted">{{ $asset->location }}</span>
              @endif
            </div>
          </div>
        </div>

        <table>
          <tbody>
            <tr><th style="width:170px;">รายละเอียด/ยี่ห้อ</th><td>{{ $asset->specification ?? '-' }}</td></tr>
            <tr><th>หมวดหมู่</th><td>{{ $asset->category ?? '-' }}</td></tr>
            <tr><th>วันที่ได้มา</th><td>{{ $asset->acquired_date_text ?? '-' }}</td></tr>
            <tr><th>ราคา</th><td><strong>{{ number_format((float)$asset->price, 2) }} บาท</strong></td></tr>
            <tr>
              <th>ตรวจเช็คล่าสุด</th>
              <td>
                @if($asset->last_checked_at)
                  {{ $asset->last_checked_at->format('d/m/Y H:i') }} โดย {{ $asset->lastChecker?->name ?? '-' }}
                @else
                  <span style="color:var(--text-light);">ยังไม่เคยบันทึกการตรวจเช็ค</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>

        @auth
          <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline btn-sm">
              <i class="fa-solid fa-box-open"></i> ดูประวัติเต็ม / แก้ไข
            </a>
            <a href="{{ route('scan.index') }}" class="btn btn-outline btn-sm">
              <i class="fa-solid fa-qrcode"></i> สแกนรายการอื่นต่อ
            </a>
          </div>
        @endauth
      </div>

      @if($asset->inspections->isNotEmpty())
      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติการตรวจเช็คล่าสุด</div></div>
        <div class="table-responsive">
          <table>
            <thead><tr><th>วันเวลา</th><th>สภาพ</th><th>สถานที่</th><th>ผู้ตรวจ</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
              @foreach($asset->inspections->take(5) as $insp)
                <tr>
                  <td style="white-space:nowrap;">{{ $insp->inspected_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $insp->conditionLabel() }}</td>
                  <td>{{ $insp->location_found ?? '-' }}</td>
                  <td>{{ $insp->inspector_name ?? '-' }}</td>
                  <td>{{ $insp->note ?? '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>

    <!-- Right: Action Panel -->
    <div class="qr-panel">
      @auth
        <div class="qr-panel-header">
          <h3><i class="fa-solid fa-clipboard-check"></i> บันทึกตรวจเช็คสต็อก</h3>
          <p>อัปเดตสภาพและยืนยันการตรวจพบครุภัณฑ์</p>
        </div>

        <form method="POST" action="{{ route('scan.check', ['token' => $asset->qr_token]) }}">
          @csrf
          <input type="hidden" name="scan_method" value="qr">

          @if($openStockChecks->isNotEmpty())
          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">บันทึกเข้าในรอบตรวจนับ</label>
            <select name="stock_check_id" class="form-select">
              <option value="">-- ตรวจเช็คทั่วไป (ไม่ผูกกับรอบ) --</option>
              @foreach($openStockChecks as $sc)
                <option value="{{ $sc->id }}" selected>{{ $sc->code }} - {{ $sc->title }}</option>
              @endforeach
            </select>
          </div>
          @endif

          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">สภาพครุภัณฑ์ที่ตรวจพบ *</label>
            <select name="condition_status" class="form-select" required>
              <option value="usable" {{ $asset->status === 'usable' ? 'selected' : '' }}>ใช้งานได้ปกติ</option>
              <option value="repair" {{ $asset->status === 'repair' ? 'selected' : '' }}>พบชำรุด / ส่งซ่อม</option>
              <option value="damaged" {{ $asset->status === 'damaged' ? 'selected' : '' }}>ชำรุด / ใช้ไม่ได้</option>
              <option value="lost" {{ $asset->status === 'lost' ? 'selected' : '' }}>ไม่พบ / สูญหาย</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">ผู้รับผิดชอบ / ตำแหน่งที่พบ</label>
            <input type="text" name="actual_responsible" list="scanRespList" class="form-control" value="{{ $asset->responsible_person }}">
            <datalist id="scanRespList">
              @foreach($responsibles as $r)<option value="{{ $r }}">@endforeach
            </datalist>
          </div>

          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">สถานที่ที่ตรวจพบ</label>
            <input type="text" name="location_found" class="form-control" value="{{ $asset->location }}">
          </div>

          <div class="form-group" style="margin-bottom:14px;">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="note" class="form-control" rows="2" placeholder="ระบุสภาพหรือจุดที่ตั้งจริง"></textarea>
          </div>

          <button type="submit" class="btn btn-success" style="width:100%;">
            <i class="fa-solid fa-circle-check"></i> ยืนยันการตรวจเช็ค
          </button>
        </form>
      @else
        <div class="qr-panel-header">
          <h3><i class="fa-solid fa-lock"></i> สำหรับเจ้าหน้าที่</h3>
          <p>เข้าสู่ระบบเพื่อบันทึกตรวจเช็คสต็อกหรือแจ้งซ่อม</p>
        </div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบเพื่อตรวจเช็ค
        </a>
      @endauth
    </div>
  </div>
@endsection
