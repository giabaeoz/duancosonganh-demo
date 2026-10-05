<?php
require __DIR__ . '/../includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $loc = (int)($_POST['location_id'] ?? 0);
  if (!fetch_one(q($conn, "SELECT id FROM locations WHERE id = ?", 'i', $loc))) {
    flash('Khu vực không hợp lệ', 'danger');
    redirect('/inventory/start.php');
  }
  q($conn, "INSERT INTO inventory_sessions (location_id, staff_id, bat_dau) VALUES (?,?,NOW())", 'ii', $loc, $_SESSION['user_id']);
  redirect('/inventory/scan.php?id=' . $conn->insert_id);
}

$viTriDs = fetch_all(q($conn, "SELECT * FROM locations ORDER BY ten"));
$phien = fetch_all(q(
  $conn,
  "SELECT s.*, l.ten AS khu_vuc, u.ho_ten FROM inventory_sessions s
   JOIN locations l ON l.id = s.location_id JOIN users u ON u.id = s.staff_id
   ORDER BY s.id DESC LIMIT 30"
));

$title = 'Kiểm kê';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h3><i class="bi bi-clipboard-check text-primary me-2"></i> Kiểm kê tài sản</h3>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h6 class="fw-bold mb-3"><i class="bi bi-play-circle text-primary me-1"></i> Khởi tạo phiên kiểm kê mới</h6>
    <form method="POST" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-8">
        <label class="form-label">Chọn khu vực / phòng cần kiểm kê *</label>
        <select name="location_id" class="form-select" required>
          <option value="">-- Chọn khu vực --</option>
          <?php foreach ($viTriDs as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['ten']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button class="btn btn-primary w-100 py-2">
          <i class="bi bi-qr-code-scan"></i> Bắt đầu kiểm kê
        </button>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Mã phiên</th>
        <th>Khu vực kiểm kê</th>
        <th>Người kiểm kê</th>
        <th>Thời gian bắt đầu</th>
        <th>Trạng thái</th>
        <th class="text-end">Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($phien as $s): 
        $isDangKiem = $s['trang_thai'] === 'dang_kiem';
      ?>
        <tr>
          <td><span class="badge bg-light text-dark border">#<?= $s['id'] ?></span></td>
          <td class="fw-semibold text-dark"><i class="bi bi-geo-alt text-danger me-1"></i><?= e($s['khu_vuc']) ?></td>
          <td><?= e($s['ho_ten']) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($s['bat_dau'])) ?></td>
          <td>
            <span class="pill <?= $isDangKiem ? 'amber' : 'green' ?>">
              <?= $isDangKiem ? 'Đang kiểm' : 'Hoàn tất' ?>
            </span>
          </td>
          <td class="text-end">
            <a class="btn btn-sm <?= $isDangKiem ? 'btn-primary' : 'btn-outline-primary' ?>"
              href="<?= $isDangKiem ? 'scan.php' : 'report.php' ?>?id=<?= $s['id'] ?>">
              <i class="bi <?= $isDangKiem ? 'bi-qr-code-scan' : 'bi-file-earmark-text' ?>"></i>
              <?= $isDangKiem ? 'Tiếp tục quét' : 'Xem kết quả' ?>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$phien): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có phiên kiểm kê nào</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>