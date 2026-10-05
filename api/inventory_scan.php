<?php
require __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

function tra($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if (empty($_SESSION['user_id'])) tra(['ok' => false, 'msg' => 'Chưa đăng nhập'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tra(['ok' => false, 'msg' => 'Sai phương thức'], 405);

// fetch gửi JSON nên đọc từ php://input
$in  = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals($_SESSION['csrf'] ?? '', $in['csrf'] ?? '')) tra(['ok' => false, 'msg' => 'CSRF không hợp lệ'], 419);

$sid = (int)($in['session_id'] ?? 0);
$ma  = strtoupper(trim($in['ma'] ?? ''));

$s = fetch_one(q($conn, "SELECT * FROM inventory_sessions WHERE id = ? AND trang_thai = 'dang_kiem'", 'i', $sid));
if (!$s) tra(['ok' => false, 'msg' => 'Phiên kiểm kê không tồn tại hoặc đã kết thúc']);

$a = fetch_one(q($conn, "SELECT id, ten, location_id FROM assets WHERE ma_tai_san = ?", 's', $ma));
if (!$a) tra(['ok' => false, 'msg' => "Mã \"$ma\" không có trong hệ thống"]);

$kq = ((int)$a['location_id'] === (int)$s['location_id']) ? 'dung_vi_tri' : 'sai_vi_tri';

try {
    q($conn, "INSERT INTO inventory_items (session_id, asset_id, ket_qua, thoi_diem) VALUES (?,?,?,NOW())", 'iis', $sid, $a['id'], $kq);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() == 1062) tra(['ok' => false, 'msg' => "$ma đã được quét trong phiên này"]);
    throw $ex;
}
tra(['ok' => true, 'ten' => $a['ten'], 'ket_qua' => $kq, 'ma' => $ma]);
