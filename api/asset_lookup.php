<?php
require __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'msg' => 'Chưa đăng nhập']);
  exit;
}

$ma = strtoupper(trim($_GET['ma'] ?? ''));
$a = fetch_one(q(
  $conn,
  "SELECT a.id, a.ma_tai_san, a.ten, a.trang_thai, a.hinh_anh, l.ten AS vitri, c.ten AS loai
   FROM assets a 
   LEFT JOIN locations l ON l.id = a.location_id
   LEFT JOIN categories c ON c.id = a.category_id
   WHERE a.ma_tai_san = ?",
  's',
  $ma
));

if (!$a) {
  echo json_encode(['ok' => false, 'msg' => "Không tìm thấy tài sản \"$ma\""], JSON_UNESCAPED_UNICODE);
  exit;
}

// Kiểm tra và trả về đường dẫn ảnh đầy đủ
if (!empty($a['hinh_anh']) && file_exists(__DIR__ . '/../uploads/assets/images/' . $a['hinh_anh'])) {
  $a['hinh_anh_url'] = BASE_URL . '/uploads/assets/images/' . $a['hinh_anh'];
} else {
  $a['hinh_anh_url'] = null;
}

$nhanTrangThai = TRANG_THAI_TS[$a['trang_thai']][0] ?? $a['trang_thai'];
$a['ten_trang_thai'] = $nhanTrangThai;

if ($a['trang_thai'] === 'dang_muon') {
  $loan = fetch_one(q($conn, "SELECT lo.*, b.ho_ten AS nguoi_muon, b.ma_so AS ma_nguoi_muon FROM loans lo JOIN borrowers b ON b.id = lo.borrower_id WHERE lo.asset_id = ? AND lo.trang_thai = 'dang_muon' ORDER BY lo.id DESC LIMIT 1", 'i', $a['id']));
  if ($loan) {
    $a['loan_info'] = [
      'nguoi_muon' => $loan['nguoi_muon'],
      'ma_nguoi_muon' => $loan['ma_nguoi_muon'],
      'phong_muon' => $loan['phong_muon'],
      'ngay_muon' => date('d/m/Y H:i', strtotime($loan['ngay_muon'])),
      'han_tra' => date('d/m/Y H:i', strtotime($loan['han_tra'])),
    ];
  }
}

echo json_encode(['ok' => true, 'asset' => $a], JSON_UNESCAPED_UNICODE);
