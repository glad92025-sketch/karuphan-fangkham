<div class="form-grid">
  <div class="form-group">
    <label class="form-label" for="asset_code">รหัสพัสดุ / ครุภัณฑ์</label>
    <input type="text" id="asset_code" name="asset_code" class="form-control"
           value="{{ old('asset_code', $asset->asset_code ?? '') }}" placeholder="เช่น 400-69-0044">
  </div>

  <div class="form-group">
    <label class="form-label" for="fiscal_year">ปีงบประมาณ *</label>
    <input type="number" id="fiscal_year" name="fiscal_year" class="form-control" required
           value="{{ old('fiscal_year', $asset->fiscal_year ?? 2569) }}">
  </div>

  <div class="form-group full">
    <label class="form-label" for="name">ชื่อพัสดุ / ครุภัณฑ์ *</label>
    <input type="text" id="name" name="name" class="form-control" required
           value="{{ old('name', $asset->name ?? '') }}" placeholder="เช่น เครื่องคอมพิวเตอร์ All In One">
  </div>

  <div class="form-group full">
    <label class="form-label" for="specification">ยี่ห้อ / แบบ / ชนิด / ลักษณะ</label>
    <textarea id="specification" name="specification" class="form-control" rows="2"
              placeholder="รายละเอียดรุ่น ยี่ห้อ ขนาด สี หมายเลขเครื่อง">{{ old('specification', $asset->specification ?? '') }}</textarea>
  </div>

  <div class="form-group">
    <label class="form-label" for="category">หมวดหมู่ครุภัณฑ์</label>
    <input type="text" id="category" name="category" list="catList" class="form-control"
           value="{{ old('category', $asset->category ?? '') }}" placeholder="เช่น ครุภัณฑ์สำนักงาน">
    <datalist id="catList">
      @foreach($categories as $cat)
        <option value="{{ $cat }}">
      @endforeach
    </datalist>
  </div>

  <div class="form-group">
    <label class="form-label" for="price">ราคา (บาท) *</label>
    <input type="number" step="0.01" min="0" id="price" name="price" class="form-control" required
           value="{{ old('price', $asset->price ?? 0) }}">
  </div>

  <div class="form-group">
    <label class="form-label" for="acquired_date_text">วันที่ได้มา (รูปแบบไทย)</label>
    <input type="text" id="acquired_date_text" name="acquired_date_text" class="form-control"
           value="{{ old('acquired_date_text', $asset->acquired_date_text ?? '') }}" placeholder="เช่น 14/9/2569 หรือ 18 มิ.ย. 2542">
  </div>

  <div class="form-group">
    <label class="form-label" for="acquired_date">วันที่ได้มา (ปฏิทิน ค.ศ.)</label>
    <input type="date" id="acquired_date" name="acquired_date" class="form-control"
           value="{{ old('acquired_date', isset($asset) && $asset->acquired_date ? $asset->acquired_date->toDateString() : '') }}">
  </div>

  <div class="form-group">
    <label class="form-label" for="responsible_person">ผู้รับผิดชอบ (ชื่อผู้ถือครอง / หน่วยงาน)</label>
    <input type="text" id="responsible_person" name="responsible_person" list="respList" class="form-control"
           value="{{ old('responsible_person', $asset->responsible_person ?? 'สำนักปลัด') }}"
           placeholder="เช่น นายสมชาย ใจดี หรือ สำนักปลัด">
    <datalist id="respList">
      @foreach($responsibles as $resp)
        <option value="{{ $resp }}">
      @endforeach
    </datalist>
  </div>

  <div class="form-group">
    <label class="form-label" for="position_title">ตำแหน่ง</label>
    <input type="text" id="position_title" name="position_title" list="posList" class="form-control"
           value="{{ old('position_title', $asset->position_title ?? '') }}"
           placeholder="เช่น หัวหน้าสำนักปลัด, นักทรัพยากรบุคคล, ผช.ธุรการ">
    <datalist id="posList">
      @foreach($positions as $pos)
        <option value="{{ $pos }}">
      @endforeach
    </datalist>
  </div>

  <div class="form-group">
    <label class="form-label" for="location">สถานที่ตั้ง / ห้อง</label>
    <input type="text" id="location" name="location" class="form-control"
           value="{{ old('location', $asset->location ?? 'สำนักปลัด อบต.ฝางคำ') }}">
  </div>

  <div class="form-group">
    <label class="form-label" for="quantity">จำนวน *</label>
    <input type="number" min="1" id="quantity" name="quantity" class="form-control" required
           value="{{ old('quantity', $asset->quantity ?? 1) }}">
  </div>

  <div class="form-group">
    <label class="form-label" for="unit">หน่วยนับ *</label>
    <input type="text" id="unit" name="unit" class="form-control" required
           value="{{ old('unit', $asset->unit ?? 'หน่วย') }}">
  </div>

  <div class="form-group">
    <label class="form-label" for="status">สภาพโดยทั่วไป *</label>
    <select id="status" name="status" class="form-select" required>
      @foreach(\App\Models\Asset::STATUS_LABELS as $k => $label)
        <option value="{{ $k }}" {{ old('status', $asset->status ?? 'usable') === $k ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="form-group full">
    <label class="form-label" for="photo">ภาพถ่ายครุภัณฑ์ (ถ่ายจากมือถือหรืออัปโหลดรูป)</label>
    @if(isset($asset) && $asset->photo_path)
      <div style="margin-bottom:8px;">
        <img src="{{ asset('storage/' . $asset->photo_path) }}" alt="{{ $asset->name }}"
             style="max-height:140px;border-radius:10px;border:1px solid var(--border);">
      </div>
    @endif
    <input type="file" id="photo" name="photo" class="form-control" accept="image/*" capture="environment">
  </div>

  <div class="form-group full">
    <label class="form-label" for="note">หมายเหตุเพิ่มเติม</label>
    <textarea id="note" name="note" class="form-control" rows="2">{{ old('note', $asset->note ?? '') }}</textarea>
  </div>
</div>
