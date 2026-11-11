@extends('layouts.app')

@section('title', 'ลงทะเบียนครุภัณฑ์ใหม่ | ระบบควบคุมครุภัณฑ์')

@section('content')
  <div class="panel">
    <div class="section-title">
      <div class="title-left"><i class="fa-solid fa-plus"></i> ลงทะเบียนครุภัณฑ์ใหม่ (รหัส QR อัตโนมัติ: {{ $nextToken }})</div>
      <a href="{{ route('assets.index') }}" class="btn btn-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> กลับทะเบียนคุม</a>
    </div>

    <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data">
      @csrf
      @include('assets._form')

      <div style="margin-top:22px;display:flex;justify-content:flex-end;gap:10px;">
        <a href="{{ route('assets.index') }}" class="btn btn-outline">ยกเลิก</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> บันทึกครุภัณฑ์และสร้าง QR</button>
      </div>
    </form>
  </div>
@endsection
