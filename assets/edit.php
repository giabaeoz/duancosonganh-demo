<?php
require __DIR__ . '/../includes/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$a = fetch_one(q($conn, "SELECT * FROM assets WHERE id = ?", 'i', $id));
if (!$a) {
  http_response_code(404);
  die('Không tìm thấy tài sản');
}

$loaiDs = fetch_all(q($conn, "SELECT * FROM categories ORDER BY ten"));
$viTriDs = fetch_all(q($conn, "SELECT * FROM locations ORDER BY ten"));
$loi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ten  = trim($_POST['ten'] ?? '');
  $cat  = (int)($_POST['category_id'] ?? 0) ?: null;
  $customCat = trim($_POST['custom_category'] ?? '');

  // Xử lý phân loại tùy ý nếu người dùng tự nhập
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
  $tt   = $_POST['trang_thai'] ?? $a['trang_thai'];
  $mota = trim($_POST['mo_ta'] ?? '');

  if ($ten === '') {
    $loi = 'Tên tài sản là bắt buộc';
  } elseif (!array_key_exists($tt, TRANG_THAI_TS)) {
    $loi = 'Trạng thái không hợp lệ';
  } else {
    $tenHinhAnh = $a['hinh_anh'];
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
        $tenHinhMoi = 'ts_' . preg_replace('/[^A-Za-z0-9_-]/', '', $a['ma_tai_san']) . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($fileTmp, $dirUpload . $tenHinhMoi)) {
          if (!empty($a['hinh_anh']) && file_exists($dirUpload . $a['hinh_anh'])) {
            @unlink($dirUpload . $a['hinh_anh']);
          }
          $tenHinhAnh = $tenHinhMoi;
        } else {
          $loi = 'Không thể lưu file ảnh vào thư mục uploads/assets/images';
        }
      }
    }

    if (!$loi) {
      try {
        q(
          $conn,
          "UPDATE assets 
           SET ten = ?, category_id = ?, location_id = ?, ngay_su_dung = ?, trang_thai = ?, mo_ta = ?, hinh_anh = ?
           WHERE id = ?",
          'siissssi',
          $ten,
          $cat,
          $loc,
          $ngay,
          $tt,
          $mota,
          $tenHinhAnh,
          $id
        );
        flash('Đã cập nhật thông tin và trạng thái tài sản ' . $a['ma_tai_san'], 'success');
        redirect('/assets/view.php?id=' . $id);
      } catch (mysqli_sql_exception $ex) {
        $loi = 'Lỗi hệ thống: ' . $ex->getMessage();
      }
    }
  }
}

$title = 'Chỉnh sửa ' . $a['ma_tai_san'];
include __DIR__ . '/../includes/header.php';
$coAnh = !empty($a['hinh_anh']) && file_exists(__DIR__ . '/../uploads/assets/images/' . $a['hinh_anh']);
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div class="d-flex align-items-center gap-2">
    <a href="view.php?id=<?= $id ?>" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại chi tiết</a>
    <h3 class="mb-0"><i class="bi bi-pencil-square text-primary me-1"></i> Cập nhật thông tin &amp; Trạng thái tài sản</h3>
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
        <label class="form-label text-muted">Mã tài sản (Không đổi)</label>
        <input class="form-control font-monospace bg-light" value="<?= e($a['ma_tai_san']) ?>" readonly>
      </div>
      <div class="col-md-8">
        <label class="form-label">Tên tài sản</label>
        <input name="ten" class="form-control" value="<?= e($_POST['ten'] ?? $a['ten']) ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Trạng thái tài sản</label>
        <select name="trang_thai" class="form-select fw-semibold">
          <?php foreach (TRANG_THAI_TS as $key => [$tenTT, $mau]): ?>
            <option value="<?= $key ?>" <?= (($_POST['trang_thai'] ?? $a['trang_thai']) === $key) ? 'selected' : '' ?>>
              <?= e($tenTT) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($a['trang_thai'] === 'dang_muon'): ?>
          <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Lưu ý: Tài sản đang ở trạng thái mượn</div>
        <?php endif; ?>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="category_select">Phân loại</label>
        <select name="category_id" id="category_select" class="form-select" onchange="handleCategoryChange(this)">
          <option value="">-- Chọn loại tài sản --</option>
          <?php foreach ($loaiDs as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($a['category_id'] == $c['id']) ? 'selected' : '') ?>><?= e($c['ten']) ?></option>
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
          <?php foreach ($viTriDs as $l): ?>
            <option value="<?= $l['id'] ?>" <?= (($a['location_id'] == $l['id']) ? 'selected' : '') ?>><?= e($l['ten']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Ngày đưa vào sử dụng</label>
        <input type="date" name="ngay_su_dung" class="form-control" value="<?= e($_POST['ngay_su_dung'] ?? $a['ngay_su_dung']) ?>">
      </div>

      <div class="col-md-8">
        <label class="form-label">Hình ảnh tài sản</label>
        <input type="file" name="hinh_anh" class="form-control" accept="image/png, image/jpeg, image/jpg, image/webp" onchange="previewImage(this)">
        <div class="form-text small text-muted">Hỗ trợ JPG, PNG, WEBP (tối đa 5MB) — Bỏ qua nếu giữ nguyên ảnh hiện tại</div>
      </div>

      <div class="col-12" id="previewContainer">
        <label class="form-label text-muted small">Ảnh tài sản:</label>
        <div>
          <?php if ($coAnh): ?>
            <img id="imgPreview" src="<?= BASE_URL ?>/uploads/assets/images/<?= e($a['hinh_anh']) ?>" alt="Ảnh hiện tại" class="rounded border p-1" style="max-height: 180px; max-width: 250px; object-fit: contain; background: #ffffff;">
          <?php else: ?>
            <img id="imgPreview" src="" alt="Xem trước" class="rounded border p-1" style="display:none; max-height: 180px; max-width: 250px; object-fit: contain; background: #ffffff;">
            <div id="noImgText" class="text-muted small italic">Chưa có hình ảnh</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Mô tả / Thông số kỹ thuật</label>
        <textarea name="mo_ta" class="form-control" rows="3"><?= e($_POST['mo_ta'] ?? $a['mo_ta']) ?></textarea>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-4 pt-3 border-top">
      <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Lưu cập nhật</button>
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary px-3">Hủy</a>
    </div>
  </form>
</div>

<script>
function previewImage(input) {
  const preview = document.getElementById('imgPreview');
  const noImg = document.getElementById('noImgText');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
      if (noImg) noImg.style.display = 'none';
    }
    reader.readAsDataURL(input.files[0]);
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
