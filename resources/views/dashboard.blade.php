@extends('layouts.app')

@section('title', 'หน้าหลัก | ระบบควบคุมครุภัณฑ์ สำนักปลัด อบต.ฝางคำ')

@section('content')
  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
      <div class="stat-value">{{ number_format($stats['total']) }}</div>
      <div class="stat-label">ครุภัณฑ์สำนักปลัดทั้งหมด</div>
      <div class="stat-sub">มูลค่ารวม {{ number_format($stats['total_value'], 2) }} บาท</div>
    </div>

    <div class="stat-card green">
      <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
      <div class="stat-value">{{ number_format($stats['usable']) }}</div>
      <div class="stat-label">ใช้งานได้ปกติ</div>
      <div class="stat-sub">ปี 2568: {{ $stats['fy2568'] }} • เพิ่มปี 2569: {{ $stats['fy2569'] }}</div>
    </div>

    <div class="stat-card orange">
      <div class="stat-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
      <div class="stat-value">{{ number_format($stats['repair']) }}</div>
      <div class="stat-label">ส่งซ่อม / รอดำเนินการ</div>
      <div class="stat-sub">รอจัดสรรตำแหน่ง: {{ number_format($stats['pending_review']) }} รายการ</div>
    </div>

    <div class="stat-card red">
      <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="stat-value">{{ number_format($stats['damaged']) }}</div>
      <div class="stat-label">ชำรุด / รอจำหน่าย / สูญหาย</div>
      <div class="stat-sub">ต้องติดตามตรวจสอบ</div>
    </div>
  </div>

  <div class="main-grid">
    <!-- LEFT COLUMN -->
    <div>
      <div class="section-title">
        <div class="title-left"><i class="fa-solid fa-grip"></i> เมนูหลักระบบควบคุมครุภัณฑ์</div>
      </div>

      <div class="menu-grid" style="margin-bottom: 24px;">
        <a class="menu-card" href="{{ route('assets.create') }}">
          <div class="icon-wrap ic-blue"><i class="fa-solid fa-plus"></i></div>
          <span>ลงทะเบียนครุภัณฑ์</span>
          <small>เพิ่มรายการ &amp; ออกรหัส QR</small>
        </a>
        <a class="menu-card" href="{{ route('assets.index') }}">
          <div class="icon-wrap ic-green"><i class="fa-solid fa-magnifying-glass"></i></div>
          <span>ค้นหาครุภัณฑ์</span>
          <small>ทะเบียนคุม {{ number_format($stats['total']) }} รายการ</small>
        </a>
        <a class="menu-card" href="{{ route('scan.index') }}">
          <div class="icon-wrap ic-orange"><i class="fa-solid fa-qrcode"></i></div>
          <span>สแกน QR Code</span>
          <small>เช็คของ / อัปเดตสภาพ</small>
        </a>
        <a class="menu-card" href="{{ route('stock-checks.index') }}">
          <div class="icon-wrap ic-purple"><i class="fa-solid fa-clipboard-check"></i></div>
          <span>ตรวจนับสต็อก</span>
          <small>รอบตรวจนับประจำปี</small>
        </a>
        <a class="menu-card" href="{{ route('maintenances.index') }}">
          <div class="icon-wrap ic-pink"><i class="fa-solid fa-screwdriver-wrench"></i></div>
          <span>แจ้งซ่อมครุภัณฑ์</span>
          <small>ติดตามงานซ่อมและค่าใช้จ่าย</small>
        </a>
        <a class="menu-card" href="{{ route('movements.index') }}">
          <div class="icon-wrap ic-teal"><i class="fa-solid fa-arrows-rotate"></i></div>
          <span>โอน/เปลี่ยนตำแหน่ง</span>
          <small>ประวัติการถือครอง/จำหน่าย</small>
        </a>
        <a class="menu-card" href="{{ route('qr.print') }}">
          <div class="icon-wrap ic-blue"><i class="fa-solid fa-print"></i></div>
          <span>พิมพ์สติกเกอร์ QR ทั้งหมด</span>
          <small>ขนาด 1.5×1.5 ซม. ครบ {{ number_format($stats['total']) }} ดวง</small>
        </a>
        <a class="menu-card" href="{{ route('reports.index') }}">
          <div class="icon-wrap ic-green"><i class="fa-solid fa-file-excel"></i></div>
          <span>รายงาน &amp; Excel</span>
          <small>ส่งออก/นำเข้าข้อมูล .xlsx</small>
        </a>
      </div>

      @if($stockCheckProgress)
      <div class="panel">
        <div class="section-title" style="margin-bottom: 10px;">
          <div class="title-left">
            <i class="fa-solid fa-list-check"></i>
            <span>ความคืบหน้ารอบตรวจนับปัจจุบัน: {{ $stockCheckProgress['campaign']->title }}</span>
          </div>
          <a href="{{ route('stock-checks.show', $stockCheckProgress['campaign']) }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-qrcode"></i> เข้าตรวจนับเลย
          </a>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:8px;color:var(--text-light);">
          <span>รหัสรอบ: <strong>{{ $stockCheckProgress['campaign']->code }}</strong></span>
          <span>ตรวจแล้ว <strong>{{ number_format($stockCheckProgress['checked']) }}</strong> / {{ number_format($stockCheckProgress['total']) }} รายการ ({{ $stockCheckProgress['percent'] }}%)</span>
        </div>
        <div class="progress-wrap">
          <div class="progress-bar" style="width: {{ $stockCheckProgress['percent'] }}%;"></div>
        </div>
      </div>
      @endif

      <!-- Recent Table -->
      <div class="panel">
        <div class="section-title">
          <div class="title-left"><i class="fa-solid fa-clock-rotate-left"></i> รายการครุภัณฑ์ล่าสุด &amp; สถานะ</div>
          <a href="{{ route('assets.index') }}" class="btn btn-outline btn-sm">ดูทั้งหมด</a>
        </div>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>รหัส QR / พัสดุ</th>
                <th>ชื่อครุภัณฑ์</th>
                <th>ผู้รับผิดชอบ/ตำแหน่ง</th>
                <th>ราคา (บาท)</th>
                <th>สถานะ</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentAssets as $asset)
                <tr>
                  <td>
                    <a href="{{ route('assets.show', $asset) }}" style="text-decoration:none;color:var(--primary);font-weight:700;">
                      {{ $asset->displayCode() }}
                    </a>
                    <div style="font-size:11.5px;color:var(--text-light);">{{ $asset->qr_token }}</div>
                  </td>
                  <td>
                    <a href="{{ route('assets.show', $asset) }}" style="text-decoration:none;font-weight:600;">
                      {{ Str::limit($asset->name, 48) }}
                    </a>
                    <div style="font-size:12px;color:var(--text-light);">{{ $asset->category ?? '-' }}</div>
                  </td>
                  <td>{{ $asset->responsible_person ?? '-' }}</td>
                  <td style="text-align:right;white-space:nowrap;">{{ number_format((float)$asset->price, 2) }}</td>
                  <td><span class="badge {{ $asset->badgeClass() }}">{{ $asset->statusLabel() }}</span></td>
                </tr>
              @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-light);">ยังไม่มีข้อมูลครุภัณฑ์</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="panel">
        <div class="section-title">
          <div class="title-left"><i class="fa-solid fa-chart-pie"></i> สรุปครุภัณฑ์แยกตามหมวดหมู่</div>
          <a href="{{ route('reports.index') }}" class="btn btn-outline btn-sm">ดูรายงานละเอียด</a>
        </div>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>หมวดหมู่ครุภัณฑ์</th>
                <th style="text-align:right;">จำนวน (รายการ)</th>
                <th style="text-align:right;">มูลค่ารวม (บาท)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($categorySummary as $cat)
                <tr>
                  <td>
                    <a href="{{ route('assets.index', ['category' => $cat->category_name]) }}" style="text-decoration:none;font-weight:600;color:var(--primary);">
                      {{ $cat->category_name }}
                    </a>
                  </td>
                  <td style="text-align:right;font-weight:600;">{{ number_format($cat->total_count) }}</td>
                  <td style="text-align:right;">{{ number_format((float)$cat->total_price, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: QR Panel -->
    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-qrcode"></i> สร้าง QR Code ครุภัณฑ์</h3>
        <p>ค้นหาหรือระบุรหัสเพื่อสร้างป้ายติดตามทันที</p>
      </div>

      <div class="qr-box">
        <div id="qrcode"></div>
        <div id="qrPreviewMeta" style="text-align:center;font-size:13px;">
          @if($sampleAsset)
            <strong style="color:var(--primary-dark);">{{ $sampleAsset->displayCode() }}</strong>
            <div style="color:var(--text-light);font-size:12px;">{{ Str::limit($sampleAsset->name, 42) }}</div>
          @endif
        </div>
      </div>

      <div class="form-group" style="margin-bottom:10px;position:relative;">
        <label class="form-label" for="qrSearchInput" style="font-size:12.5px;">ค้นหาจากทะเบียนครุภัณฑ์ (รหัส / ชื่อ / ตำแหน่ง)</label>
        <input type="text" id="qrSearchInput" class="qr-input" placeholder="พิมพ์ค้นหา เช่น 400-42-0013 หรือ โต๊ะ..." autocomplete="off">
        <div id="qrSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1.5px solid var(--primary-light);border-radius:10px;max-height:220px;overflow-y:auto;z-index:50;box-shadow:0 12px 24px rgba(0,0,0,0.12);margin-top:4px;"></div>
      </div>

      <div class="qr-input-group">
        <input type="text" id="qrInput" class="qr-input"
               placeholder="รหัส หรือ URL"
               value="{{ $sampleAsset ? $sampleAsset->scanUrl() : url('/scan/FKSP-0001') }}">
        <button type="button" class="btn-generate" onclick="generateQR()">
          <i class="fa-solid fa-bolt"></i> สร้าง
        </button>
      </div>

      <input type="hidden" id="qrLabelCode" value="{{ $sampleAsset ? $sampleAsset->displayCode() : 'FKSP-0001' }}">
      <input type="hidden" id="qrLabelName" value="{{ $sampleAsset ? $sampleAsset->name : 'ครุภัณฑ์สำนักปลัด' }}">

      <div class="qr-actions">
        <button type="button" class="btn-qr-action primary" onclick="downloadQR()">
          <i class="fa-solid fa-download"></i> ดาวน์โหลด
        </button>
        <button type="button" class="btn-qr-action" onclick="printQR()">
          <i class="fa-solid fa-print"></i> พิมพ์ป้าย
        </button>
      </div>

      @if($recentInspections->isNotEmpty())
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
        <div style="font-size:14px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-tower-broadcast" style="color:var(--success);"></i>
          <span>สแกนตรวจเช็คล่าสุด</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          @foreach($recentInspections->take(4) as $insp)
            <a href="{{ route('assets.show', $insp->asset_id) }}" style="text-decoration:none;padding:8px 10px;border-radius:10px;background:#f8fafc;border:1px solid var(--border);font-size:12.5px;">
              <div style="font-weight:600;color:var(--primary);">{{ $insp->asset?->displayCode() }} — {{ Str::limit($insp->asset?->name, 28) }}</div>
              <div style="color:var(--text-light);font-size:11.5px;">
                {{ $insp->conditionLabel() }} • {{ $insp->inspector_name ?? '-' }} • {{ $insp->inspected_at->format('d/m H:i') }}
              </div>
            </a>
          @endforeach
        </div>
      </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  let qrCodeInstance = null;

  function generateQR(silent = false) {
    const value = document.getElementById('qrInput').value.trim();
    if (!value) {
      if (!silent) showToast('กรุณาระบุรหัสหรือ URL สำหรับสร้าง QR Code', true);
      return;
    }

    const qrContainer = document.getElementById('qrcode');
    qrContainer.innerHTML = '';

    qrCodeInstance = new QRCode(qrContainer, {
      text: value,
      width: 180,
      height: 180,
      colorDark: '#1e3a8a',
      colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.H
    });

    if (!silent) showToast('สร้าง QR Code สำเร็จ');
  }

  function downloadQR() {
    const img = document.querySelector('#qrcode img') || document.querySelector('#qrcode canvas');
    if (!img) { showToast('กรุณาสร้าง QR Code ก่อน', true); return; }

    const dataURL = img.tagName === 'CANVAS' ? img.toDataURL('image/png') : img.src;
    const code = (document.getElementById('qrLabelCode').value || 'qr').replace(/[^A-Za-z0-9\-_]/g, '_');

    const a = document.createElement('a');
    a.href = dataURL;
    a.download = 'qrcode-' + code + '.png';
    a.click();
    showToast('ดาวน์โหลด QR Code เรียบร้อย');
  }

  function printQR() {
    const img = document.querySelector('#qrcode img') || document.querySelector('#qrcode canvas');
    if (!img) { showToast('กรุณาสร้าง QR Code ก่อน', true); return; }

    const dataURL = img.tagName === 'CANVAS' ? img.toDataURL() : img.src;
    const code = document.getElementById('qrLabelCode').value;
    const name = document.getElementById('qrLabelName').value;

    const win = window.open('', '_blank');
    win.document.write(`
      <!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>พิมพ์ป้าย QR</title>
      <style>
        body{font-family:'Sarabun',sans-serif;text-align:center;padding:40px;}
        .label{border:2px solid #1e40af;border-radius:12px;padding:22px;display:inline-block;background:#fff;max-width:280px;}
        img{width:200px;height:200px;}
        h3{margin:10px 0 4px;font-size:17px;color:#1e3a8a;}
        p{color:#475569;font-size:13px;margin:2px 0;}
      </style></head>
      <body>
        <div class="label">
          <p style="font-weight:700;color:#1e40af;">สำนักปลัด อบต.ฝางคำ</p>
          <img src="${dataURL}">
          <h3>${code}</h3>
          <p>${name}</p>
        </div>
        <script>window.onload=()=>{window.print();}<\/script>
      </body></html>
    `);
    win.document.close();
  }

  // Live Asset Search for QR Panel
  let searchTimer = null;
  const searchInput = document.getElementById('qrSearchInput');
  const resultsBox = document.getElementById('qrSearchResults');

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();
    if (q.length < 1) {
      resultsBox.style.display = 'none';
      return;
    }
    searchTimer = setTimeout(async () => {
      try {
        const res = await fetch(`{{ route('api.assets.search') }}?q=${encodeURIComponent(q)}`);
        const items = await res.json();
        if (!items.length) {
          resultsBox.innerHTML = '<div style="padding:10px 12px;font-size:13px;color:#64748b;">ไม่พบครุภัณฑ์ที่ตรงกัน</div>';
        } else {
          resultsBox.innerHTML = items.map(item => `
            <div onclick='selectQrAsset(${JSON.stringify(item)})'
                 style="padding:9px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;font-size:13px;"
                 onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='white'">
              <div style="font-weight:700;color:#1e40af;">${item.display_code} <span style="font-weight:400;color:#64748b;">(${item.qr_token})</span></div>
              <div style="color:#1e293b;">${item.name}</div>
              <div style="font-size:11.5px;color:#64748b;">${item.responsible_person || '-'}</div>
            </div>
          `).join('');
        }
        resultsBox.style.display = 'block';
      } catch (e) {
        console.error(e);
      }
    }, 220);
  });

  function selectQrAsset(item) {
    document.getElementById('qrInput').value = item.scan_url;
    document.getElementById('qrLabelCode').value = item.display_code;
    document.getElementById('qrLabelName').value = item.name;
    document.getElementById('qrPreviewMeta').innerHTML = `
      <strong style="color:var(--primary-dark);">${item.display_code}</strong>
      <div style="color:var(--text-light);font-size:12px;">${item.name}</div>
      <div style="margin-top:4px;"><a href="${item.detail_url}" style="font-size:12px;color:var(--primary);font-weight:600;">เปิดดูรายละเอียด &rarr;</a></div>
    `;
    resultsBox.style.display = 'none';
    searchInput.value = item.display_code + ' — ' + item.name;
    generateQR();
  }

  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
      resultsBox.style.display = 'none';
    }
  });

  window.addEventListener('load', () => {
    setTimeout(() => generateQR(true), 200);
  });

  document.getElementById('qrInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') generateQR();
  });
</script>
@endpush
