<?php
require __DIR__ . '/includes/init.php';
require_login();
$loi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ma = strtoupper(trim($_POST['ma_so'] ?? ''));
  $ten = trim($_POST['ho_ten'] ?? '');
  $lh = trim($_POST['lien_he'] ?? '');
  if ($ma === '' || $ten === '') {
    $loi = 'Mã số và họ tên là bắt buộc';
  } else {
    try {
      q($conn, "INSERT INTO borrowers (ma_so, ho_ten, lien_he) VALUES (?,?,?)", 'sss', $ma, $ten, $lh);
      flash('Đã thêm người mượn ' . $ten, 'success');
      redirect('/borrowers.php');
    } catch (mysqli_sql_exception $ex) {
      $loi = ($ex->getCode() == 1062) ? 'Mã số người mượn đã tồn tại' : $ex->getMessage();
    }
  }
}
$ds = fetch_all(q($conn, "SELECT * FROM borrowers ORDER BY id DESC"));
$title = 'Người mượn';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h3><i class="bi bi-people text-primary me-2"></i> Quản lý người mượn</h3>
</div>

<?php if ($loi): ?>
  <div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div><?= e($loi) ?></div>
  </div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-body">
    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus text-primary me-1"></i> Thêm người mượn mới</h6>
    <form method="POST" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-3">
        <label class="form-label">Mã SV / Mã NV</label>
        <input name="ma_so" class="form-control font-monospace" placeholder="VD: SV202601" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Họ và tên</label>
        <input name="ho_ten" class="form-control" placeholder="Họ và tên đầy đủ" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Số ĐT / Email</label>
        <input name="lien_he" class="form-control" placeholder="Liên hệ">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100 py-2">
          <i class="bi bi-plus-lg"></i> Thêm
        </button>
      </div>
    </form>
  </div>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Mã định danh</th>
        <th>Họ tên</th>
        <th>Thông tin liên hệ</th>
        <th>Ngày tạo</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ds as $b): ?>
        <tr>
          <td><span class="badge bg-light text-dark border font-monospace"><?= e($b['ma_so']) ?></span></td>
          <td class="fw-semibold text-dark"><?= e($b['ho_ten']) ?></td>
          <td><?= e($b['lien_he'] ?: '—') ?></td>
          <td class="text-muted small"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ds): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">Chưa có người mượn nào trong danh sách</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>