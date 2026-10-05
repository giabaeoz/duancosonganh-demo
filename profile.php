<?php
require __DIR__ . '/includes/init.php';
require_login();

$uid = (int)$_SESSION['user_id'];
$u = fetch_one(q($conn, "SELECT * FROM users WHERE id = ?", 'i', $uid));
if (!$u) {
  redirect('/logout.php');
}

$loi = '';
$thanhCong = '';

// Xử lý các thao tác form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // 1. Cập nhật họ tên
  if ($action === 'update_profile') {
    $hoTen = trim($_POST['ho_ten'] ?? '');
    if ($hoTen === '') {
      $loi = 'Họ và tên không được để trống';
    } else {
      q($conn, "UPDATE users SET ho_ten = ? WHERE id = ?", 'si', $hoTen, $uid);
      $_SESSION['ho_ten'] = $hoTen;
      $u['ho_ten'] = $hoTen;
      flash('Cập nhật thông tin tài khoản thành công', 'success');
      redirect('/profile.php');
    }
  }

  // 2. Đổi mật khẩu
  elseif ($action === 'change_password') {
    $mkCu = $_POST['mat_khau_cu'] ?? '';
    $mkMoi = $_POST['mat_khau_moi'] ?? '';
    $mkXacNhan = $_POST['mat_khau_xac_nhan'] ?? '';

    if (!password_verify($mkCu, $u['mat_khau'])) {
      $loi = 'Mật khẩu hiện tại không chính xác';
    } elseif (mb_strlen($mkMoi) < 6) {
      $loi = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } elseif ($mkMoi !== $mkXacNhan) {
      $loi = 'Mật khẩu xác nhận không khớp với mật khẩu mới';
    } else {
      $hash = password_hash($mkMoi, PASSWORD_DEFAULT);
      q($conn, "UPDATE users SET mat_khau = ? WHERE id = ?", 'si', $hash, $uid);
      flash('Đổi mật khẩu thành công. Vui lòng ghi nhớ mật khẩu mới.', 'success');
      redirect('/profile.php');
    }
  }

  // 3. Quản trị viên thêm tài khoản mới
  elseif ($action === 'create_user' && $_SESSION['role'] === 'admin') {
    $tk = trim($_POST['tai_khoan'] ?? '');
    $ten = trim($_POST['ho_ten'] ?? '');
    $mk = $_POST['mat_khau'] ?? '';
    $role = $_POST['role'] ?? 'staff';

    if ($tk === '' || $ten === '' || $mk === '') {
      $loi = 'Vui lòng nhập đầy đủ tài khoản, họ tên và mật khẩu';
    } elseif (!in_array($role, ['admin', 'staff'], true)) {
      $loi = 'Vai trò không hợp lệ';
    } elseif (mb_strlen($mk) < 6) {
      $loi = 'Mật khẩu phải từ 6 ký tự trở lên';
    } else {
      $exist = fetch_one(q($conn, "SELECT id FROM users WHERE tai_khoan = ?", 's', $tk));
      if ($exist) {
        $loi = "Tên tài khoản \"$tk\" đã tồn tại trên hệ thống";
      } else {
        $hash = password_hash($mk, PASSWORD_DEFAULT);
        q($conn, "INSERT INTO users (ho_ten, tai_khoan, mat_khau, role) VALUES (?,?,?,?)", 'ssss', $ten, $tk, $hash, $role);
        flash("Đã tạo tài khoản người dùng \"$tk\" thành công", 'success');
        redirect('/profile.php#tab-users');
      }
    }
  }

  // 4. Quản trị viên đổi mật khẩu cho người khác
  elseif ($action === 'reset_password' && $_SESSION['role'] === 'admin') {
    $targetId = (int)($_POST['target_user_id'] ?? 0);
    $mkMoi = $_POST['mat_khau_moi'] ?? '';
    if (mb_strlen($mkMoi) < 6) {
      $loi = 'Mật khẩu mới phải từ 6 ký tự trở lên';
    } else {
      $targetUser = fetch_one(q($conn, "SELECT tai_khoan FROM users WHERE id = ?", 'i', $targetId));
      if ($targetUser) {
        $hash = password_hash($mkMoi, PASSWORD_DEFAULT);
        q($conn, "UPDATE users SET mat_khau = ? WHERE id = ?", 'si', $hash, $targetId);
        flash("Đã đặt lại mật khẩu cho tài khoản {$targetUser['tai_khoan']}", 'success');
        redirect('/profile.php#tab-users');
      }
    }
  }
}

