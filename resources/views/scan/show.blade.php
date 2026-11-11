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
            <div style="font-size:14px;color:var(--text-light);margin-bottom:8px;">
              รหัสพัสดุ: <strong>{{ $asset->asset_code ?? 'ไม่มีรหัสพัสดุ' }}</strong>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
              <span class="badge badge-blue">
                <i class="fa-solid fa-user"></i> ผู้รับผิดชอบ: {{ $asset->responsible_person ?? 'ยังไม่ระบุ' }}
              </span>
              <span class="badge" style="background:#ede9fe;color:#6d28d9;">
                <i class="fa-solid fa-id-badge"></i> ตำแหน่ง: {{ $asset->position_title ?? 'ยังไม่ระบุตำแหน่ง' }}
              </span>
              <span class="badge badge-muted">
                <i class="fa-solid fa-location-dot"></i> {{ $asset->location ?? 'ยังไม่ระบุสถานที่' }}
              </span>
            </div>
          </div>
        </div>

        @if($asset->photo_path)
          <div style="margin-bottom:16px;text-align:center;">
            <img src="{{ asset('storage/' . $asset->photo_path) }}" alt="{{ $asset->name }}"
                 style="max-height:220px;max-width:100%;border-radius:12px;border:1px solid var(--border);">
          </div>
        @endif

        <table>
          <tbody>
            <tr><th style="width:170px;">ผู้รับผิดชอบ</th><td><strong>{{ $asset->responsible_person ?? '-' }}</strong></td></tr>
            <tr><th>ตำแหน่ง</th><td><strong>{{ $asset->position_title ?? '-' }}</strong></td></tr>
            <tr><th>สถานที่ตั้ง</th><td>{{ $asset->location ?? '-' }}</td></tr>
            <tr><th>รายละเอียด/ยี่ห้อ</th><td>{{ $asset->specification ?? '-' }}</td></tr>
            <tr><th>หมวดหมู่</th><td>{{ $asset->category ?? '-' }}</td></tr>
            <tr><th>วันที่ได้มา</th><td>{{ $asset->acquired_date_text ?? '-' }}</td></tr>
            <tr><th>ราคา</th><td><strong>{{ number_format((float)$asset->price, 2) }} บาท</strong> ({{ $asset->quantity }} {{ $asset->unit }})</td></tr>
            <tr><th>หมายเหตุ</th><td>{{ $asset->note ?? '-' }}</td></tr>
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
            <a href="{{ route('assets.edit', ['asset' => $asset, 'return_to' => 'scan']) }}" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลครุภัณฑ์ทั้งหมด
            </a>
            <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline btn-sm">
              <i class="fa-solid fa-box-open"></i> ดูประวัติเต็ม / แจ้งซ่อม
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
          <h3><i class="fa-solid fa-clipboard-check"></i> บันทึกตรวจเช็ค &amp; อัปเดตข้อมูล</h3>
          <p>ระบุผู้รับผิดชอบ ตำแหน่ง สถานที่ และสภาพที่พบ</p>
        </div>

        <form method="POST" action="{{ route('scan.check', ['token' => $asset->qr_token]) }}" enctype="multipart/form-data">
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
            <label class="form-label">ผู้รับผิดชอบ (ชื่อผู้ถือครอง / หน่วยงาน)</label>
            <input type="text" name="actual_responsible" list="scanRespList" class="form-control"
                   value="{{ old('actual_responsible', $asset->responsible_person) }}"
                   placeholder="เช่น นายสมชาย ใจดี หรือ สำนักปลัด">
            <datalist id="scanRespList">
              @foreach($responsibles as $r)<option value="{{ $r }}">@endforeach
            </datalist>
          </div>

          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">ตำแหน่ง</label>
            <input type="text" name="actual_position" list="scanPosList" class="form-control"
                   value="{{ old('actual_position', $asset->position_title) }}"
                   placeholder="เช่น หัวหน้าสำนักปลัด, นักทรัพยากรบุคคล">
            <datalist id="scanPosList">
              @foreach($positions as $p)<option value="{{ $p }}">@endforeach
            </datalist>
          </div>

          <div class="form-group" style="margin-bottom:10px;">
            <label class="form-label">สถานที่ที่ตรวจพบ / ห้อง</label>
            <input type="text" name="location_found" class="form-control"
                   value="{{ old('location_found', $asset->location) }}"
                   placeholder="เช่น ห้องสำนักปลัด ชั้น 1">
          </div>

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
            <label class="form-label">ถ่ายรูป / แนบภาพครุภัณฑ์ (ถ้ามี)</label>
            <input type="file" name="photo" class="form-control" accept="image/*" capture="environment">
          </div>

          <div class="form-group" style="margin-bottom:14px;">
            <label class="form-label">หมายเหตุ</label>
            <textarea name="note" class="form-control" rows="2" placeholder="ระบุสภาพหรือรายละเอียดเพิ่มเติม"></textarea>
          </div>

          <button type="submit" class="btn btn-success" style="width:100%;">
            <i class="fa-solid fa-circle-check"></i> บันทึกข้อมูล &amp; ยืนยันการตรวจเช็ค
          </button>
        </form>
      @else
        <div class="qr-panel-header">
          <h3><i class="fa-solid fa-lock"></i> สำหรับเจ้าหน้าที่</h3>
          <p>เข้าสู่ระบบเพื่อบันทึกตรวจเช็คสต็อกหรือแก้ไขข้อมูล</p>
        </div>
        <a href="{{ route('login') }}" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบเพื่อตรวจเช็ค
        </a>
      @endauth
    </div>
  </div>
@endsection
