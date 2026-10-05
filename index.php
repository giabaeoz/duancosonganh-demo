<?php
require __DIR__ . '/includes/init.php';
require_login();

// ---- Số liệu thống kê ----
$dem = fn($sql) => (int)fetch_one(q($conn, $sql))['n'];
$tong     = $dem("SELECT COUNT(*) n FROM assets");
$sanSang  = $dem("SELECT COUNT(*) n FROM assets WHERE trang_thai = 'san_sang'");
$dangMuon = $dem("SELECT COUNT(*) n FROM loans WHERE trang_thai = 'dang_muon'");
$quaHan   = $dem("SELECT COUNT(*) n FROM loans WHERE trang_thai = 'dang_muon' AND han_tra < NOW()");

// ---- Biểu đồ tròn: tài sản theo trạng thái ----
$mau = ['san_sang' => '#10b981', 'dang_muon' => '#f59e0b', 'bao_tri' => '#ef4444', 'ngung_su_dung' => '#94a3af'];
$nhan = [];
$giaTri = [];
$mauDs = [];
foreach (fetch_all(q($conn, "SELECT trang_thai, COUNT(*) sl FROM assets GROUP BY trang_thai")) as $r) {
  $nhan[]   = TRANG_THAI_TS[$r['trang_thai']][0];
  $giaTri[] = (int)$r['sl'];
  $mauDs[]  = $mau[$r['trang_thai']];
}

// ---- Biểu đồ cột: lượt mượn 6 tháng gần nhất ----
$theoThang = fetch_all(q(
  $conn,
  "SELECT DATE_FORMAT(ngay_muon, '%m/%Y') AS thang, COUNT(*) AS sl FROM loans
   WHERE ngay_muon >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
   GROUP BY DATE_FORMAT(ngay_muon, '%Y-%m'), thang ORDER BY DATE_FORMAT(ngay_muon, '%Y-%m')"
));

// ---- Bảng mượn gần đây ----
$gan = fetch_all(q(
  $conn,
  "SELECT lo.*, a.ma_tai_san, a.ten AS ten_ts, b.ho_ten AS nguoi_muon
   FROM loans lo JOIN assets a ON a.id = lo.asset_id JOIN borrowers b ON b.id = lo.borrower_id
   ORDER BY lo.id DESC LIMIT 6"
));

$title = 'Tổng quan';
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  Chart.defaults.font.family = "\'Be Vietnam Pro\', sans-serif";
  Chart.defaults.color = "#64748b";
  new Chart(document.getElementById("c1"), {
    type: "doughnut",
    data: { labels: ' . json_encode($nhan, JSON_UNESCAPED_UNICODE) . ',
            datasets: [{ data: ' . json_encode($giaTri) . ', backgroundColor: ' . json_encode($mauDs) . ', borderWidth: 2, borderColor: "#ffffff" }] },
    options: { cutout: "70%", plugins: { legend: { position: "bottom", labels: { boxWidth: 12, padding: 12 } } } }
  });
  new Chart(document.getElementById("c2"), {
    type: "bar",
    data: { labels: ' . json_encode(array_column($theoThang, 'thang')) . ',
            datasets: [{ label: "Lượt mượn", data: ' . json_encode(array_map('intval', array_column($theoThang, 'sl'))) . ',
                         backgroundColor: "#2563eb", borderRadius: 6 }] },
    options: { 
      plugins: { legend: { display: false } }, 
      scales: { 
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: "#f1f5f9" } } 
      } 
    }
  });
</script>';
include __DIR__ . '/includes/header.php';

$the = [
  ['Tổng tài sản', $tong,     'bi-box-seam',            'indigo'],
  ['Sẵn sàng',     $sanSang,  'bi-check-circle',        'green'],
  ['Đang mượn',    $dangMuon, 'bi-hourglass-split',     'amber'],
  ['Quá hạn',      $quaHan,   'bi-exclamation-triangle', 'red'],
];
?>
<div class="hero d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div>
    <h3 class="hero-title">Xin chào, <?= e($_SESSION['ho_ten']) ?> 👋</h3>
    <div class="hero-desc">Hôm nay là <?= date('d/m/Y') ?> · Quản lý mượn, trả và kiểm kê tài sản bằng mã QR</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="loans/borrow.php"><i class="bi bi-box-arrow-up-right"></i> Mượn tài sản</a>
    <a class="btn btn-light" href="loans/return.php"><i class="bi bi-box-arrow-in-down-left"></i> Trả tài sản</a>
    <a class="btn btn-warning" href="scan.php"><i class="bi bi-qr-code-scan"></i> Quét QR</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($the as [$lbl, $num, $ico, $mauIco]): ?>
    <div class="col-6 col-lg-3">
      <div class="card stat">
        <div class="ico <?= $mauIco ?>"><i class="bi <?= $ico ?>"></i></div>
        <div>
          <div class="num"><?= $num ?></div>
          <div class="lbl"><?= $lbl ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card card-body h-100">
      <h5 class="mb-3 fs-6 fw-bold">Tài sản theo trạng thái</h5>
      <canvas id="c1" height="230"></canvas>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card card-body h-100">
      <h5 class="mb-3 fs-6 fw-bold">Lượt mượn theo tháng (6 tháng gần nhất)</h5>
      <canvas id="c2" height="150"></canvas>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0 fs-6 fw-bold"><i class="bi bi-clock-history text-primary me-1"></i> Mượn – trả gần đây</h5>
  <a href="loans/list.php?loc=tat_ca" class="btn btn-sm btn-outline-primary">Xem tất cả →</a>
</div>
<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Mã &amp; Tên tài sản</th>
        <th>Người mượn</th>
        <th>Ngày mượn</th>
        <th>Hạn trả</th>
        <th>Trạng thái</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gan as $l):
        $qua = $l['trang_thai'] === 'dang_muon' && strtotime($l['han_tra']) < time();
        [$nhanTT, $mauPill] = $l['trang_thai'] === 'da_tra' ? ['Đã trả', 'green'] : ($qua ? ['Quá hạn', 'red'] : ['Đang mượn', 'amber']); ?>
        <tr>
          <td>
            <strong class="text-dark"><?= e($l['ma_tai_san']) ?></strong>
            <div class="text-muted small"><?= e($l['ten_ts']) ?></div>
          </td>
          <td class="fw-semibold text-dark"><?= e($l['nguoi_muon']) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($l['ngay_muon'])) ?></td>
          <td><?= date('d/m/Y H:i', strtotime($l['han_tra'])) ?></td>
          <td><span class="pill <?= $mauPill ?>"><?= $nhanTT ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$gan): ?><tr>
          <td colspan="5" class="text-center text-muted py-4">Chưa có giao dịch gần đây</td>
        </tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>