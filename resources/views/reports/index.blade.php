@extends('layouts.app')

@section('title', 'รายงานและนำเข้า/ส่งออก Excel | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
      <div class="stat-value">{{ number_format($totalCount) }}</div>
      <div class="stat-label">ครุภัณฑ์สำนักปลัดทั้งหมด</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="fa-solid fa-coins"></i></div>
      <div class="stat-value">{{ number_format($totalValue, 2) }}</div>
      <div class="stat-label">มูลค่าทรัพย์สินรวม (บาท)</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
      <div class="stat-value">{{ number_format($pendingCount) }}</div>
      <div class="stat-label">รายการรอจัดสรรตำแหน่ง</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
      <div class="stat-value">{{ number_format($repairTotalCost, 2) }}</div>
      <div class="stat-label">ค่าซ่อมบำรุงสะสม (บาท)</div>
    </div>
  </div>

  <div class="two-col-grid">
    <div>
      <!-- By Year & Status -->
      <div class="panel">
        <div class="section-title">
          <div class="title-left"><i class="fa-solid fa-calendar-check"></i> สรุปตามปีงบประมาณ &amp; สภาพครุภัณฑ์</div>
          <a href="{{ route('reports.export') }}" class="btn btn-success btn-sm">
            <i class="fa-solid fa-file-excel"></i> ดาวน์โหลดทะเบียนทั้งหมด (.xlsx)
          </a>
        </div>

        <div class="form-grid">
          <div class="table-responsive">
            <table>
              <thead><tr><th>ปีงบประมาณ</th><th style="text-align:right;">จำนวน</th><th style="text-align:right;">มูลค่า (บาท)</th></tr></thead>
              <tbody>
                @foreach($byYear as $y)
                  <tr>
                    <td><strong>ปีงบประมาณ {{ $y->fiscal_year }}</strong></td>
                    <td style="text-align:right;">{{ number_format($y->qty) }}</td>
                    <td style="text-align:right;">{{ number_format((float)$y->amount, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="table-responsive">
            <table>
              <thead><tr><th>สภาพครุภัณฑ์</th><th style="text-align:right;">จำนวน</th><th style="text-align:right;">มูลค่า (บาท)</th></tr></thead>
              <tbody>
                @foreach($byStatus as $st)
                  <tr>
                    <td>{{ \App\Models\Asset::STATUS_LABELS[$st->status] ?? $st->status }}</td>
                    <td style="text-align:right;">{{ number_format($st->qty) }}</td>
                    <td style="text-align:right;">{{ number_format((float)$st->amount, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- By Category -->
      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-layer-group"></i> สรุปแยกตามหมวดหมู่ครุภัณฑ์</div></div>
        <div class="table-responsive">
          <table>
            <thead><tr><th>หมวดหมู่</th><th style="text-align:right;">จำนวน (รายการ)</th><th style="text-align:right;">มูลค่ารวม (บาท)</th><th style="text-align:right;">ส่งออกเฉพาะหมวด</th></tr></thead>
            <tbody>
              @foreach($byCategory as $cat)
                <tr>
                  <td><strong>{{ $cat->label }}</strong></td>
                  <td style="text-align:right;">{{ number_format($cat->qty) }}</td>
                  <td style="text-align:right;">{{ number_format((float)$cat->amount, 2) }}</td>
                  <td style="text-align:right;">
                    <a href="{{ route('reports.export', ['category' => $cat->label]) }}" class="btn btn-outline btn-sm">
                      <i class="fa-solid fa-download"></i> Excel
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <!-- By Responsible Person / Position -->
      <div class="panel">
        <div class="section-title"><div class="title-left"><i class="fa-solid fa-users"></i> สรุปแยกตามผู้รับผิดชอบ / ชื่อตำแหน่ง</div></div>
        <div class="table-responsive">
          <table>
            <thead><tr><th>ผู้รับผิดชอบ / ตำแหน่ง</th><th style="text-align:right;">จำนวน (รายการ)</th><th style="text-align:right;">มูลค่ารวม (บาท)</th><th style="text-align:right;">ดูรายการ</th></tr></thead>
            <tbody>
              @foreach($byResponsible as $resp)
                <tr>
                  <td><span class="badge badge-blue">{{ $resp->label }}</span></td>
                  <td style="text-align:right;font-weight:600;">{{ number_format($resp->qty) }}</td>
                  <td style="text-align:right;">{{ number_format((float)$resp->amount, 2) }}</td>
                  <td style="text-align:right;">
                    <a href="{{ route('assets.index', ['responsible_person' => $resp->label]) }}" class="btn btn-outline btn-sm">ดูครุภัณฑ์</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right: Import / Export Tools -->
    <div class="qr-panel">
      <div class="qr-panel-header">
        <h3><i class="fa-solid fa-file-excel"></i> นำเข้า / ส่งออก Excel</h3>
        <p>ซิงค์ข้อมูลจากไฟล์ตารางครุภัณฑ์ .xlsx</p>
      </div>

      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
        <a href="{{ route('reports.export') }}" class="btn btn-success" style="width:100%;">
          <i class="fa-solid fa-file-arrow-down"></i> ส่งออกทะเบียนสำนักปลัด (.xlsx)
        </a>
        <a href="{{ route('reports.export', ['fiscal_year' => 2569]) }}" class="btn btn-outline" style="width:100%;">
          <i class="fa-solid fa-download"></i> ส่งออกเฉพาะครุภัณฑ์เพิ่มปี 2569
        </a>
        @foreach($stockChecks as $sc)
          <a href="{{ route('stock-checks.export', $sc) }}" class="btn btn-outline" style="width:100%;">
            <i class="fa-solid fa-clipboard-check"></i> รายงานตรวจนับ {{ $sc->code }}
          </a>
        @endforeach
      </div>

      @if(auth()->user()->isAdmin())
      <div style="padding-top:16px;border-top:1px solid var(--border);">
        <h4 style="font-size:15px;margin-bottom:8px;"><i class="fa-solid fa-file-arrow-up" style="color:var(--primary);"></i> นำเข้า/อัปเดตจากไฟล์ Excel</h4>
        <p style="font-size:12.5px;color:var(--text-light);margin-bottom:10px;">
          รองรับไฟล์รูปแบบเดียวกับ <code>ตารางครุภัณฑ์69_สำนักปลัด.xlsx</code> โดยระบบจะอัปเดตข้อมูลเดิมและเพิ่มรายการใหม่อัตโนมัติโดยไม่ลบ QR เดิม
        </p>
        <form method="POST" action="{{ route('reports.import') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-group" style="margin-bottom:10px;">
            <input type="file" name="excel_file" accept=".xlsx" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;">
            <i class="fa-solid fa-upload"></i> นำเข้าไฟล์ Excel
          </button>
        </form>
      </div>
      @endif
    </div>
  </div>
@endsection
