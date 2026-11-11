@extends('layouts.app')

@section('title', 'สแกน QR Code ครุภัณฑ์ | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="two-col-grid">
    <div>
      <div class="panel">
        <div class="section-title">
          <div class="title-left"><i class="fa-solid fa-camera"></i> สแกน QR Code ด้วยกล้อง หรืออัปโหลดรูปภาพ</div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
          <button type="button" id="startCamBtn" class="btn btn-primary btn-sm" onclick="startCamera()">
            <i class="fa-solid fa-video"></i> เปิดกล้องสแกน QR
          </button>
          <button type="button" id="stopCamBtn" class="btn btn-outline btn-sm" onclick="stopCamera()" style="display:none;">
            <i class="fa-solid fa-stop"></i> ปิดกล้อง
          </button>
          <label class="btn btn-outline btn-sm" style="cursor:pointer;">
            <i class="fa-solid fa-image"></i> เลือกรูป QR Code
            <input type="file" id="qrFileInput" accept="image/*" style="display:none;" onchange="scanImageFile(this)">
          </label>
        </div>

        <div id="reader" style="width:100%;max-width:460px;margin:0 auto;border-radius:14px;overflow:hidden;border:2px dashed var(--primary-light);background:#f8fafc;min-height:240px;display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:14px;padding:16px;text-align:center;">
          <div>
            <i class="fa-solid fa-qrcode" style="font-size:48px;color:var(--primary-light);margin-bottom:8px;"></i><br>
            กดปุ่ม "เปิดกล้องสแกน QR" เพื่อสแกนป้ายครุภัณฑ์<br>
            <small>(รองรับเครื่องอ่านบาร์โค้ด/QR แบบ USB ในช่องด้านขวาด้วย)</small>
          </div>
        </div>
        <div id="reader-file-temp" style="display:none;"></div>
      </div>

      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-clock-rotate-left"></i> ประวัติการสแกน/ตรวจเช็คล่าสุด</div></div>
        <div class="table-responsive">
          <table>
            <thead>
              <tr><th>วันเวลา</th><th>รหัส / ชื่อครุภัณฑ์</th><th>ผลตรวจ</th><th>ผู้ตรวจ</th></tr>
            </thead>
            <tbody>
              @forelse($recentScans as $scan)
                <tr>
                  <td style="white-space:nowrap;">{{ $scan->inspected_at->format('d/m/Y H:i') }}</td>
                  <td>
                    @if($scan->asset)
                      <a href="{{ route('scan.show', ['token' => $scan->asset->qr_token]) }}" style="font-weight:700;color:var(--primary);text-decoration:none;">
                        {{ $scan->asset->displayCode() }}
                      </a>
                      <div style="font-size:12.5px;">{{ Str::limit($scan->asset->name, 45) }}</div>
                    @endif
                  </td>
                  <td>{{ $scan->conditionLabel() }}</td>
                  <td>{{ $scan->inspector_name ?? '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" style="text-align:center;color:var(--text-light);">ยังไม่มีประวัติการสแกน</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-magnifying-glass"></i> ค้นหาด้วยรหัส / เครื่องสแกน USB</h3>
        <p>ยิงบาร์โค้ด/QR หรือพิมพ์รหัสพัสดุ / FKSP-xxxx</p>
      </div>

      <form method="POST" action="{{ route('scan.lookup') }}" id="lookupForm">
        @csrf
        <div class="form-group" style="margin-bottom:12px;">
          <input type="text" name="code" id="manualCodeInput" class="form-control" autofocus required
                 placeholder="เช่น FKSP-0001 หรือ 400-42-0013">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">
          <i class="fa-solid fa-arrow-right"></i> เปิดข้อมูลครุภัณฑ์
        </button>
      </form>

      @if($openStockChecks->isNotEmpty())
        <div style="margin-top:22px;padding-top:16px;border-top:1px solid var(--border);">
          <h4 style="font-size:14.5px;margin-bottom:8px;color:var(--primary-dark);">
            <i class="fa-solid fa-clipboard-check"></i> ต้องการตรวจนับแบบต่อเนื่อง?
          </h4>
          <p style="font-size:12.5px;color:var(--text-light);margin-bottom:10px;">
            เลือกรอบตรวจนับด้านล่างเพื่อเปิดโหมดสแกนเช็คสต็อกรวดเดียวหลายชิ้น
          </p>
          @foreach($openStockChecks as $sc)
            <a href="{{ route('stock-checks.show', $sc) }}" class="btn btn-success btn-sm" style="width:100%;margin-bottom:8px;">
              <i class="fa-solid fa-bolt"></i> สแกนเข้ารอบ {{ $sc->code }}
            </a>
          @endforeach
        </div>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;

function handleDecodedText(decodedText) {
  let text = (decodedText || '').trim();
  const match = text.match(/\/scan\/([A-Za-z0-9\-_]+)/);
  if (match) {
    window.location.href = `/scan/${match[1]}`;
    return;
  }
  document.getElementById('manualCodeInput').value = text;
  document.getElementById('lookupForm').submit();
}

async function startCamera() {
  if (typeof Html5Qrcode === 'undefined') {
    showToast('ไม่สามารถโหลดไลบรารีกล้องได้ กรุณาใช้ช่องค้นหาด้วยรหัสแทน', true);
    return;
  }
  const readerEl = document.getElementById('reader');
  readerEl.innerHTML = '';
  html5QrCode = new Html5Qrcode('reader');
  try {
    await html5QrCode.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 220, height: 220 } },
      (decodedText) => {
        stopCamera();
        handleDecodedText(decodedText);
      }
    );
    document.getElementById('startCamBtn').style.display = 'none';
    document.getElementById('stopCamBtn').style.display = 'inline-flex';
  } catch (err) {
    readerEl.innerHTML = '<div style="color:#dc2626;padding:20px;">ไม่สามารถเปิดกล้องได้ (เบราว์เซอร์อาจไม่ได้อนุญาตกล้อง หรือต้องใช้ผ่าน HTTPS / localhost) กรุณาใช้การเลือกรูปภาพหรือพิมพ์รหัสแทน</div>';
    showToast('ไม่สามารถเปิดกล้องได้', true);
  }
}

async function stopCamera() {
  if (html5QrCode) {
    try { await html5QrCode.stop(); } catch (_) {}
    html5QrCode = null;
  }
  document.getElementById('startCamBtn').style.display = 'inline-flex';
  document.getElementById('stopCamBtn').style.display = 'none';
}

async function scanImageFile(input) {
  if (!input.files || !input.files[0]) return;
  if (typeof Html5Qrcode === 'undefined') {
    showToast('ไม่สามารถโหลดตัวอ่าน QR ได้', true);
    return;
  }
  const scanner = new Html5Qrcode('reader-file-temp');
  try {
    const text = await scanner.scanFile(input.files[0], true);
    handleDecodedText(text);
  } catch (err) {
    showToast('ไม่พบ QR Code ในรูปภาพที่เลือก', true);
  }
}
</script>
@endpush
