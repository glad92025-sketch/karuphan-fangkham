@extends('layouts.app')

@section('title', $stockCheck->code . ' | ตรวจนับสต็อกครุภัณฑ์')

@section('content')
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div>
      <a href="{{ route('stock-checks.index') }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> รอบตรวจนับทั้งหมด</a>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="{{ route('stock-checks.export', $stockCheck) }}" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-file-excel" style="color:var(--success);"></i> ส่งออกรายงาน Excel
      </a>
      <form method="POST" action="{{ route('stock-checks.toggle', $stockCheck) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-outline btn-sm">
          {{ $stockCheck->isOpen() ? 'ปิดรอบตรวจนับ' : 'เปิดรอบอีกครั้ง' }}
        </button>
      </form>
    </div>
  </div>

  <!-- Summary KPIs -->
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div class="stat-value" id="statChecked">{{ number_format($summary['checked']) }} / {{ number_format($summary['total']) }}</div>
      <div class="stat-label">ความคืบหน้าการตรวจนับ (<span id="statPercent">{{ $summary['percent'] }}</span>%)</div>
      <div class="progress-wrap" style="margin-top:8px;">
        <div class="progress-bar" id="progressBar" style="width:{{ $summary['percent'] }}%;"></div>
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="stat-value">{{ number_format($summary['found_usable']) }}</div>
      <div class="stat-label">ตรวจพบ - ใช้งานได้</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
      <div class="stat-value" id="statUnchecked">{{ number_format($summary['unchecked']) }}</div>
      <div class="stat-label">ยังไม่ถูกตรวจนับ</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="stat-value">{{ number_format($summary['found_repair'] + $summary['found_damaged'] + $summary['not_found']) }}</div>
      <div class="stat-label">พบซ่อม / ชำรุด / ไม่พบ</div>
    </div>
  </div>

  <div class="two-col-grid">
    <!-- Left: Checklist Tabs -->
    <div class="panel">
      <div class="section-title">
        <div class="title-left"><i class="fa-solid fa-list-check"></i> {{ $stockCheck->title }} ({{ $stockCheck->code }})</div>
      </div>

      <div class="tabs">
        <a href="{{ route('stock-checks.show', ['stockCheck' => $stockCheck, 'tab' => 'unchecked', 'search' => $search, 'responsible_person' => $responsibleFilter]) }}"
           class="tab-btn {{ $tab === 'unchecked' ? 'active' : '' }}">
          ยังไม่ตรวจนับ ({{ number_format($summary['unchecked']) }})
        </a>
        <a href="{{ route('stock-checks.show', ['stockCheck' => $stockCheck, 'tab' => 'checked', 'search' => $search, 'responsible_person' => $responsibleFilter]) }}"
           class="tab-btn {{ $tab === 'checked' ? 'active' : '' }}">
          ตรวจนับแล้ว ({{ number_format($summary['checked']) }})
        </a>
        <a href="{{ route('stock-checks.show', ['stockCheck' => $stockCheck, 'tab' => 'issues', 'search' => $search, 'responsible_person' => $responsibleFilter]) }}"
           class="tab-btn {{ $tab === 'issues' ? 'active' : '' }}">
          พบปัญหา / ซ่อม / ไม่พบ ({{ number_format($summary['found_repair'] + $summary['found_damaged'] + $summary['not_found']) }})
        </a>
      </div>

      <form method="GET" action="{{ route('stock-checks.show', $stockCheck) }}" class="filter-bar">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="form-group">
          <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="ค้นหารหัส / ชื่อครุภัณฑ์...">
        </div>
        <div class="form-group">
          <select name="responsible_person" class="form-select">
            <option value="">ทุกตำแหน่ง/ผู้รับผิดชอบ</option>
            @foreach($responsibles as $r)
              <option value="{{ $r }}" {{ $responsibleFilter === $r ? 'selected' : '' }}>{{ $r }}</option>
            @endforeach
          </select>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" class="btn btn-primary btn-sm">กรอง</button>
          <a href="{{ route('stock-checks.show', ['stockCheck' => $stockCheck, 'tab' => $tab]) }}" class="btn btn-outline btn-sm">ล้าง</a>
        </div>
      </form>

      @if($tab === 'unchecked')
        <div class="table-responsive">
          <table>
            <thead><tr><th>รหัส QR / พัสดุ</th><th>ชื่อพัสดุ</th><th>ผู้รับผิดชอบ</th><th style="text-align:right;">เช็คด่วน</th></tr></thead>
            <tbody>
              @forelse($uncheckedAssets as $asset)
                <tr>
                  <td style="white-space:nowrap;">
                    <a href="{{ route('assets.show', $asset) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">{{ $asset->displayCode() }}</a>
                    <div style="font-size:11.5px;color:var(--text-light);">{{ $asset->qr_token }}</div>
                  </td>
                  <td>{{ $asset->name }}</td>
                  <td>
                    <span class="badge badge-blue">{{ $asset->responsible_person ?? '-' }}</span>
                    @if($asset->position_title)
                      <div style="font-size:11.5px;color:#6d28d9;font-weight:600;margin-top:2px;">{{ $asset->position_title }}</div>
                    @endif
                  </td>
                  <td style="text-align:right;white-space:nowrap;">
                    @if($stockCheck->isOpen())
                      <form method="POST" action="{{ route('stock-checks.scan', $stockCheck) }}" style="display:inline-flex;gap:4px;">
                        @csrf
                        <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                        <input type="hidden" name="scan_method" value="manual">
                        <button type="submit" name="check_status" value="found_usable" class="btn btn-success btn-sm" title="พบ - ใช้งานได้">
                          <i class="fa-solid fa-check"></i> ปกติ
                        </button>
                        <button type="submit" name="check_status" value="found_repair" class="btn btn-warning btn-sm" title="พบ - ควรส่งซ่อม">
                          ซ่อม
                        </button>
                        <button type="submit" name="check_status" value="not_found" class="btn btn-danger btn-sm" title="ไม่พบ">
                          ไม่พบ
                        </button>
                      </form>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="4" style="text-align:center;color:var(--success);padding:26px;">ตรวจนับครบทุกรายการแล้ว!</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="pagination-wrap">
          <div style="font-size:13px;color:var(--text-light);">
            แสดง {{ $uncheckedAssets->firstItem() ?? 0 }} ถึง {{ $uncheckedAssets->lastItem() ?? 0 }} จาก {{ $uncheckedAssets->total() }} รายการ
          </div>
          <div class="pagination-links">{{ $uncheckedAssets->links() }}</div>
        </div>
      @else
        <div class="table-responsive">
          <table>
            <thead><tr><th>วันเวลา</th><th>รหัส / ชื่อครุภัณฑ์</th><th>ผลตรวจนับ</th><th>ผู้ถือครอง / ตำแหน่ง</th><th>ผู้ตรวจ</th></tr></thead>
            <tbody>
              @forelse($checkedItems as $item)
                <tr>
                  <td style="white-space:nowrap;">{{ $item->checked_at->format('d/m/Y H:i') }}</td>
                  <td>
                    <a href="{{ route('assets.show', $item->asset_id) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">
                      {{ $item->asset?->displayCode() }}
                    </a>
                    <div style="font-size:12.5px;">{{ $item->asset?->name }}</div>
                  </td>
                  <td>
                    <span class="badge {{ $item->check_status === 'found_usable' ? 'badge-ok' : ($item->check_status === 'found_repair' ? 'badge-repair' : 'badge-lost') }}">
                      {{ $item->statusLabel() }}
                    </span>
                  </td>
                  <td>
                    <div>{{ $item->actual_responsible ?? '-' }}</div>
                    @if($item->actual_position)
                      <div style="font-size:11.5px;color:#6d28d9;font-weight:600;">{{ $item->actual_position }}</div>
                    @endif
                  </td>
                  <td>{{ $item->checker_name ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:26px;">ยังไม่มีรายการในหมวดนี้</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="pagination-wrap">
          <div style="font-size:13px;color:var(--text-light);">
            แสดง {{ $checkedItems->firstItem() ?? 0 }} ถึง {{ $checkedItems->lastItem() ?? 0 }} จาก {{ $checkedItems->total() }} รายการ
          </div>
          <div class="pagination-links">{{ $checkedItems->links() }}</div>
        </div>
      @endif
    </div>

    <!-- Right: Continuous QR Scanner Mode -->
    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-bolt"></i> โหมดสแกนเช็คสต็อกต่อเนื่อง</h3>
        <p>ยิง QR / กล้อง เพื่อติ๊กเช็คอัตโนมัติทีละชิ้น</p>
      </div>

      @if($stockCheck->isOpen())
        <div style="display:flex;gap:8px;margin-bottom:10px;">
          <button type="button" id="startRoundCam" class="btn btn-primary btn-sm" style="flex:1;" onclick="startRoundCamera()">
            <i class="fa-solid fa-video"></i> เปิดกล้อง
          </button>
          <button type="button" id="stopRoundCam" class="btn btn-outline btn-sm" style="flex:1;display:none;" onclick="stopRoundCamera()">
            <i class="fa-solid fa-stop"></i> ปิดกล้อง
          </button>
        </div>

        <div id="roundReader" style="border:2px dashed var(--primary-light);border-radius:12px;margin-bottom:12px;overflow:hidden;display:none;"></div>

        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">สถานะอัตโนมัติเมื่อสแกน</label>
          <select id="autoCheckStatus" class="form-select">
            <option value="found_usable">พบ - ใช้งานได้ปกติ</option>
            <option value="found_repair">พบ - ควรส่งซ่อม</option>
            <option value="found_damaged">พบ - ชำรุด</option>
            <option value="not_found">ไม่พบครุภัณฑ์</option>
          </select>
        </div>

        <div class="qr-input-group">
          <input type="text" id="quickScanInput" class="qr-input" placeholder="สแกน หรือพิมพ์ FKSP-0001">
          <button type="button" class="btn-generate" onclick="submitQuickScan()">เช็ค</button>
        </div>

        <div id="liveScanLog" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;max-height:240px;overflow-y:auto;"></div>
      @else
        <div class="alert alert-warning">รอบตรวจนับนี้ถูกปิดแล้ว หากต้องการสแกนเพิ่มกรุณากด "เปิดรอบอีกครั้ง"</div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const scanEndpoint = @json(route('stock-checks.scan', $stockCheck));
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let roundScanner = null;
let lastScannedCode = '';
let lastScannedAt = 0;

async function submitQuickScan(overrideCode = null) {
  const input = document.getElementById('quickScanInput');
  const code = (overrideCode ?? input.value).trim();
  if (!code) return;

  const status = document.getElementById('autoCheckStatus').value;

  try {
    const res = await fetch(scanEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({
        code: code,
        check_status: status,
        scan_method: overrideCode ? 'qr' : 'manual'
      })
    });

    const data = await res.json();
    if (!res.ok || !data.ok) {
      showToast(data.message || 'ไม่พบครุภัณฑ์', true);
      return;
    }

    showToast(data.message);
    if (!overrideCode) input.value = '';
    input.focus();

    if (data.progress) {
      document.getElementById('statChecked').textContent = `${data.progress.checked} / ${data.progress.total}`;
      document.getElementById('statUnchecked').textContent = data.progress.unchecked;
      document.getElementById('statPercent').textContent = data.progress.percent;
      document.getElementById('progressBar').style.width = `${data.progress.percent}%`;
    }

    const logBox = document.getElementById('liveScanLog');
    const entry = document.createElement('div');
    entry.style.cssText = 'padding:8px 10px;border-radius:10px;background:#d1fae5;border:1px solid #a7f3d0;font-size:12.5px;';
    entry.innerHTML = `<strong>${data.asset.display_code}</strong> — ${data.asset.name}<div style="color:#065f46;font-size:11.5px;">${data.asset.status_label} • ${data.asset.checked_at}</div>`;
    logBox.prepend(entry);
  } catch (err) {
    showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', true);
  }
}

document.getElementById('quickScanInput')?.addEventListener('keypress', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    submitQuickScan();
  }
});

async function startRoundCamera() {
  if (typeof Html5Qrcode === 'undefined') {
    showToast('โหลดกล้องไม่สำเร็จ', true);
    return;
  }
  const box = document.getElementById('roundReader');
  box.style.display = 'block';
  roundScanner = new Html5Qrcode('roundReader');
  try {
    await roundScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 200, height: 200 } },
      (decoded) => {
        const now = Date.now();
        if (decoded === lastScannedCode && (now - lastScannedAt) < 2500) return;
        lastScannedCode = decoded;
        lastScannedAt = now;
        submitQuickScan(decoded);
      }
    );
    document.getElementById('startRoundCam').style.display = 'none';
    document.getElementById('stopRoundCam').style.display = 'inline-flex';
  } catch (e) {
    box.style.display = 'none';
    showToast('ไม่สามารถเปิดกล้องได้', true);
  }
}

async function stopRoundCamera() {
  if (roundScanner) {
    try { await roundScanner.stop(); } catch (_) {}
    roundScanner = null;
  }
  document.getElementById('roundReader').style.display = 'none';
  document.getElementById('startRoundCam').style.display = 'inline-flex';
  document.getElementById('stopRoundCam').style.display = 'none';
}
</script>
@endpush
