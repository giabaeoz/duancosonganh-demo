<?php
require __DIR__ . '/../includes/init.php';
require_login();

$tu = trim($_GET['q'] ?? '');
$like = "%$tu%";
$ds = fetch_all(q(
  $conn,
  "SELECT a.*, c.ten AS loai, l.ten AS vitri
   FROM assets a
   LEFT JOIN categories c ON c.id = a.category_id
   LEFT JOIN locations  l ON l.id = a.location_id
   WHERE a.ma_tai_san LIKE ? OR a.ten LIKE ?
   ORDER BY a.id DESC",
  'ss',
  $like,
  $like
));

$title = 'Tài sản';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3><i class="bi bi-box-seam text-primary me-2"></i> Danh sách tài sản</h3>
  <?php if ($_SESSION['role'] === 'admin'): ?>
    <a class="btn btn-primary" href="add.php"><i class="bi bi-plus-lg"></i> Thêm tài sản</a>
  <?php endif; ?>
</div>

<form class="mb-3" method="GET">
  <input name="q" value="<?= e($tu) ?>" class="form-control" placeholder="Tìm theo mã hoặc tên tài sản (gõ rồi nhấn Enter)...">
</form>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th style="width: 60px;">Ảnh</th>
        <th>Mã tài sản</th>
        <th>Tên tài sản</th>
        <th>Phân loại</th>
        <th>Vị trí</th>
        <th>Trạng thái</th>
        <th class="text-end">Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ds as $a): 
        $coAnh = !empty($a['hinh_anh']) && file_exists(__DIR__ . '/../uploads/assets/images/' . $a['hinh_anh']);
      ?>
        <tr>
          <td>
            <?php if ($coAnh): ?>
              <img src="<?= BASE_URL ?>/uploads/assets/images/<?= e($a['hinh_anh']) ?>" alt="<?= e($a['ten']) ?>" 
                   class="rounded border" style="width: 44px; height: 44px; object-fit: cover; background: #ffffff;">
            <?php else: ?>
              <div class="rounded border bg-light text-muted d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-image text-secondary"></i>
              </div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-dark border font-monospace"><?= e($a['ma_tai_san']) ?></span></td>
          <td class="fw-semibold text-dark"><?= e($a['ten']) ?></td>
          <td><?= e($a['loai'] ?: '—') ?></td>
          <td><i class="bi bi-geo-alt text-danger me-1"></i><?= e($a['vitri'] ?: '—') ?></td>
          <td><?= badge_ts($a['trang_thai']) ?></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a class="btn btn-outline-primary" href="view.php?id=<?= (int)$a['id'] ?>" title="Xem chi tiết & mã QR">
                <i class="bi bi-qr-code"></i> Chi tiết
              </a>
              <a class="btn btn-outline-secondary" href="edit.php?id=<?= (int)$a['id'] ?>" title="Chỉnh sửa thông tin & trạng thái">
                <i class="bi bi-pencil"></i> Sửa
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$ds): ?><tr>
          <td colspan="7" class="text-center text-muted py-4">Không tìm thấy tài sản nào phù hợp</td>
        </tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>