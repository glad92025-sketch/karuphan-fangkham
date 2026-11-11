<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ระบบควบคุมครุภัณฑ์ | สำนักปลัด อบต.ฝางคำ')</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
  @stack('styles')
</head>
<body>

@php
  $openRepairCount = \App\Models\MaintenanceLog::whereIn('maintenance_status', ['pending', 'in_progress'])->count();
  $pendingReviewCount = \App\Models\Asset::pendingReview()->count();
@endphp

<header class="app-header no-print">
  <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="logo">
    <div class="logo-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
    <div class="logo-text">
      <h1>ระบบควบคุมครุภัณฑ์</h1>
      <p>องค์การบริหารส่วนตำบลฝางคำ • สำนักปลัด (งานพัสดุและทรัพย์สิน)</p>
    </div>
  </a>

  <div class="header-actions">
    @auth
      <a href="{{ route('scan.index') }}" class="btn-header" title="สแกน QR Code">
        <i class="fa-solid fa-qrcode"></i>
        <span class="hide-sm">สแกน QR</span>
      </a>

      <a href="{{ route('maintenances.index') }}" class="btn-header" title="รายการแจ้งซ่อมค้างดำเนินการ">
        <i class="fa-solid fa-bell"></i>
        <span class="hide-sm">แจ้งเตือน</span>
        @if(($openRepairCount + $pendingReviewCount) > 0)
          <span class="notif-dot">{{ $openRepairCount + $pendingReviewCount }}</span>
        @endif
      </a>

      <div class="user-badge">
        <div class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
        <div class="info">
          <div>{{ auth()->user()->name }}</div>
          <small>{{ auth()->user()->position ?? auth()->user()->roleLabel() }}</small>
        </div>
      </div>

      <form method="POST" action="{{ route('logout') }}" style="display:inline;">
        @csrf
        <button type="submit" class="btn-header" title="ออกจากระบบ">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span class="hide-sm">ออก</span>
        </button>
      </form>
    @else
      <a href="{{ route('login') }}" class="btn-header">
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>เข้าสู่ระบบ</span>
      </a>
    @endauth
  </div>
</header>

@auth
<nav class="subnav no-print">
  <div class="subnav-inner">
    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
      <i class="fa-solid fa-gauge-high"></i> หน้าหลัก
    </a>
    <a href="{{ route('assets.index') }}" class="nav-link {{ request()->routeIs('assets.*') && request('review_status') !== 'pending' ? 'active' : '' }}">
      <i class="fa-solid fa-clipboard-list"></i> ทะเบียนคุมครุภัณฑ์
    </a>
    <a href="{{ route('assets.index', ['review_status' => 'pending']) }}" class="nav-link {{ request()->routeIs('assets.index') && request('review_status') === 'pending' ? 'active' : '' }}">
      <i class="fa-solid fa-hourglass-half"></i> รอจัดสรรตำแหน่ง
      @if($pendingReviewCount > 0)
        <span class="badge badge-repair" style="padding:2px 8px;">{{ $pendingReviewCount }}</span>
      @endif
    </a>
    <a href="{{ route('scan.index') }}" class="nav-link {{ request()->routeIs('scan.*') ? 'active' : '' }}">
      <i class="fa-solid fa-qrcode"></i> สแกน QR / เช็คของ
    </a>
    <a href="{{ route('stock-checks.index') }}" class="nav-link {{ request()->routeIs('stock-checks.*') ? 'active' : '' }}">
      <i class="fa-solid fa-clipboard-check"></i> ตรวจนับสต็อก
    </a>
    <a href="{{ route('maintenances.index') }}" class="nav-link {{ request()->routeIs('maintenances.*') ? 'active' : '' }}">
      <i class="fa-solid fa-screwdriver-wrench"></i> แจ้งซ่อม
      @if($openRepairCount > 0)
        <span class="badge badge-lost" style="padding:2px 8px;">{{ $openRepairCount }}</span>
      @endif
    </a>
    <a href="{{ route('movements.index') }}" class="nav-link {{ request()->routeIs('movements.*') ? 'active' : '' }}">
      <i class="fa-solid fa-arrows-rotate"></i> โอนย้าย/จำหน่าย
    </a>
    <a href="{{ route('qr.print') }}" class="nav-link {{ request()->routeIs('qr.print') ? 'active' : '' }}">
      <i class="fa-solid fa-print"></i> พิมพ์ป้าย QR ทั้งหมด
    </a>
    <a href="{{ route('assets.checklist') }}" class="nav-link {{ request()->routeIs('assets.checklist') ? 'active' : '' }}">
      <i class="fa-solid fa-list-check"></i> พิมพ์ใบรายการติด QR
    </a>
    <a href="{{ route('assets.bulk-edit') }}" class="nav-link {{ request()->routeIs('assets.bulk-edit') ? 'active' : '' }}">
      <i class="fa-solid fa-table-cells"></i> คีย์ผู้รับผิดชอบ/ตำแหน่ง
    </a>
    <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
      <i class="fa-solid fa-file-excel"></i> รายงาน / Excel
    </a>
  </div>
</nav>
@endauth

<main>
  @if(session('success'))
    <div class="alert alert-success no-print">
      <i class="fa-solid fa-circle-check"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger no-print">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger no-print">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>
        <strong>กรุณาตรวจสอบข้อมูล:</strong>
        <ul style="margin:4px 0 0 18px;">
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

  @yield('content')
</main>

<footer class="app-footer no-print">
  © {{ now()->year + 543 }} ระบบควบคุมครุภัณฑ์และตรวจนับสต็อก QR Code | สำนักปลัด องค์การบริหารส่วนตำบลฝางคำ อำเภอสิรินธร จังหวัดอุบลราชธานี
</footer>

<div class="toast no-print" id="toast">
  <i class="fa-solid fa-circle-check"></i>
  <span id="toastMsg">สำเร็จ</span>
</div>

<script>
function showToast(msg, isError = false) {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toastMsg');
  if (!toast || !toastMsg) return;
  toastMsg.textContent = msg;
  toast.style.background = isError ? '#ef4444' : '#10b981';
  toast.style.boxShadow = isError
    ? '0 8px 24px rgba(239,68,68,0.4)'
    : '0 8px 24px rgba(16,185,129,0.4)';
  toast.querySelector('i').className = isError
    ? 'fa-solid fa-circle-exclamation'
    : 'fa-solid fa-circle-check';
  toast.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
}
</script>
@stack('scripts')
</body>
</html>
