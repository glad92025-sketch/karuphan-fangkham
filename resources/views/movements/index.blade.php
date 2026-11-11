@extends('layouts.app')

@section('title', 'ประวัติโอนย้าย / จำหน่าย | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="two-col-grid">
    <div class="panel">
      <div class="section-title">
        <div class="title-left"><i class="fa-solid fa-arrows-rotate"></i> ประวัติความเคลื่อนไหวครุภัณฑ์</div>
      </div>

      <form method="GET" action="{{ route('movements.index') }}" class="filter-bar">
        <div class="form-group">
          <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="ค้นหารหัส / ชื่อ / ตำแหน่ง / เลขเอกสาร...">
        </div>
        <div class="form-group">
          <select name="type" class="form-select">
            <option value="">ทุกประเภท</option>
            @foreach(\App\Models\AssetMovement::TYPE_LABELS as $k => $l)
              <option value="{{ $k }}" {{ $typeFilter === $k ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
          <a href="{{ route('movements.index') }}" class="btn btn-outline btn-sm">ล้าง</a>
        </div>
      </form>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>วันเวลา</th>
              <th>ครุภัณฑ์</th>
              <th>ประเภท</th>
              <th>ผู้รับผิดชอบ (เดิม &rarr; ใหม่)</th>
              <th>เอกสาร / หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            @forelse($movements as $mv)
              <tr>
                <td style="white-space:nowrap;">
                  {{ $mv->moved_at->format('d/m/Y H:i') }}
                  <div style="font-size:11.5px;color:var(--text-light);">{{ $mv->user?->name ?? 'ระบบ' }}</div>
                </td>
                <td>
                  @if($mv->asset)
                    <a href="{{ route('assets.show', $mv->asset) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">
                      {{ $mv->asset->displayCode() }}
                    </a>
                    <div style="font-size:12.5px;">{{ Str::limit($mv->asset->name, 42) }}</div>
                  @endif
                </td>
                <td><span class="badge badge-blue">{{ $mv->typeLabel() }}</span></td>
                <td>
                  <div style="font-size:12.5px;">{{ $mv->from_responsible ?? '-' }} &rarr; <strong>{{ $mv->to_responsible ?? '-' }}</strong></div>
                  @if($mv->from_status !== $mv->to_status)
                    <div style="font-size:11.5px;color:var(--text-light);">
                      สถานะ: {{ \App\Models\Asset::STATUS_LABELS[$mv->from_status] ?? '-' }} &rarr; {{ \App\Models\Asset::STATUS_LABELS[$mv->to_status] ?? '-' }}
                    </div>
                  @endif
                </td>
                <td>
                  @if($mv->reference_doc)<div><strong>{{ $mv->reference_doc }}</strong></div>@endif
                  <div style="font-size:12.5px;color:var(--text-light);">{{ $mv->remark ?? '-' }}</div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:28px;">ยังไม่มีประวัติความเคลื่อนไหว</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="pagination-wrap"><div class="pagination-links">{{ $movements->links() }}</div></div>
    </div>

    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-right-left"></i> บันทึกโอนย้าย / จำหน่าย</h3>
        <p>อัปเดตผู้รับผิดชอบ ตำแหน่ง หรือจำหน่ายครุภัณฑ์</p>
      </div>

      <form method="POST" action="{{ route('movements.store') }}">
        @csrf
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">เลือกครุภัณฑ์ *</label>
          <select name="asset_id" class="form-select" required>
            <option value="">-- เลือกครุภัณฑ์ --</option>
            @foreach($assets as $a)
              <option value="{{ $a->id }}">{{ $a->displayCode() }} — {{ Str::limit($a->name, 40) }} ({{ $a->responsible_person ?? '-' }})</option>
            @endforeach
          </select>
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ประเภทรายการ *</label>
          <select name="movement_type" class="form-select" required>
            <option value="transfer">โอนย้าย / เปลี่ยนผู้รับผิดชอบ</option>
            <option value="status_change">เปลี่ยนสภาพครุภัณฑ์</option>
            <option value="dispose">จำหน่ายครุภัณฑ์</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ผู้รับผิดชอบใหม่ (ชื่อ / หน่วยงาน)</label>
          <input type="text" name="to_responsible" list="mvRespList" class="form-control" placeholder="ระบุผู้รับผิดชอบใหม่">
          <datalist id="mvRespList">
            @foreach($responsibles as $r)<option value="{{ $r }}">@endforeach
          </datalist>
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ตำแหน่งใหม่</label>
          <input type="text" name="to_position" list="mvPosList" class="form-control" placeholder="ระบุตำแหน่งใหม่">
          <datalist id="mvPosList">
            @foreach($positions as $p)<option value="{{ $p }}">@endforeach
          </datalist>
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">สถานที่ใหม่</label>
          <input type="text" name="to_location" class="form-control" placeholder="ระบุห้อง/อาคาร/หมู่ที่">
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">สถานะครุภัณฑ์ *</label>
          <select name="to_status" class="form-select" required>
            @foreach(\App\Models\Asset::STATUS_LABELS as $k => $l)
              <option value="{{ $k }}">{{ $l }}</option>
            @endforeach
          </select>
        </div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">เลขที่หนังสือ / บันทึกข้อความ</label>
          <input type="text" name="reference_doc" class="form-control" placeholder="เช่น อบต.ฝค/2569">
        </div>

        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">หมายเหตุ</label>
          <textarea name="remark" class="form-control" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-floppy-disk"></i> บันทึกความเคลื่อนไหว
        </button>
      </form>
    </div>
  </div>
@endsection
