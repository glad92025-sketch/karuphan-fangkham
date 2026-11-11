@extends('layouts.app')

@section('title', 'คีย์ข้อมูลผู้รับผิดชอบและตำแหน่ง (แบบตารางด่วน) | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="panel">
    <div class="section-title">
      <div class="title-left">
        <i class="fa-solid fa-table-cells"></i>
        <span>คีย์ข้อมูลผู้รับผิดชอบ / ตำแหน่ง / สถานที่ตั้ง แบบตารางด่วน ({{ number_format($assets->count()) }} รายการ)</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('assets.checklist', request()->query()) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-print"></i> พิมพ์ใบรายการทั้งหมด
        </a>
        <a href="{{ route('assets.index', ['review_status' => $reviewStatus]) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> กลับทะเบียนคุม
        </a>
      </div>
    </div>

    <form method="GET" action="{{ route('assets.bulk-edit') }}" class="filter-bar">
      <div class="form-group">
        <label class="form-label">ชุดข้อมูล</label>
        <select name="review_status" class="form-select">
          <option value="approved" {{ $reviewStatus === 'approved' ? 'selected' : '' }}>ทะเบียนสำนักปลัด (194)</option>
          <option value="pending" {{ $reviewStatus === 'pending' ? 'selected' : '' }}>รอตรวจสอบ (123)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ค้นหา (รหัส / ชื่อ / ปีที่ได้มา / ผู้รับผิดชอบ)</label>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="เช่น 2542 หรือ เก้าอี้...">
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
        <label class="form-label">ตัวกรองตำแหน่ง</label>
        <select name="missing_position" class="form-select">
          <option value="0" {{ !$onlyMissingPosition ? 'selected' : '' }}>แสดงทั้งหมด</option>
          <option value="1" {{ $onlyMissingPosition ? 'selected' : '' }}>เฉพาะรายการที่ยังไม่ใส่ตำแหน่ง</option>
        </select>
      </div>
      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
        <a href="{{ route('assets.bulk-edit') }}" class="btn btn-outline btn-sm">ล้าง</a>
      </div>
    </form>

    <form method="POST" action="{{ route('assets.bulk-update') }}">
      @csrf
      <datalist id="bulkRespList">
        @foreach($responsibles as $r)<option value="{{ $r }}">@endforeach
      </datalist>
      <datalist id="bulkPosList">
        @foreach($positions as $p)<option value="{{ $p }}">@endforeach
      </datalist>

      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding:10px 14px;background:#eff6ff;border-radius:10px;flex-wrap:wrap;gap:10px;">
        <span style="font-size:13.5px;">พิมพ์แก้ไขในตารางได้เลย แล้วกด <strong>บันทึกการเปลี่ยนแปลงทั้งหมด</strong> ด้านขวา (ระบบจะอัปเดตเฉพาะแถวที่มีการแก้ไข)</span>
        <button type="submit" class="btn btn-success">
          <i class="fa-solid fa-floppy-disk"></i> บันทึกการเปลี่ยนแปลงทั้งหมด
        </button>
      </div>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width:50px;">ลำดับ</th>
              <th style="width:125px;">รหัส QR / พัสดุ</th>
              <th style="min-width:210px;">ชื่อพัสดุ / ปีที่ได้มา</th>
              <th style="min-width:190px;">ผู้รับผิดชอบ (ชื่อ / หน่วยงาน)</th>
              <th style="min-width:190px;">ตำแหน่ง</th>
              <th style="min-width:170px;">สถานที่ตั้ง / ห้อง</th>
              <th style="width:140px;">สภาพ</th>
            </tr>
          </thead>
          <tbody>
            @forelse($assets as $idx => $asset)
              @php $acqYear = $asset->acquiredYearBe(); @endphp
              <tr>
                <td style="text-align:center;">{{ $idx + 1 }}</td>
                <td style="white-space:nowrap;">
                  <a href="{{ route('scan.show', ['token' => $asset->qr_token]) }}" target="_blank" style="font-weight:700;color:var(--primary);text-decoration:none;">
                    {{ $asset->qr_token }}
                  </a>
                  <div style="font-size:11.5px;color:var(--text-light);">{{ $asset->asset_code ?? '-' }}</div>
                </td>
                <td>
                  <div style="font-weight:600;">{{ $asset->name }}</div>
                  <div style="font-size:11.5px;color:var(--text-light);">
                    @if($acqYear)
                      <span style="display:inline-block;padding:0 6px;border-radius:999px;background:#ecfdf5;color:#0f766e;font-weight:700;margin-right:4px;">ปี {{ $acqYear }}</span>
                    @endif
                    ได้มา: {{ $asset->acquiredDisplay() }} • {{ $asset->category ?? '-' }}
                  </div>
                </td>
                <td>
                  <input type="text" name="rows[{{ $asset->id }}][responsible_person]" list="bulkRespList"
                         class="form-control" style="padding:7px 10px;font-size:13.5px;"
                         value="{{ $asset->responsible_person }}" placeholder="ระบุผู้รับผิดชอบ">
                </td>
                <td>
                  <input type="text" name="rows[{{ $asset->id }}][position_title]" list="bulkPosList"
                         class="form-control" style="padding:7px 10px;font-size:13.5px;"
                         value="{{ $asset->position_title }}" placeholder="ระบุตำแหน่ง">
                </td>
                <td>
                  <input type="text" name="rows[{{ $asset->id }}][location]"
                         class="form-control" style="padding:7px 10px;font-size:13.5px;"
                         value="{{ $asset->location }}" placeholder="ห้อง / สถานที่">
                </td>
                <td>
                  <select name="rows[{{ $asset->id }}][status]" class="form-select" style="padding:7px 8px;font-size:13px;">
                    @foreach(\App\Models\Asset::STATUS_LABELS as $k => $l)
                      <option value="{{ $k }}" {{ $asset->status === $k ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                  </select>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-light);">ไม่พบรายการตามเงื่อนไข</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($assets->isNotEmpty())
      <div style="margin-top:16px;text-align:right;">
        <button type="submit" class="btn btn-success">
          <i class="fa-solid fa-floppy-disk"></i> บันทึกการเปลี่ยนแปลงทั้งหมด
        </button>
      </div>
      @endif
    </form>
  </div>
@endsection
