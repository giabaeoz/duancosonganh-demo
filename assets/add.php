<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$loaiDs = fetch_all(q($conn, "SELECT * FROM categories ORDER BY ten"));
$viTriDs = fetch_all(q($conn, "SELECT * FROM locations ORDER BY ten"));
$loi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ma   = strtoupper(trim($_POST['ma_tai_san'] ?? ''));
  $ten  = trim($_POST['ten'] ?? '');
  $cat  = (int)($_POST['category_id'] ?? 0) ?: null;
  $customCat = trim($_POST['custom_category'] ?? '');

  if (($_POST['category_id'] ?? '') === '__custom__' || $customCat !== '') {
    if ($customCat !== '') {
      $existCat = fetch_one(q($conn, "SELECT id FROM categories WHERE LOWER(ten) = LOWER(?)", 's', $customCat));
      if ($existCat) {
        $cat = (int)$existCat['id'];
      } else {
        q($conn, "INSERT INTO categories (ten) VALUES (?)", 's', $customCat);
        $cat = (int)$conn->insert_id;
      }
    } else {
      $cat = null;
    }
  }

  $loc  = (int)($_POST['location_id'] ?? 0) ?: null;
  $ngay = ($_POST['ngay_su_dung'] ?? '') ?: null;
  $tt   = $_POST['trang_thai'] ?? 'san_sang';
  $mota = trim($_POST['mo_ta'] ?? '');

  if ($ma === '' || $ten === '') {
    $loi = 'Mã và tên tài sản là bắt buộc';
  } elseif (!preg_match('/^[A-Z0-9\-]{3,30}$/', $ma)) {
    $loi = 'Mã chỉ gồm chữ, số, dấu gạch ngang (3–30 ký tự)';
  } elseif (!array_key_exists($tt, TRANG_THAI_TS) || $tt === 'dang_muon') {
    $loi = 'Trạng thái không hợp lệ';
  } else {
    $tenHinhAnh = null;
    if (!empty($_FILES['hinh_anh']['name']) && $_FILES['hinh_anh']['error'] === UPLOAD_ERR_OK) {
      $fileTmp  = $_FILES['hinh_anh']['tmp_name'];
      $fileName = $_FILES['hinh_anh']['name'];
      $fileSize = $_FILES['hinh_anh']['size'];

      $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
      $duoiHopLe = ['jpg', 'jpeg', 'png', 'webp'];

      if (!in_array($ext, $duoiHopLe)) {
        $loi = 'Chỉ chấp nhận file ảnh: JPG, JPEG, PNG, WEBP';
      } elseif ($fileSize > 5 * 1024 * 1024) {
        $loi = 'Dung lượng file ảnh không được vượt quá 5MB';
      } else {
        $dirUpload = __DIR__ . '/../uploads/assets/images/';
        if (!is_dir($dirUpload)) {
          mkdir($dirUpload, 0777, true);
        }
        $tenHinhAnh = 'ts_' . preg_replace('/[^A-Za-z0-9_-]/', '', $ma) . '_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($fileTmp, $dirUpload . $tenHinhAnh)) {
          $loi = 'Không thể lưu file ảnh vào thư mục uploads/assets/images';
          $tenHinhAnh = null;
        }
      }
    }

    if (!$loi) {
      try {
        q(
          $conn,
          "INSERT INTO assets (ma_tai_san, ten, category_id, location_id, ngay_su_dung, trang_thai, mo_ta, hinh_anh)
           VALUES (?,?,?,?,?,?,?,?)",
          'ssiissss',
          $ma,
          $ten,
          $cat,
          $loc,
          $ngay,
          $tt,
          $mota,
          $tenHinhAnh
        );
        $id = $conn->insert_id;
        flash('Đã thêm tài sản ' . $ma, 'success');
        redirect('/assets/view.php?id=' . $id);
      } catch (mysqli_sql_exception $ex) {
        if ($tenHinhAnh && file_exists(__DIR__ . '/../uploads/assets/images/' . $tenHinhAnh)) {
          unlink(__DIR__ . '/../uploads/assets/images/' . $tenHinhAnh);
        }
        $loi = ($ex->getCode() == 1062) ? 'Mã tài sản đã tồn tại' : 'Lỗi hệ thống: ' . $ex->getMessage();
      }
    }
  }
}

