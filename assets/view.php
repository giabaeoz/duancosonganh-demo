<?php
require __DIR__ . '/../includes/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$a = fetch_one(q(
  $conn,
  "SELECT a.*, c.ten AS loai, l.ten AS vitri
   FROM assets a
   LEFT JOIN categories c ON c.id = a.category_id
   LEFT JOIN locations l ON l.id = a.location_id
   WHERE a.id = ?",
  'i',
  $id
));
if (!$a) {
  http_response_code(404);
  die('Không tìm thấy tài sản');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  csrf_check();
  $ttMoi = $_POST['trang_thai'] ?? '';
  if (!array_key_exists($ttMoi, TRANG_THAI_TS)) {
    flash('Trạng thái không hợp lệ', 'danger');
  } else {
    q($conn, "UPDATE assets SET trang_thai = ? WHERE id = ?", 'si', $ttMoi, $id);
    flash("Đã cập nhật trạng thái tài sản thành: " . TRANG_THAI_TS[$ttMoi][0], 'success');
  }
  redirect('/assets/view.php?id=' . $id);
}

$loans = fetch_all(q(
  $conn,
  "SELECT lo.*, b.ho_ten AS nguoi_muon, u.ho_ten AS nv_giao
   FROM loans lo
   JOIN borrowers b ON b.id = lo.borrower_id
   JOIN users u ON u.id = lo.staff_giao_id
   WHERE lo.asset_id = ? ORDER BY lo.id DESC",
  'i',
  $id
));

$trans = fetch_all(q(
  $conn,
  "SELECT t.*, f.ten AS tu_vitri, d.ten AS den_vitri
   FROM transfers t
   LEFT JOIN locations f ON f.id = t.from_location_id
   JOIN locations d ON d.id = t.to_location_id
   WHERE t.asset_id = ? ORDER BY t.id DESC",
  'i',
  $id
));

$title = 'Chi tiết ' . $a['ma_tai_san'];
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById("qr"), {
    text: ' . json_encode($a['ma_tai_san']) . ',
    width: 170, height: 170,
    colorDark: "#0f172a",
    colorLight: "#ffffff"
  });
</script>';
include __DIR__ . '/../includes/header.php';

$coAnh = !empty($a['hinh_anh']) && file_exists(__DIR__ . '/../uploads/assets/images/' . $a['hinh_anh']);
?>
<style>
  @media print {
    body * {
      visibility: hidden;
    }
    #tem, #tem * {
      visibility: visible;
    }
    #tem {
      position: absolute;
      left: 0;
      top: 0;
      border: 1px solid #000000 !important;
    }
  }
  #tem {
    background-color: #ffffff;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 1rem;
    display: inline-block;
  }
  #qr img {
    margin: 0 auto;
  }
</style>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="list.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> Quay lại</a>
    <h3 class="mb-0"><?= e($a['ten']) ?></h3>
    <div><?= badge_ts($a['trang_thai']) ?></div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTrangThai">
      <i class="bi bi-arrow-repeat"></i> Cập nhật trạng thái
    </button>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-sm btn-primary">
      <i class="bi bi-pencil-square"></i> Sửa tài sản
    </a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card card-body h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-1"></i> Thông tin chi tiết tài sản</h6>
      <table class="table align-middle">
        <tbody>
          <tr>
            <th style="width:28%" class="text-muted">Mã tài sản</th>
            <td><strong class="text-primary font-monospace fs-6"><?= e($a['ma_tai_san']) ?></strong></td>
          </tr>
          <tr>
            <th class="text-muted">Tên tài sản</th>
            <td class="fw-semibold text-dark"><?= e($a['ten']) ?></td>
          </tr>
          <tr>
            <th class="text-muted">Phân loại</th>
            <td><?= e($a['loai'] ?: 'Chưa phân loại') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Vị trí lưu kho</th>
            <td><i class="bi bi-geo-alt text-danger me-1"></i><?= e($a['vitri'] ?: 'Chưa gán vị trí') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Ngày sử dụng</th>
            <td><?= e($a['ngay_su_dung'] ?: 'Chưa cập nhật') ?></td>
          </tr>
          <tr>
            <th class="text-muted">Mô tả</th>
            <td><?= nl2br(e($a['mo_ta'] ?: 'Không có mô tả')) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Thẻ hình ảnh tài sản -->
    <div class="card card-body text-center mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-image text-primary me-1"></i> Hình ảnh thực tế</h6>
      <?php if ($coAnh): ?>
        <img src="<?= BASE_URL ?>/uploads/assets/images/<?= e($a['hinh_anh']) ?>" alt="<?= e($a['ten']) ?>" 
             class="img-fluid rounded border p-1" style="max-height: 200px; width: 100%; object-fit: contain; background: #ffffff;">
      <?php else: ?>
        <div class="p-4 bg-light text-muted border rounded text-center">
          <i class="bi bi-card-image fs-1 text-secondary d-block mb-1"></i>
          <span class="small">Chưa có hình ảnh tài sản</span>
        </div>
      <?php endif; ?>
    </div>

    <!-- Thẻ mã QR in tem -->
    <div class="card card-body text-center">
      <h6 class="fw-bold mb-3"><i class="bi bi-qr-code text-primary me-1"></i> Tem mã QR</h6>
      <div id="tem" class="mb-3">
        <div id="qr"></div>
        <div class="mt-2 fw-bold font-monospace text-dark"><?= e($a['ma_tai_san']) ?></div>
      </div>
      <div>
        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> In tem QR
        </button>
      </div>
    </div>
  </div>
