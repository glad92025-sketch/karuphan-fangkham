<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เข้าสู่ระบบ | ระบบควบคุมครุภัณฑ์ สำนักปลัด อบต.ฝางคำ</title>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
  <style>
    body {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 55%, #3b82f6 100%);
      justify-content: center;
      align-items: center;
      padding: 24px;
    }
    .login-card {
      background: white;
      border-radius: 22px;
      box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28);
      max-width: 440px;
      width: 100%;
      padding: 36px 32px;
    }
    .login-brand {
      text-align: center;
      margin-bottom: 24px;
    }
    .login-brand .badge-icon {
      width: 68px;
      height: 68px;
      border-radius: 18px;
      background: linear-gradient(135deg, #1e3a8a, #3b82f6);
      color: white;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 30px;
      margin-bottom: 12px;
      box-shadow: 0 10px 24px rgba(30, 64, 175, 0.3);
    }
    .login-brand h1 { font-size: 22px; color: var(--text); margin-bottom: 4px; }
    .login-brand p { font-size: 13.5px; color: var(--text-light); }
    .demo-box {
      margin-top: 20px;
      background: #f8fafc;
      border: 1px dashed var(--primary-light);
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 13px;
      color: var(--text);
    }
    .demo-btn {
      margin-top: 8px;
      padding: 5px 10px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: white;
      font-family: inherit;
      font-size: 12.5px;
      cursor: pointer;
      color: var(--primary);
      font-weight: 600;
    }
    .demo-btn:hover { background: #eff6ff; border-color: var(--primary-light); }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-brand">
    <div class="badge-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
    <h1>ระบบควบคุมครุภัณฑ์ &amp; QR Stock</h1>
    <p>สำนักปลัด องค์การบริหารส่วนตำบลฝางคำ</p>
  </div>

  @if(session('success'))
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>
  @endif

  <form method="POST" action="{{ route('login.attempt') }}">
    @csrf
    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label" for="email">อีเมลผู้ใช้งาน</label>
      <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus placeholder="admin@fangkham.go.th">
    </div>

    <div class="form-group" style="margin-bottom:14px;">
      <label class="form-label" for="password">รหัสผ่าน</label>
      <input id="password" type="password" name="password" class="form-control" required placeholder="••••••••">
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13.5px;margin-bottom:18px;cursor:pointer;">
      <input type="checkbox" name="remember" value="1" checked> จดจำการเข้าสู่ระบบ
    </label>

    <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;">
      <i class="fa-solid fa-right-to-bracket"></i> เข้าสู่ระบบ
    </button>
  </form>

  <div class="demo-box">
    <div><strong>บัญชีทดสอบระบบ (พร้อมใช้งาน):</strong></div>
    <div style="margin-top:4px;color:var(--text-light);">
      • เจ้าหน้าที่พัสดุ (Admin): <code>admin@fangkham.go.th</code> / <code>password</code><br>
      • กรรมการตรวจนับ (Staff): <code>staff@fangkham.go.th</code> / <code>password</code>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <button type="button" class="demo-btn" onclick="fillDemo('admin@fangkham.go.th','password')">เติมบัญชีพัสดุ</button>
      <button type="button" class="demo-btn" onclick="fillDemo('staff@fangkham.go.th','password')">เติมบัญชีตรวจนับ</button>
    </div>
  </div>
</div>

<script>
function fillDemo(email, pass) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = pass;
}
</script>
</body>
</html>