// Thống kê hoạt động của tài khoản này
$soGiao = fetch_one(q($conn, "SELECT COUNT(*) n FROM loans WHERE staff_giao_id = ?", 'i', $uid))['n'] ?? 0;
$soNhan = fetch_one(q($conn, "SELECT COUNT(*) n FROM loans WHERE staff_nhan_id = ?", 'i', $uid))['n'] ?? 0;
$soKiemKe = fetch_one(q($conn, "SELECT COUNT(*) n FROM inventory_sessions WHERE staff_id = ?", 'i', $uid))['n'] ?? 0;
$soDieuChuyen = fetch_one(q($conn, "SELECT COUNT(*) n FROM transfers WHERE staff_id = ?", 'i', $uid))['n'] ?? 0;

// Nếu là admin, lấy danh sách tất cả tài khoản
$allUsers = [];
if ($_SESSION['role'] === 'admin') {
  $allUsers = fetch_all(q($conn, "SELECT id, ho_ten, tai_khoan, role, created_at FROM users ORDER BY id ASC"));
}

$title = 'Thông tin tài khoản';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h3><i class="bi bi-person-circle text-primary me-2"></i> Thông tin tài khoản</h3>
  <span class="badge <?= $u['role'] === 'admin' ? 'text-bg-primary' : 'text-bg-secondary' ?> fs-6 px-3 py-2">
    <i class="bi <?= $u['role'] === 'admin' ? 'bi-shield-check' : 'bi-person-badge' ?> me-1"></i>
    <?= $u['role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên thiết bị' ?>
  </span>
</div>

<?php if ($loi): ?>
  <div class="alert alert-danger mb-4">
    <i class="bi bi-exclamation-circle-fill"></i>
    <div><?= e($loi) ?></div>
  </div>
<?php endif; ?>

<!-- Thẻ tổng quan người dùng -->
<div class="card mb-4">
  <div class="card-body p-4">
    <div class="d-flex flex-column flex-md-row align-items-center gap-4">
      <div class="avatar rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" 
           style="width: 80px; height: 80px; font-size: 2rem; background: #2563eb;">
        <?= e(mb_strtoupper(mb_substr($u['ho_ten'], 0, 1))) ?>
      </div>
      <div class="flex-grow-1 text-center text-md-start">
        <h4 class="mb-1 text-dark fw-bold"><?= e($u['ho_ten']) ?></h4>
        <div class="text-muted small mb-2">
          <span class="me-3"><i class="bi bi-at text-primary"></i> Tài khoản: <strong class="font-monospace text-dark"><?= e($u['tai_khoan']) ?></strong></span>
          <span class="me-3"><i class="bi bi-calendar3 text-primary"></i> Tham gia: <strong><?= date('d/m/Y', strtotime($u['created_at'])) ?></strong></span>
          <span><i class="bi bi-dot text-success fs-5 align-middle"></i><span class="text-success fw-semibold">Đang hoạt động</span></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Khối thống kê hoạt động -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card card-body text-center p-3">
      <div class="text-muted small mb-1"><i class="bi bi-box-arrow-up-right text-primary me-1"></i>Giao mượn</div>
      <div class="fs-4 fw-bold text-dark"><?= number_format($soGiao) ?></div>
      <div class="small text-muted" style="font-size: 0.75rem;">Lượt đã bàn giao</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-body text-center p-3">
      <div class="text-muted small mb-1"><i class="bi bi-box-arrow-in-down-left text-success me-1"></i>Nhận trả</div>
      <div class="fs-4 fw-bold text-dark"><?= number_format($soNhan) ?></div>
      <div class="small text-muted" style="font-size: 0.75rem;">Lượt tiếp nhận trả</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-body text-center p-3">
      <div class="text-muted small mb-1"><i class="bi bi-arrow-left-right text-warning me-1"></i>Điều chuyển</div>
      <div class="fs-4 fw-bold text-dark"><?= number_format($soDieuChuyen) ?></div>
      <div class="small text-muted" style="font-size: 0.75rem;">Lượt điều chuyển</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card card-body text-center p-3">
      <div class="text-muted small mb-1"><i class="bi bi-clipboard-check text-info me-1"></i>Kiểm kê</div>
      <div class="fs-4 fw-bold text-dark"><?= number_format($soKiemKe) ?></div>
      <div class="small text-muted" style="font-size: 0.75rem;">Đợt kiểm kê</div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Cột bên trái: Cập nhật thông tin -->
  <div class="col-lg-6">
    <div class="card card-body h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i> Thông tin cá nhân</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="mb-3">
          <label class="form-label text-muted small">Tên đăng nhập (Cố định)</label>
          <input class="form-control font-monospace bg-light" value="<?= e($u['tai_khoan']) ?>" readonly disabled>
          <div class="form-text small text-muted">Tên tài khoản dùng để đăng nhập vào hệ thống</div>
        </div>

        <div class="mb-3">
          <label class="form-label text-muted small">Vai trò người dùng</label>
          <input class="form-control bg-light" value="<?= $u['role'] === 'admin' ? 'Quản trị viên (Admin)' : 'Nhân viên thiết bị (Staff)' ?>" readonly disabled>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Họ và tên hiển thị</label>
          <input name="ho_ten" class="form-control" value="<?= e($u['ho_ten']) ?>" required>
          <div class="form-text small text-muted">Tên sẽ được hiển thị trên phiếu mượn, biên bản kiểm kê và thanh điều hướng</div>
        </div>

        <button class="btn btn-primary px-4">
          <i class="bi bi-check2"></i> Lưu họ và tên
        </button>
      </form>
    </div>
  </div>

  <!-- Cột bên phải: Đổi mật khẩu -->
  <div class="col-lg-6">
    <div class="card card-body h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i> Đổi mật khẩu</h6>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="mb-3">
          <label class="form-label fw-semibold">Mật khẩu hiện tại</label>
          <input type="password" name="mat_khau_cu" class="form-control" placeholder="Nhập mật khẩu hiện tại của bạn" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Mật khẩu mới</label>
          <input type="password" name="mat_khau_moi" class="form-control" placeholder="Mật khẩu mới (tối thiểu 6 ký tự)" required minlength="6">
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Xác nhận mật khẩu mới</label>
          <input type="password" name="mat_khau_xac_nhan" class="form-control" placeholder="Nhập lại mật khẩu mới" required minlength="6">
        </div>

        <button class="btn btn-outline-primary px-4">
          <i class="bi bi-key"></i> Đổi mật khẩu
        </button>
      </form>
    </div>
  </div>
</div>

<?php if ($_SESSION['role'] === 'admin'): ?>
<!-- Dành riêng cho Quản trị viên: Quản lý danh sách tài khoản -->
<div class="card mt-4" id="tab-users">
  <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i> Quản lý danh sách tài khoản hệ thống (Dành cho Quản trị viên)</h6>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser">
      <i class="bi bi-person-plus-fill me-1"></i> Thêm tài khoản mới
    </button>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 50px;">ID</th>
            <th>Tài khoản</th>
            <th>Họ và tên</th>
            <th>Vai trò</th>
            <th>Ngày tạo</th>
            <th class="text-end">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($allUsers as $usr): ?>
            <tr>
              <td><?= $usr['id'] ?></td>
              <td><strong class="font-monospace text-primary"><?= e($usr['tai_khoan']) ?></strong></td>
              <td class="fw-semibold text-dark"><?= e($usr['ho_ten']) ?></td>
              <td>
                <span class="badge <?= $usr['role'] === 'admin' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                  <?= $usr['role'] === 'admin' ? 'Quản trị viên' : 'Nhân viên' ?>
                </span>
              </td>
              <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($usr['created_at'])) ?></td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalResetPw<?= $usr['id'] ?>">
                  <i class="bi bi-key"></i> Đặt lại MK
                </button>

                <!-- Modal đặt lại mật khẩu cho user này -->
                <div class="modal fade" id="modalResetPw<?= $usr['id'] ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered text-start">
                    <div class="modal-content">
                      <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>">
                        <div class="modal-header">
                          <h6 class="modal-title fw-bold"><i class="bi bi-key text-primary me-2"></i> Đặt lại mật khẩu: <?= e($usr['tai_khoan']) ?></h6>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <p class="text-muted small">Đặt mật khẩu mới cho người dùng <strong><?= e($usr['ho_ten']) ?></strong> (<code><?= e($usr['tai_khoan']) ?></code>)</p>
                          <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="mat_khau_moi" class="form-control" placeholder="Tối thiểu 6 ký tự" required minlength="6">
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                          <button type="submit" class="btn btn-primary">Xác nhận đặt lại</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal thêm tài khoản mới -->
<div class="modal fade" id="modalAddUser" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i> Thêm tài khoản người dùng</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Tên đăng nhập</label>
            <input name="tai_khoan" class="form-control font-monospace" placeholder="VD: nv_thietbi01" required>
            <div class="form-text small text-muted">Viết liền không dấu</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Họ và tên</label>
            <input name="ho_ten" class="form-control" placeholder="VD: Nguyễn Văn B" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Mật khẩu khởi tạo</label>
            <input type="password" name="mat_khau" class="form-control" placeholder="Tối thiểu 6 ký tự" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label">Vai trò</label>
            <select name="role" class="form-select">
              <option value="staff">Nhân viên thiết bị (Staff)</option>
              <option value="admin">Quản trị viên (Admin)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Tạo tài khoản</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
