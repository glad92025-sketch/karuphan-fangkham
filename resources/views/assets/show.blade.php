@extends('layouts.app')

@section('title', $asset->displayCode() . ' - ' . $asset->name)

@section('content')
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <a href="{{ route('assets.index', ['review_status' => $asset->review_status]) }}" class="btn btn-outline btn-sm">
      <i class="fa-solid fa-arrow-left"></i> กลับรายการครุภัณฑ์
    </a>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="{{ route('scan.show', ['token' => $asset->qr_token]) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-mobile-screen"></i> หน้าสแกน QR
      </a>
      <a href="{{ route('qr.print', ['ids' => [$asset->id], 'layout' => 'grid-10']) }}" target="_blank" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-print"></i> พิมพ์สติกเกอร์
      </a>
      <a href="{{ route('assets.edit', $asset) }}" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-pen"></i> แก้ไขข้อมูล
      </a>
      @if(auth()->user()->isAdmin())
        <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('ยืนยันลบครุภัณฑ์รายการนี้?');">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
        </form>
      @endif
    </div>
  </div>

  <div class="two-col-grid">
    <!-- Left: Details & History -->
    <div>
      <div class="panel">
        <div class="section-title">
          <div class="title-left">
            <i class="fa-solid fa-box-open"></i>
            <span>{{ $asset->name }}</span>
          </div>
          <span class="badge {{ $asset->badgeClass() }}">{{ $asset->statusLabel() }}</span>
        </div>

        @if($asset->photo_path)
          <div style="margin-bottom:16px;">
            <img src="{{ asset('storage/' . $asset->photo_path) }}" alt="{{ $asset->name }}"
                 style="max-height:240px;border-radius:12px;border:1px solid var(--border);">
          </div>
        @endif

        <table>
          <tbody>
            <tr><th style="width:200px;">รหัส QR ติดตาม</th><td><strong>{{ $asset->qr_token }}</strong></td></tr>
            <tr><th>รหัสพัสดุ</th><td><strong>{{ $asset->asset_code ?? 'ไม่มีรหัสพัสดุ' }}</strong></td></tr>
            <tr><th>ยี่ห้อ/แบบ/ชนิด/ลักษณะ</th><td>{{ $asset->specification ?? '-' }}</td></tr>
            <tr><th>หมวดหมู่</th><td>{{ $asset->category ?? '-' }}</td></tr>
            <tr><th>วันที่ได้มา / ปีงบประมาณ</th><td>{{ $asset->acquired_date_text ?? '-' }} (ปีงบประมาณ {{ $asset->fiscal_year }})</td></tr>
            <tr><th>ราคา</th><td><strong>{{ number_format((float)$asset->price, 2) }} บาท</strong> ({{ $asset->quantity }} {{ $asset->unit }})</td></tr>
            <tr><th>ผู้รับผิดชอบ</th><td><span class="badge badge-blue">{{ $asset->responsible_person ?? 'ยังไม่ระบุ' }}</span></td></tr>
            <tr><th>ตำแหน่ง</th><td><span class="badge" style="background:#ede9fe;color:#6d28d9;">{{ $asset->position_title ?? 'ยังไม่ระบุตำแหน่ง' }}</span></td></tr>
            <tr><th>สถานที่ตั้ง</th><td>{{ $asset->location ?? '-' }}</td></tr>
            <tr><th>หมายเหตุ</th><td>{{ $asset->note ?? '-' }}</td></tr>
            <tr>
              <th>ตรวจเช็คสต็อกล่าสุด</th>
              <td>
                @if($asset->last_checked_at)
                  {{ $asset->last_checked_at->format('d/m/Y H:i') }} โดย {{ $asset->lastChecker?->name ?? '-' }}
                @else
                  <span style="color:var(--text-light);">ยังไม่มีประวัติตรวจเช็คผ่านระบบ</span>
                @endif
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Quick Actions Tabs -->
      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-bolt"></i> บันทึกความเคลื่อนไหว / ตรวจเช็ค / แจ้งซ่อม</div></div>
        <div class="form-grid">
          <!-- Check-in -->
          <form method="POST" action="{{ route('scan.check', ['token' => $asset->qr_token]) }}" style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid var(--border);">
            @csrf
            <input type="hidden" name="scan_method" value="manual">
            <h4 style="margin-bottom:10px;color:var(--success);"><i class="fa-solid fa-clipboard-check"></i> บันทึกตรวจเช็คสต็อก</h4>
            <div class="form-group" style="margin-bottom:8px;">
              <label class="form-label">รอบตรวจนับ</label>
              <select name="stock_check_id" class="form-select">
                <option value="">ตรวจเช็คทั่วไป (นอกรอบ)</option>
                @foreach($openStockChecks as $sc)
                  <option value="{{ $sc->id }}" selected>{{ $sc->code }} - {{ $sc->title }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group" style="margin-bottom:8px;">
              <label class="form-label">สภาพที่พบ</label>
              <select name="condition_status" class="form-select" required>
                <option value="usable" {{ $asset->status === 'usable' ? 'selected' : '' }}>ใช้งานได้ปกติ</option>
                <option value="repair" {{ $asset->status === 'repair' ? 'selected' : '' }}>พบชำรุด / ควรส่งซ่อม</option>
                <option value="damaged" {{ $asset->status === 'damaged' ? 'selected' : '' }}>ชำรุด / ใช้ไม่ได้</option>
                <option value="lost" {{ $asset->status === 'lost' ? 'selected' : '' }}>ไม่พบ / สูญหาย</option>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label class="form-label">บันทึกเพิ่มเติม</label>
              <input type="text" name="note" class="form-control" placeholder="สภาพเรียบร้อยดี / ตำแหน่งที่พบ">
            </div>
            <button type="submit" class="btn btn-success btn-sm" style="width:100%;">บันทึกผลตรวจเช็ค</button>
          </form>

          <!-- Repair Ticket -->
          <form method="POST" action="{{ route('maintenances.store') }}" style="background:#fffbeb;padding:16px;border-radius:12px;border:1px solid #fde68a;">
            @csrf
            <input type="hidden" name="asset_id" value="{{ $asset->id }}">
            <h4 style="margin-bottom:10px;color:#b45309;"><i class="fa-solid fa-screwdriver-wrench"></i> แจ้งซ่อมครุภัณฑ์</h4>
            <div class="form-group" style="margin-bottom:8px;">
              <label class="form-label">อาการชำรุด / สาเหตุ *</label>
              <textarea name="issue_description" class="form-control" rows="2" required placeholder="ระบุอาการชำรุด"></textarea>
            </div>
            <div class="form-group" style="margin-bottom:10px;">
              <label class="form-label">ร้าน / ผู้ซ่อม (ถ้าทราบ)</label>
              <input type="text" name="vendor_name" class="form-control" placeholder="ระบุร้านหรือช่าง">
            </div>
            <button type="submit" class="btn btn-warning btn-sm" style="width:100%;">เปิดใบแจ้งซ่อม</button>
          </form>
        </div>

        <!-- Transfer Form -->
        <form method="POST" action="{{ route('movements.store') }}" style="margin-top:16px;background:#eff6ff;padding:16px;border-radius:12px;border:1px solid #bfdbfe;">
          @csrf
          <input type="hidden" name="asset_id" value="{{ $asset->id }}">
          <h4 style="margin-bottom:10px;color:var(--primary);"><i class="fa-solid fa-arrows-rotate"></i> โอนย้ายผู้รับผิดชอบ / เปลี่ยนสถานที่ / จำหน่าย</h4>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">ประเภทรายการ</label>
              <select name="movement_type" class="form-select">
                <option value="transfer">โอนย้าย / เปลี่ยนผู้รับผิดชอบ</option>
                <option value="status_change">เปลี่ยนสภาพครุภัณฑ์</option>
                <option value="dispose">จำหน่ายออก</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">ผู้รับผิดชอบใหม่</label>
              <input type="text" name="to_responsible" list="respShowList" class="form-control" value="{{ $asset->responsible_person }}">
              <datalist id="respShowList">
                @foreach($responsibles as $r)<option value="{{ $r }}">@endforeach
              </datalist>
            </div>
            <div class="form-group">
              <label class="form-label">ตำแหน่งใหม่</label>
              <input type="text" name="to_position" list="posShowList" class="form-control" value="{{ $asset->position_title }}" placeholder="เช่น หัวหน้าสำนักปลัด">
              <datalist id="posShowList">
                @foreach($positions as $p)<option value="{{ $p }}">@endforeach
              </datalist>
            </div>
            <div class="form-group">
              <label class="form-label">สถานที่ใหม่</label>
              <input type="text" name="to_location" class="form-control" value="{{ $asset->location }}">
            </div>
            <div class="form-group">
              <label class="form-label">สถานะใหม่</label>
              <select name="to_status" class="form-select">
                @foreach(\App\Models\Asset::STATUS_LABELS as $k => $l)
                  <option value="{{ $k }}" {{ $asset->status === $k ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">เลขที่เอกสารอ้างอิง</label>
              <input type="text" name="reference_doc" class="form-control" placeholder="บันทึกข้อความ / คำสั่ง">
            </div>
            <div class="form-group">
              <label class="form-label">หมายเหตุ</label>
              <input type="text" name="remark" class="form-control" placeholder="เหตุผลในการโอนย้าย">
            </div>
          </div>
          <div style="margin-top:10px;text-align:right;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> บันทึกการโอนย้าย/เปลี่ยนสถานะ</button>
          </div>
        </form>
      </div>

      <!-- History Logs -->
      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติการตรวจเช็ค / โอนย้าย / ซ่อมบำรุง</div></div>

        <h4 style="margin-bottom:8px;">1. ประวัติการตรวจเช็คสต็อก ({{ $asset->inspections->count() }})</h4>
        <div class="table-responsive" style="margin-bottom:18px;">
          <table>
            <thead><tr><th>วันเวลา</th><th>สภาพที่พบ</th><th>รอบตรวจนับ</th><th>สถานที่</th><th>ผู้ตรวจ</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
              @forelse($asset->inspections as $insp)
                <tr>
                  <td style="white-space:nowrap;">{{ $insp->inspected_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $insp->conditionLabel() }}</td>
                  <td>{{ $insp->stockCheck?->code ?? 'ตรวจทั่วไป' }}</td>
                  <td>{{ $insp->location_found ?? '-' }}</td>
                  <td>{{ $insp->inspector_name ?? '-' }}</td>
                  <td>{{ $insp->note ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="6" style="color:var(--text-light);text-align:center;">ยังไม่มีประวัติการตรวจเช็ค</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <h4 style="margin-bottom:8px;">2. ประวัติการโอนย้าย / เปลี่ยนแปลง ({{ $asset->movements->count() }})</h4>
        <div class="table-responsive" style="margin-bottom:18px;">
          <table>
            <thead><tr><th>วันเวลา</th><th>ประเภท</th><th>จาก &rarr; เป็น</th><th>เอกสาร</th><th>ผู้ทำรายการ</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
              @forelse($asset->movements as $mv)
                <tr>
                  <td style="white-space:nowrap;">{{ $mv->moved_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $mv->typeLabel() }}</td>
                  <td>{{ $mv->from_responsible ?? '-' }} &rarr; <strong>{{ $mv->to_responsible ?? '-' }}</strong></td>
                  <td>{{ $mv->reference_doc ?? '-' }}</td>
                  <td>{{ $mv->user?->name ?? 'ระบบ' }}</td>
                  <td>{{ $mv->remark ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="6" style="color:var(--text-light);text-align:center;">ยังไม่มีประวัติความเคลื่อนไหว</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <h4 style="margin-bottom:8px;">3. ประวัติการแจ้งซ่อม ({{ $asset->maintenanceLogs->count() }})</h4>
        <div class="table-responsive">
          <table>
            <thead><tr><th>วันที่แจ้ง</th><th>อาการชำรุด</th><th>สถานะ</th><th>ร้านซ่อม</th><th>ค่าซ่อม (บาท)</th></tr></thead>
            <tbody>
              @forelse($asset->maintenanceLogs as $ml)
                <tr>
                  <td style="white-space:nowrap;">{{ $ml->reported_at->format('d/m/Y H:i') }}</td>
                  <td>{{ $ml->issue_description }}</td>
                  <td>{{ $ml->statusLabel() }}</td>
                  <td>{{ $ml->vendor_name ?? '-' }}</td>
                  <td style="text-align:right;">{{ number_format((float)$ml->repair_cost, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="5" style="color:var(--text-light);text-align:center;">ยังไม่มีประวัติแจ้งซ่อม</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right: QR Sticker Card -->
    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-qrcode"></i> ป้าย QR Code ครุภัณฑ์</h3>
        <p>สแกนเพื่อตรวจสอบหรือตรวจนับสต็อกทันที</p>
      </div>

      <div class="qr-box">
        <div class="qr-svg-wrap" id="assetQrWrap">{!! $qrSvg !!}</div>
        <div style="text-align:center;">
          <div style="font-size:16px;font-weight:700;color:var(--primary-dark);">{{ $asset->displayCode() }}</div>
          <div style="font-size:12.5px;color:var(--text-light);">{{ $asset->qr_token }} • {{ $asset->responsible_person ?? 'สำนักปลัด' }}</div>
        </div>
      </div>

      <div style="font-size:12px;color:var(--text-light);word-break:break-all;background:#f8fafc;padding:10px;border-radius:10px;margin-bottom:14px;">
        <strong>URL สแกน:</strong><br>{{ $asset->scanUrl() }}
      </div>

      <div class="qr-actions">
        <a href="{{ route('assets.qr.svg', $asset) }}" download="qr-{{ $asset->qr_token }}.svg" class="btn-qr-action primary">
          <i class="fa-solid fa-download"></i> โหลด SVG
        </a>
        <a href="{{ route('qr.print', ['ids' => [$asset->id], 'layout' => 'grid-10']) }}" target="_blank" class="btn-qr-action">
          <i class="fa-solid fa-print"></i> พิมพ์ป้าย
        </a>
      </div>
    </div>
  </div>
@endsection
