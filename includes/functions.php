<?php
// Chống XSS: LUÔN bọc dữ liệu in ra HTML bằng e()
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($path) { header('Location: ' . BASE_URL . $path); exit; }

// Thông báo 1 lần (hiện sau khi chuyển trang)
function flash($msg, $type = 'success') { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }

// ---------- Truy vấn gọn ----------
function q($conn, $sql, $types = '', ...$params) {
    $st = $conn->prepare($sql);
    if ($types !== '') { $st->bind_param($types, ...$params); }
    $st->execute();
    return $st;
}
function fetch_all($st) { return $st->get_result()->fetch_all(MYSQLI_ASSOC); }
function fetch_one($st) { return $st->get_result()->fetch_assoc(); }

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        die('Phiên làm việc không hợp lệ, vui lòng tải lại trang.');
    }
}

// ---------- Phân quyền ----------
function require_login() {
    if (empty($_SESSION['user_id'])) { redirect('/login.php'); }
}
function require_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Bạn không có quyền truy cập chức năng này.');
    }
}

// ---------- Trạng thái ----------
const TRANG_THAI_TS = [
    'san_sang'      => ['Sẵn sàng', 'success'],
    'dang_muon'     => ['Đang mượn', 'warning'],
    'bao_tri'       => ['Cần bảo trì', 'danger'],
    'ngung_su_dung' => ['Ngừng sử dụng', 'secondary'],
];
function badge_ts($tt) {
    [$ten, $mau] = TRANG_THAI_TS[$tt] ?? [$tt, 'light'];
    return '<span class="badge text-bg-' . $mau . '">' . e($ten) . '</span>';
}
