@extends('layouts.app')

@section('title', 'แก้ไขครุภัณฑ์ ' . $asset->displayCode() . ' | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="panel">
    <div class="section-title">
      <div class="title-left"><i class="fa-solid fa-pen-to-square"></i> แก้ไขข้อมูลครุภัณฑ์ ({{ $asset->displayCode() }} / {{ $asset->qr_token }})</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('scan.show', ['token' => $asset->qr_token]) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-qrcode"></i> หน้าสแกน QR
        </a>
        <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> กลับหน้ารายละเอียด
        </a>
      </div>
    </div>

    <form method="POST" action="{{ route('assets.update', $asset) }}" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <input type="hidden" name="return_to" value="{{ $returnTo ?? '' }}">
      @include('assets._form', ['asset' => $asset])

      <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;">
        <a href="{{ ($returnTo ?? '') === 'scan' ? route('scan.show', ['token' => $asset->qr_token]) : route('assets.show', $asset) }}" class="btn btn-outline">ยกเลิก</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกการเปลี่ยนแปลง</button>
      </div>
    </form>
  </div>
@endsection
