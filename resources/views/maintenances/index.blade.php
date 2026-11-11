@extends('layouts.app')

@section('title', 'ทะเบียนแจ้งซ่อมบำรุง | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="stats-grid">
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
      <div class="stat-value">{{ number_format($summary['pending']) }}</div>
      <div class="stat-label">รอซ่อม</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
      <div class="stat-value">{{ number_format($summary['in_progress']) }}</div>
      <div class="stat-label">กำลังดำเนินการซ่อม</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="stat-value">{{ number_format($summary['completed']) }}</div>
      <div class="stat-label">ซ่อมเสร็จแล้ว</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon"><i class="fa-solid fa-baht-sign"></i></div>
      <div class="stat-value">{{ number_format($summary['total_cost'], 2) }}</div>
      <div class="stat-label">ค่าซ่อมบำรุงสะสม (บาท)</div>
    </div>
  </div>

  <div class="two-col-grid">
    <div class="panel">
      <div class="section-title">
        <div class="title-left"><i class="fa-solid fa-wrench"></i> รายการแจ้งซ่อมและบำรุงรักษา</div>
      </div>

      <form method="GET" action="{{ route('maintenances.index') }}" class="filter-bar">
        <div class="form-group">
          <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="ค้นหารหัส / ชื่อครุภัณฑ์ / อาการ...">
        </div>
        <div class="form-group">
          <select name="status" class="form-select">
            <option value="">ทุกสถานะ</option>
            @foreach(\App\Models\MaintenanceLog::STATUS_LABELS as $k => $l)
              <option value="{{ $k }}" {{ $statusFilter === $k ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
          <a href="{{ route('maintenances.index') }}" class="btn btn-outline btn-sm">ล้าง</a>
        </div>
      </form>

      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>วันที่แจ้ง</th>
              <th>ครุภัณฑ์</th>
              <th>อาการชำรุด</th>
              <th>สถานะ &amp; อัปเดต</th>
            </tr>
          </thead>
          <tbody>
            @forelse($logs as $log)
              <tr>
                <td style="white-space:nowrap;">
                  {{ $log->reported_at->format('d/m/Y H:i') }}
                  <div style="font-size:11.5px;color:var(--text-light);">{{ $log->reporter_name ?? '-' }}</div>
                </td>
                <td>
                  @if($log->asset)
                    <a href="{{ route('assets.show', $log->asset) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">
                      {{ $log->asset->displayCode() }}
                    </a>
                    <div style="font-size:12.5px;">{{ $log->asset->name }}</div>
                  @endif
                </td>
                <td>
                  {{ $log->issue_description }}
                  @if($log->resolution_note)
                    <div style="font-size:12px;color:var(--success);margin-top:4px;">ผล: {{ $log->resolution_note }}</div>
                  @endif
                </td>
                <td style="min-width:250px;">
                  <form method="POST" action="{{ route('maintenances.update', $log) }}" style="display:flex;flex-direction:column;gap:6px;">
                    @csrf
                    @method('PATCH')
                    <select name="maintenance_status" class="form-select" style="padding:6px 10px;font-size:13px;">
                      @foreach(\App\Models\MaintenanceLog::STATUS_LABELS as $k => $l)
                        <option value="{{ $k }}" {{ $log->maintenance_status === $k ? 'selected' : '' }}>{{ $l }}</option>
                      @endforeach
                    </select>
                    <div style="display:flex;gap:6px;">
                      <input type="text" name="vendor_name" class="form-control" style="padding:5px 8px;font-size:12.5px;" value="{{ $log->vendor_name }}" placeholder="ร้านซ่อม">
                      <input type="number" step="0.01" min="0" name="repair_cost" class="form-control" style="padding:5px 8px;font-size:12.5px;width:100px;" value="{{ $log->repair_cost }}" placeholder="ค่าซ่อม">
                    </div>
                    <div style="display:flex;gap:6px;">
                      <input type="text" name="resolution_note" class="form-control" style="padding:5px 8px;font-size:12.5px;" value="{{ $log->resolution_note }}" placeholder="หมายเหตุการซ่อม">
                      <button type="submit" class="btn btn-primary btn-sm">อัปเดต</button>
                    </div>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:28px;">ยังไม่มีรายการแจ้งซ่อม</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="pagination-wrap"><div class="pagination-links">{{ $logs->links() }}</div></div>
    </div>

    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-plus-circle"></i> เปิดใบแจ้งซ่อมใหม่</h3>
        <p>เลือกครุภัณฑ์และระบุอาการชำรุด</p>
      </div>

      <form method="POST" action="{{ route('maintenances.store') }}">
        @csrf
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">เลือกครุภัณฑ์ *</label>
          <select name="asset_id" class="form-select" required>
            <option value="">-- เลือกรายการครุภัณฑ์ --</option>
            @foreach($assets as $a)
              <option value="{{ $a->id }}">{{ $a->displayCode() }} — {{ Str::limit($a->name, 45) }} ({{ $a->responsible_person ?? '-' }})</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">อาการชำรุด / รายละเอียด *</label>
          <textarea name="issue_description" class="form-control" rows="3" required placeholder="เช่น เปิดไม่ติด / แอร์ไม่เย็น"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">ร้านซ่อม / ผู้รับผิดชอบซ่อม</label>
          <input type="text" name="vendor_name" class="form-control" placeholder="ระบุถ้าทราบ">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">ประมาณการค่าซ่อม (บาท)</label>
          <input type="number" step="0.01" min="0" name="repair_cost" class="form-control" value="0">
        </div>
        <button type="submit" class="btn btn-warning" style="width:100%;">
          <i class="fa-solid fa-screwdriver-wrench"></i> บันทึกแจ้งซ่อม
        </button>
      </form>
    </div>
  </div>
@endsection