$title = 'Thêm tài sản';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
    <h3 class="mb-0"><i class="bi bi-plus-circle text-primary me-1"></i> Thêm tài sản mới</h3>
  </div>
</div>

<?php if ($loi): ?>
  <div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div><?= e($loi) ?></div>
  </div>
<?php endif; ?>

<div class="card card-body">
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Mã tài sản *</label>
        <input name="ma_tai_san" class="form-control font-monospace" placeholder="VD: TS-000001" value="<?= e($_POST['ma_tai_san'] ?? '') ?>" required>
        <div class="form-text small text-muted">Chữ in hoa, số, dấu gạch ngang</div>
      </div>
      <div class="col-md-8">
        <label class="form-label">Tên tài sản *</label>
        <input name="ten" class="form-control" placeholder="VD: Laptop Dell Inspiron 15" value="<?= e($_POST['ten'] ?? '') ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="category_select">Phân loại</label>
        <select name="category_id" id="category_select" class="form-select" onchange="handleCategoryChange(this)">
          <option value="">-- Chọn loại tài sản --</option>
          <?php foreach ($loaiDs as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($_POST['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= e($c['ten']) ?></option>
          <?php endforeach; ?>
          <option value="__custom__" <?= (($_POST['category_id'] ?? '') === '__custom__' || !empty($_POST['custom_category'])) ? 'selected' : '' ?>>
            + Nhập phân loại mới...
          </option>
        </select>

        <div id="customCategoryBox" class="mt-2" style="<?= (($_POST['category_id'] ?? '') === '__custom__' || !empty($_POST['custom_category'])) ? 'display:block;' : 'display:none;' ?>">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-primary"><i class="bi bi-tag-fill"></i></span>
            <input type="text" name="custom_category" id="custom_category" class="form-control" placeholder="Nhập tên phân loại mới..." value="<?= e($_POST['custom_category'] ?? '') ?>">
            <button type="button" class="btn btn-outline-secondary" onclick="cancelCustomCategory()" title="Hủy"><i class="bi bi-x-lg"></i></button>
          </div>
          <div class="form-text small text-muted">Phân loại mới sẽ được tự động lưu vào hệ thống.</div>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label">Vị trí lưu kho / phòng</label>
        <select name="location_id" class="form-select">
          <option value="">-- Chọn vị trí --</option>
          <?php foreach ($viTriDs as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['ten']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Ngày đưa vào sử dụng</label>
        <input type="date" name="ngay_su_dung" class="form-control" value="<?= e($_POST['ngay_su_dung'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">Trạng thái ban đầu</label>
        <select name="trang_thai" class="form-select">
          <option value="san_sang">Sẵn sàng</option>
          <option value="bao_tri">Cần bảo trì</option>
          <option value="ngung_su_dung">Ngừng sử dụng</option>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label">Hình ảnh tài sản</label>
        <input type="file" name="hinh_anh" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewImage(this)">
        <div class="form-text small text-muted">Hỗ trợ JPG, PNG, WEBP (tối đa 5MB)</div>
      </div>

      <div class="col-12" id="previewContainer" style="display:none;">
        <label class="form-label text-muted small">Xem trước ảnh:</label>
        <div>
          <img id="imgPreview" src="" alt="Xem trước" class="rounded border p-1" style="max-height: 180px; max-width: 250px; object-fit: contain; background: #ffffff;">
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Mô tả / Thông số kỹ thuật</label>
        <textarea name="mo_ta" class="form-control" rows="3" placeholder="Nhập cấu hình, đặc điểm, phụ kiện đi kèm..."><?= e($_POST['mo_ta'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4 pt-3 border-top">
      <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Lưu tài sản</button>
      <a href="list.php" class="btn btn-secondary px-3">Hủy</a>
    </div>
  </form>
</div>

<script>
function previewImage(input) {
  const container = document.getElementById('previewContainer');
  const preview = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      container.style.display = 'block';
    }
    reader.readAsDataURL(input.files[0]);
  } else {
    container.style.display = 'none';
  }
}

function handleCategoryChange(sel) {
  const box = document.getElementById('customCategoryBox');
  const input = document.getElementById('custom_category');
  if (sel.value === '__custom__') {
    box.style.display = 'block';
    input.focus();
  } else {
    box.style.display = 'none';
    input.value = '';
  }
}

function cancelCustomCategory() {
  const sel = document.getElementById('category_select');
  sel.value = '';
  handleCategoryChange(sel);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>