<?php
require __DIR__ . '/../includes/init.php';
require_login();

$loc = $_GET['loc'] ?? 'dang_muon';   // dang_muon | qua_han | tat_ca
$where = '1=1';
if ($loc === 'dang_muon') $where = "lo.trang_thai = 'dang_muon'";
if ($loc === 'qua_han')   $where = "lo.trang_thai = 'dang_muon' AND lo.han_tra < NOW()";

$ds = fetch_all(q(
  $conn,
  "SELECT lo.*, a.ma_tai_san, a.ten AS ten_ts, b.ho_ten AS nguoi_muon
   FROM loans lo JOIN assets a ON a.id = lo.asset_id JOIN borrowers b ON b.id = lo.borrower_id
   WHERE $where ORDER BY lo.id DESC"
));

$title = 'Danh sách mượn – trả';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-clock-history text-primary me-2"></i> Lịch sử mượn – trả</h3>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-primary" href="borrow.php"><i class="bi bi-plus-lg"></i> Mượn mới</a>
    <a class="btn btn-sm btn-outline-primary" href="return.php"><i class="bi bi-box-arrow-in-down-left"></i> Nhận trả</a>
  </div>
</div>

<div class="d-flex gap-2 mb-3">
  <a class="btn btn-sm <?= $loc === 'dang_muon' ? 'btn-primary' : 'btn-secondary' ?>" href="?loc=dang_muon">
    <i class="bi bi-hourglass-split"></i> Đang mượn
  </a>
  <a class="btn btn-sm <?= $loc === 'qua_han' ? 'btn-danger' : 'btn-secondary' ?>" href="?loc=qua_han">
    <i class="bi bi-exclamation-triangle"></i> Quá hạn
  </a>
  <a class="btn btn-sm <?= $loc === 'tat_ca' ? 'btn-primary' : 'btn-secondary' ?>" href="?loc=tat_ca">
    <i class="bi bi-list-check"></i> Tất cả
  </a>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Mã &amp; Tên tài sản</th>
        <th>Người mượn</th>
        <th>Phòng mượn</th>
        <th>Thời gian mượn</th>
        <th>Hạn trả</th>
        <th>Thời gian trả</th>
        <th>Trạng thái</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ds as $l):
        $quaHan = $l['trang_thai'] === 'dang_muon' && strtotime($l['han_tra']) < time(); 
        [$nhanTT, $mauPill] = $l['trang_thai'] === 'da_tra' ? ['Đã trả', 'green'] : ($quaHan ? ['Quá hạn', 'red'] : ['Đang mượn', 'amber']);
      ?>
      <tr>
        <td>
          <strong class="text-dark font-monospace"><?= e($l['ma_tai_san']) ?></strong>
          <div class="text-muted small"><?= e($l['ten_ts']) ?></div>
        </td>
        <td class="fw-semibold text-dark"><?= e($l['nguoi_muon']) ?></td>
        <td>
          <?php if (!empty($l['phong_muon'])): ?>
            <span class="badge bg-light text-dark border font-monospace"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($l['phong_muon']) ?></span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
        <td><?= date('d/m/Y H:i', strtotime($l['ngay_muon'])) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($l['han_tra'])) ?></td>
        <td><?= $l['ngay_tra'] ? date('d/m/Y H:i', strtotime($l['ngay_tra'])) : '<span class="text-muted">—</span>' ?></td>
        <td><span class="pill <?= $mauPill ?>"><?= $nhanTT ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$ds): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Không có giao dịch nào trong danh mục này</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>