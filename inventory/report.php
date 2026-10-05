<?php
require __DIR__ . '/../includes/init.php';
require_login();

$sid = (int)($_GET['id'] ?? 0);
$s = fetch_one(q(
  $conn,
  "SELECT s.*, l.ten AS khu_vuc FROM inventory_sessions s JOIN locations l ON l.id = s.location_id WHERE s.id = ?",
  'i',
  $sid
));
if (!$s) {
  http_response_code(404);
  die('Không tìm thấy phiên kiểm kê');
}

// Đã kiểm kê đúng vị trí
$dung = fetch_all(q(
  $conn,
  "SELECT a.ma_tai_san, a.ten FROM inventory_items i JOIN assets a ON a.id = i.asset_id
   WHERE i.session_id = ? AND i.ket_qua = 'dung_vi_tri'",
  'i',
  $sid
));

// Xuất hiện sai vị trí (hiển thị vị trí DB đang ghi)
$sai = fetch_all(q(
  $conn,
  "SELECT a.ma_tai_san, a.ten, l.ten AS vitri_db FROM inventory_items i
   JOIN assets a ON a.id = i.asset_id LEFT JOIN locations l ON l.id = a.location_id
   WHERE i.session_id = ? AND i.ket_qua = 'sai_vi_tri'",
  'i',
  $sid
));

// Thuộc khu vực nhưng KHÔNG được quét
$thieu = fetch_all(q(
  $conn,
  "SELECT a.ma_tai_san, a.ten, a.trang_thai FROM assets a
   WHERE a.location_id = ?
     AND a.id NOT IN (SELECT asset_id FROM inventory_items WHERE session_id = ?)",
  'ii',
  $s['location_id'],
  $sid
));

$title = 'Báo cáo kiểm kê';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="mb-1"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i> Kết quả kiểm kê: <?= e($s['khu_vuc']) ?></h3>
    <div class="text-muted small">
      Phiên #<?= $sid ?> · 
      Bắt đầu: <?= date('d/m/Y H:i', strtotime($s['bat_dau'])) ?> · 
      Kết thúc: <?= $s['ket_thuc'] ? date('d/m/Y H:i', strtotime($s['ket_thuc'])) : '<span class="text-warning">Đang tiến hành</span>' ?>
    </div>
  </div>
  <a href="start.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Danh sách phiên</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card stat">
      <div class="ico green"><i class="bi bi-check-circle"></i></div>
      <div>
        <div class="num text-success"><?= count($dung) ?></div>
        <div class="lbl">Đúng vị trí</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat">
      <div class="ico amber"><i class="bi bi-exclamation-triangle"></i></div>
      <div>
        <div class="num text-warning"><?= count($sai) ?></div>
        <div class="lbl">Sai vị trí (thừa ở đây)</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card stat">
      <div class="ico red"><i class="bi bi-x-circle"></i></div>
      <div>
        <div class="num text-danger"><?= count($thieu) ?></div>
        <div class="lbl">Chưa tìm thấy (thiếu)</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card card-body h-100">
      <h6 class="fw-bold text-success mb-3"><i class="bi bi-check2-circle me-1"></i> Khớp đúng vị trí (<?= count($dung) ?>)</h6>
      <ul class="list-group list-group-flush border rounded-3">
        <?php foreach ($dung as $r): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><strong class="font-monospace"><?= e($r['ma_tai_san']) ?></strong> - <?= e($r['ten']) ?></span>
            <i class="bi bi-check text-success fs-5"></i>
          </li>
        <?php endforeach; ?>
        <?php if (!$dung): ?><li class="list-group-item text-muted text-center py-3">Không có tài sản nào</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-body h-100">
      <h6 class="fw-bold text-warning mb-3"><i class="bi bi-exclamation-diamond me-1"></i> Sai vị trí (<?= count($sai) ?>)</h6>
      <ul class="list-group list-group-flush border rounded-3">
        <?php foreach ($sai as $r): ?>
          <li class="list-group-item">
            <div><strong class="font-monospace"><?= e($r['ma_tai_san']) ?></strong> - <?= e($r['ten']) ?></div>
            <div class="small text-muted mt-1">Vị trí trong hệ thống: <span class="badge bg-light text-dark border"><?= e($r['vitri_db'] ?: 'Chưa gán') ?></span></div>
          </li>
        <?php endforeach; ?>
        <?php if (!$sai): ?><li class="list-group-item text-muted text-center py-3">Không có tài sản sai vị trí</li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-body h-100">
      <h6 class="fw-bold text-danger mb-3"><i class="bi bi-x-octagon me-1"></i> Chưa quét được (<?= count($thieu) ?>)</h6>
      <ul class="list-group list-group-flush border rounded-3">
        <?php foreach ($thieu as $r): ?>
          <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <span><strong class="font-monospace"><?= e($r['ma_tai_san']) ?></strong> - <?= e($r['ten']) ?></span>
              <?= $r['trang_thai'] === 'dang_muon' ? '<span class="pill amber">Đang mượn</span>' : '<span class="pill red">Thiếu</span>' ?>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if (!$thieu): ?><li class="list-group-item text-muted text-center py-3">Tất cả tài sản đã được kiểm kê đầy đủ</li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>