<?php
$dangNhap = !empty($_SESSION['user_id']);

function nav_item($href, $icon, $text, $match = null)
{
  $m = BASE_URL . ($match ?? $href);
  $active = (strpos($_SERVER['SCRIPT_NAME'], $m) === 0) ? ' active' : '';
?>
  <a class="item<?= $active ?>" href="<?= BASE_URL . $href ?>"><i class="bi <?= $icon ?>"></i> <span><?= $text ?></span></a>
<?php
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'QLTS-QR') ?> · QLTS-QR</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/static/css/style.css?v=<?= @filemtime(__DIR__ . '/../static/css/style.css') ?>" rel="stylesheet">
</head>
<body class="<?= $dangNhap ? '' : 'guest' ?>">
<?php if ($dangNhap): ?>
<aside class="sidebar" id="sidebar">
  <a class="brand" href="<?= BASE_URL ?>/index.php">
    <div class="brand-icon"><i class="bi bi-qr-code-scan"></i></div>
    <div class="brand-text">
      <span class="brand-title">QLTS-QR</span>
      <span class="brand-sub">Quản lý tài sản</span>
    </div>
  </a>

  <div class="nav-label">Chung</div>
  <?php nav_item('/index.php', 'bi-grid-1x2-fill', 'Tổng quan'); ?>
  <?php nav_item('/assets/list.php', 'bi-box-seam', 'Tài sản', '/assets/'); ?>

  <div class="nav-label">Nghiệp vụ</div>
  <?php nav_item('/loans/borrow.php', 'bi-box-arrow-up-right', 'Mượn tài sản'); ?>
  <?php nav_item('/loans/return.php', 'bi-box-arrow-in-down-left', 'Trả tài sản'); ?>
  <?php nav_item('/loans/list.php', 'bi-clock-history', 'Danh sách mượn – trả'); ?>
  <?php nav_item('/transfers/create.php', 'bi-arrow-left-right', 'Điều chuyển', '/transfers/'); ?>
  <?php nav_item('/inventory/start.php', 'bi-clipboard-check', 'Kiểm kê', '/inventory/'); ?>

  <div class="nav-label">Hệ thống</div>
  <?php nav_item('/borrowers.php', 'bi-people', 'Người mượn'); ?>
  <?php nav_item('/profile.php', 'bi-person-badge', 'Thông tin tài khoản'); ?>
  <?php nav_item('/scan.php', 'bi-qr-code-scan', 'Quét QR thử'); ?>

  <div class="user">
    <a href="<?= BASE_URL ?>/profile.php" class="avatar text-decoration-none" title="Xem thông tin tài khoản"><?= e(mb_strtoupper(mb_substr($_SESSION['ho_ten'], 0, 1))) ?></a>
    <div class="user-info">
      <a href="<?= BASE_URL ?>/profile.php" class="user-name text-decoration-none text-dark" title="Xem thông tin tài khoản"><?= e($_SESSION['ho_ten']) ?></a>
      <div class="user-role"><?= $_SESSION['role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?></div>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</aside>
<main class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button class="btn btn-menu" id="btnMenu" type="button" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
      <div class="topbar-title">
        <i class="bi bi-folder2-open text-primary me-1"></i>
        <span><?= e($title ?? 'QLTS-QR') ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <div class="topbar-chip d-none d-sm-inline-flex">
        <i class="bi bi-calendar-event text-secondary"></i>
        <span><?= date('d/m/Y') ?></span>
      </div>
      <a href="<?= BASE_URL ?>/profile.php" class="topbar-chip user-chip text-decoration-none text-dark" title="Xem thông tin tài khoản">
        <span class="user-dot"></span>
        <span><?= e($_SESSION['ho_ten']) ?></span>
      </a>
    </div>
  </div>
  <div class="page">
<?php else: ?>
<div class="guest-wrapper">
  <div class="container guest-container">
<?php endif; ?>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm mb-4">
    <i class="bi <?= $f['type'] === 'success' ? 'bi-check-circle-fill text-success' : ($f['type'] === 'danger' ? 'bi-exclamation-triangle-fill text-danger' : 'bi-info-circle-fill text-primary') ?> fs-5"></i>
    <div><?= e($f['msg']) ?></div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>