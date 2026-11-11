@extends('layouts.app')

@section('title', 'ทะเบียนคุมครุภัณฑ์ | ระบบควบคุมครุภัณฑ์ สำนักปลัด')

@section('content')
  <div class="tabs">
    <a href="{{ route('assets.index', ['review_status' => 'approved']) }}"
       class="tab-btn {{ $reviewStatus === 'approved' ? 'active' : '' }}">
      <i class="fa-solid fa-boxes-stacked"></i> ทะเบียนสำนักปลัด ({{ number_format($approvedCount) }})
    </a>
    <a href="{{ route('assets.index', ['review_status' => 'pending']) }}"
       class="tab-btn {{ $reviewStatus === 'pending' ? 'active' : '' }}">
      <i class="fa-solid fa-hourglass-half"></i> รอตรวจสอบ/จัดสรรตำแหน่ง ({{ number_format($pendingCount) }})
    </a>
  </div>

  <div class="panel">
    <div class="section-title">
      <div class="title-left">
        <i class="fa-solid fa-clipboard-list"></i>
        <span>{{ $reviewStatus === 'pending' ? 'รายการรอตรวจสอบ / ยังไม่ระบุตำแหน่งผู้รับผิดชอบ' : 'ทะเบียนคุมครุภัณฑ์ สำนักปลัด อบต.ฝางคำ' }}</span>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if($reviewStatus === 'approved')
          <a href="{{ route('reports.export', request()->query()) }}" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-file-excel" style="color:var(--success);"></i> ส่งออก Excel
          </a>
          <a href="{{ route('qr.print', request()->query()) }}" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-print"></i> พิมพ์สติกเกอร์ QR
          </a>
          <a href="{{ route('assets.create') }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> ลงทะเบียนครุภัณฑ์ใหม่
          </a>
        @endif
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('assets.index') }}" class="filter-bar">
      <input type="hidden" name="review_status" value="{{ $reviewStatus }}">

      <div class="form-group">
        <label class="form-label">คำค้นหา (รหัส / ชื่อ / ตำแหน่ง)</label>
        <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="ค้นหา...">
      </div>

      <div class="form-group">
        <label class="form-label">ปีงบประมาณ</label>
        <select name="fiscal_year" class="form-select">
          <option value="">ทั้งหมด</option>
          <option value="2568" {{ request('fiscal_year') == '2568' ? 'selected' : '' }}>ปี 2568 (ฐานเดิม)</option>
          <option value="2569" {{ request('fiscal_year') == '2569' ? 'selected' : '' }}>ปี 2569 (เพิ่มใหม่)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">หมวดหมู่</label>
        <select name="category" class="form-select">
          <option value="">ทุกหมวดหมู่</option>
          @foreach($categories as $cat)
            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">ผู้รับผิดชอบ / ตำแหน่ง</label>
        <select name="responsible_person" class="form-select">
          <option value="">ทั้งหมด</option>
          @if($reviewStatus === 'pending')
            <option value="__EMPTY__" {{ request('responsible_person') === '__EMPTY__' ? 'selected' : '' }}>(ช่องผู้รับผิดชอบว่าง)</option>
          @endif
          @foreach($responsibles as $resp)
            <option value="{{ $resp }}" {{ request('responsible_person') === $resp ? 'selected' : '' }}>{{ $resp }}</option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">สภาพ / สถานะ</label>
        <select name="status" class="form-select">
          <option value="">ทุกสถานะ</option>
          @foreach(\App\Models\Asset::STATUS_LABELS as $key => $label)
            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div style="display:flex;gap:8px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> ค้นหา</button>
        <a href="{{ route('assets.index', ['review_status' => $reviewStatus]) }}" class="btn btn-outline btn-sm">ล้าง</a>
      </div>
    </form>

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:10px 14px;background:#eff6ff;border-radius:10px;margin-bottom:16px;font-size:13.5px;">
      <div>
        พบทั้งหมด <strong>{{ number_format($filteredCount) }}</strong> รายการ
        • มูลค่ารวม <strong>{{ number_format($filteredSum, 2) }}</strong> บาท
      </div>
      @if($reviewStatus === 'approved')
        <button type="button" onclick="printSelectedQR()" class="btn btn-success btn-sm">
          <i class="fa-solid fa-qrcode"></i> พิมพ์ QR เฉพาะรายการที่ติ๊กเลือก (<span id="selectedCount">0</span>)
        </button>
      @endif
    </div>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            @if($reviewStatus === 'approved')
              <th style="width:36px;"><input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)"></th>
            @endif
            <th>ลำดับ</th>
            <th>รหัส QR / รหัสพัสดุ</th>
            <th>ชื่อพัสดุ / รายละเอียด</th>
            <th>วันที่ได้มา</th>
            <th style="text-align:right;">ราคา (บาท)</th>
            <th>ผู้รับผิดชอบ / ตำแหน่ง</th>
            <th>สภาพ</th>
            <th>เช็คล่าสุด</th>
            <th style="text-align:right;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($assets as $asset)
            <tr>
              @if($reviewStatus === 'approved')
                <td><input type="checkbox" class="asset-chk" value="{{ $asset->id }}" onchange="updateSelectedCount()"></td>
              @endif
              <td>{{ $asset->excel_seq ?? '-' }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('assets.show', $asset) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">
                  {{ $asset->asset_code ?? '(ไม่มีรหัส)' }}
                </a>
                <div style="font-size:11.5px;color:var(--text-light);">{{ $asset->qr_token }} • ปี {{ $asset->fiscal_year }}</div>
              </td>
              <td style="min-width:220px;">
                <a href="{{ route('assets.show', $asset) }}" style="font-weight:600;text-decoration:none;">{{ $asset->name }}</a>
                @if($asset->specification && $asset->specification !== $asset->name)
                  <div style="font-size:12px;color:var(--text-light);">{{ Str::limit($asset->specification, 70) }}</div>
                @endif
                <div style="font-size:11.5px;color:var(--primary);">{{ $asset->category ?? '-' }}</div>
                @if($reviewStatus === 'pending' && $asset->note)
                  <div style="font-size:11.5px;color:#b45309;margin-top:2px;"><i class="fa-solid fa-circle-info"></i> {{ $asset->note }}</div>
                @endif
              </td>
              <td style="white-space:nowrap;">{{ $asset->acquired_date_text ?? '-' }}</td>
              <td style="text-align:right;white-space:nowrap;font-weight:600;">{{ number_format((float)$asset->price, 2) }}</td>
              <td>
                <span class="badge badge-blue">{{ $asset->responsible_person ?? 'ยังไม่ระบุ' }}</span>
                @if($asset->location)
                  <div style="font-size:11.5px;color:var(--text-light);margin-top:3px;">{{ $asset->location }}</div>
                @endif
              </td>
              <td><span class="badge {{ $asset->badgeClass() }}">{{ $asset->statusLabel() }}</span></td>
              <td style="white-space:nowrap;font-size:12px;color:var(--text-light);">
                {{ $asset->last_checked_at ? $asset->last_checked_at->format('d/m/Y H:i') : 'ยังไม่ตรวจ' }}
              </td>
              <td style="text-align:right;white-space:nowrap;">
                <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline btn-sm" title="ดูรายละเอียด &amp; QR"><i class="fa-solid fa-eye"></i></a>
                <a href="{{ route('assets.edit', $asset) }}" class="btn btn-outline btn-sm" title="แก้ไข"><i class="fa-solid fa-pen"></i></a>
                @if($reviewStatus === 'pending')
                  <button type="button" class="btn btn-success btn-sm"
                          onclick='openApproveModal(@json($asset->id), @json($asset->name), @json($asset->responsible_person))'>
                    <i class="fa-solid fa-check"></i> จัดสรรเข้าสำนักปลัด
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" style="text-align:center;color:var(--text-light);padding:32px;">ไม่พบข้อมูลครุภัณฑ์ตามเงื่อนไขที่ค้นหา</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="pagination-wrap">
      <div style="font-size:13px;color:var(--text-light);">
        แสดง {{ $assets->firstItem() ?? 0 }} ถึง {{ $assets->lastItem() ?? 0 }} จาก {{ $assets->total() }} รายการ
      </div>
      <div class="pagination-links">{{ $assets->links() }}</div>
    </div>
  </div>

  <!-- Modal Approve / Assign Position -->
  <div class="modal-backdrop" id="approveModal">
    <div class="modal-card">
      <div class="section-title" style="margin-bottom:12px;">
        <div class="title-left"><i class="fa-solid fa-user-check"></i> กำหนดผู้รับผิดชอบ &amp; นำเข้าสำนักปลัด</div>
        <button type="button" class="btn btn-outline btn-sm" onclick="closeApproveModal()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <p id="approveAssetName" style="font-size:14px;font-weight:600;color:var(--primary);margin-bottom:14px;"></p>

      <form method="POST" id="approveForm">
        @csrf
        <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">ผู้รับผิดชอบ / ชื่อตำแหน่ง *</label>
          <input type="text" name="responsible_person" id="approveRespInput" list="respSuggestions" class="form-control" required placeholder="เช่น สำนักปลัด, สำนักปลัด(ผช.ธุรการ), ห้องปลัด">
          <datalist id="respSuggestions">
            @foreach($approvedResponsibles as $resp)
              <option value="{{ $resp }}">
            @endforeach
          </datalist>
        </div>

        <div class="form-group" style="margin-bottom:12px;">
          <label class="form-label">สถานที่ตั้ง / ห้อง</label>
          <input type="text" name="location" class="form-control" placeholder="เช่น สำนักปลัด อบต.ฝางคำ">
        </div>

        <div class="form-group" style="margin-bottom:16px;">
          <label class="form-label">หมายเหตุ</label>
          <input type="text" name="note" class="form-control" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)">
        </div>

        <div style="display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" class="btn btn-outline" onclick="closeApproveModal()">ยกเลิก</button>
          <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> บันทึกและนำเข้าสำนักปลัด</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
function toggleAllCheckboxes(master) {
  document.querySelectorAll('.asset-chk').forEach(chk => chk.checked = master.checked);
  updateSelectedCount();
}

function updateSelectedCount() {
  const count = document.querySelectorAll('.asset-chk:checked').length;
  const el = document.getElementById('selectedCount');
  if (el) el.textContent = count;
}

function printSelectedQR() {
  const ids = Array.from(document.querySelectorAll('.asset-chk:checked')).map(c => c.value);
  if (!ids.length) {
    showToast('กรุณาติ๊กเลือกรายการครุภัณฑ์อย่างน้อย 1 รายการ', true);
    return;
  }
  const params = new URLSearchParams();
  ids.forEach(id => params.append('ids[]', id));
  window.open(`{{ route('qr.print') }}?${params.toString()}`, '_blank');
}

function openApproveModal(id, name, currentResp) {
  document.getElementById('approveAssetName').textContent = name;
  document.getElementById('approveRespInput').value = currentResp || 'สำนักปลัด';
  document.getElementById('approveForm').action = `/assets/${id}/approve`;
  document.getElementById('approveModal').classList.add('open');
}

function closeApproveModal() {
  document.getElementById('approveModal').classList.remove('open');
}
</script>
@endpush