</div>

<div class="mt-4">
  <h6 class="fw-bold mb-2"><i class="bi bi-clock-history text-primary me-1"></i> Lịch sử mượn – trả</h6>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Người mượn</th>
          <th>Phòng mượn</th>
          <th>Thời gian mượn</th>
          <th>Hạn trả</th>
          <th>Thời gian trả</th>
          <th>Tình trạng khi trả</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loans as $l): ?>
          <tr>
            <td class="fw-semibold text-dark"><?= e($l['nguoi_muon']) ?></td>
            <td>
              <?php if (!empty($l['phong_muon'])): ?>
                <span class="badge bg-light text-dark border font-monospace"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($l['phong_muon']) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= e($l['ngay_muon']) ?></td>
            <td><?= e($l['han_tra']) ?></td>
            <td><?= e($l['ngay_tra'] ?? '—') ?></td>
            <td><?= e($l['tinh_trang_khi_tra'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">Chưa có lịch sử mượn trả</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="mt-4 mb-4">
  <h6 class="fw-bold mb-2"><i class="bi bi-arrow-left-right text-primary me-1"></i> Lịch sử bàn giao / điều chuyển</h6>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Thời điểm</th>
          <th>Người giao</th>
          <th>Người nhận</th>
          <th>Từ vị trí</th>
          <th>Đến vị trí</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($trans as $t): ?>
          <tr>
            <td><?= e($t['thoi_diem']) ?></td>
            <td><?= e($t['nguoi_giao']) ?></td>
            <td class="fw-semibold text-dark"><?= e($t['nguoi_nhan']) ?></td>
            <td><?= e($t['tu_vitri'] ?: '—') ?></td>
            <td><?= e($t['den_vitri']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$trans): ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Chưa có lịch sử điều chuyển</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal cập nhật trạng thái nhanh -->
<div class="modal fade" id="modalTrangThai" tabindex="-1" aria-labelledby="modalTrangThaiLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_status">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTrangThaiLabel"><i class="bi bi-arrow-repeat text-primary me-2"></i> Cập nhật trạng thái tài sản</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Tài sản: <strong><?= e($a['ten']) ?></strong> (<span class="font-monospace text-primary"><?= e($a['ma_tai_san']) ?></span>)</p>
          <label class="form-label fw-semibold">Chọn trạng thái mới:</label>
          <div class="d-flex flex-column gap-2">
            <?php foreach (TRANG_THAI_TS as $key => [$tenTT, $mau]): ?>
              <label class="list-group-item d-flex align-items-center gap-3 p-3 border rounded cursor-pointer <?= ($a['trang_thai'] === $key) ? 'border-primary bg-light' : '' ?>">
                <input class="form-check-input flex-shrink-0" type="radio" name="trang_thai" value="<?= $key ?>" <?= ($a['trang_thai'] === $key) ? 'checked' : '' ?>>
                <span class="flex-grow-1">
                  <span class="badge text-bg-<?= $mau ?> me-2"><?= e($tenTT) ?></span>
                  <span class="small text-muted d-block mt-1">
                    <?= $key === 'san_sang' ? 'Sẵn sàng trong kho để bàn giao/cho mượn' : ($key === 'dang_muon' ? 'Đang được người mượn giữ' : ($key === 'bao_tri' ? 'Hư hỏng, đang bảo dưỡng/sửa chữa' : 'Đã thanh lý hoặc ngừng hoạt động')) ?>
                  </span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Xác nhận lưu</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>