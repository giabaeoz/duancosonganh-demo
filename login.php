<?php
require __DIR__ . '/includes/init.php';

if (!empty($_SESSION['user_id'])) {
  redirect('/index.php');
}

$loi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $tk = trim($_POST['tai_khoan'] ?? '');
  $mk = $_POST['mat_khau'] ?? '';

  $user = fetch_one(q($conn, "SELECT * FROM users WHERE tai_khoan = ?", 's', $tk));

  if ($user && password_verify($mk, $user['mat_khau'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['ho_ten']  = $user['ho_ten'];
    $_SESSION['role']    = $user['role'];
    redirect('/index.php');
  }
  $loi = 'Sai tài khoản hoặc mật khẩu';
}

$title = 'Đăng nhập';
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <div class="brand-logo">
    <i class="bi bi-qr-code-scan"></i>
  </div>
  <h2>QLTS-QR</h2>
  <div class="sub-text">Hệ thống quản lý mượn trả &amp; kiểm kê tài sản</div>

  <?php if ($loi): ?>
    <div class="alert alert-danger mb-3">
      <i class="bi bi-exclamation-circle-fill"></i>
      <div><?= e($loi) ?></div>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="tai_khoan">Tài khoản</label>
      <input id="tai_khoan" name="tai_khoan" class="form-control" placeholder="Tên đăng nhập" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label" for="mat_khau">Mật khẩu</label>
      <input id="mat_khau" type="password" name="mat_khau" class="form-control" placeholder="Mật khẩu" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2">
      <i class="bi bi-box-arrow-in-right"></i> Đăng nhập hệ thống
    </button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>