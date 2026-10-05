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
if ($s['trang_thai'] === 'hoan_tat') redirect('/inventory/report.php?id=' . $sid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // nút "Kết thúc"
  csrf_check();
  q($conn, "UPDATE inventory_sessions SET trang_thai = 'hoan_tat', ket_thuc = NOW() WHERE id = ?", 'i', $sid);
  redirect('/inventory/report.php?id=' . $sid);
}

$title = 'Quét kiểm kê';
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . '/static/js/scanner.js?v=2"></script>
<script>
  const SID = ' . $sid . ', CSRF = ' . json_encode(csrf_token()) . ';
  async function ghi(ma) {
    const r = await fetch("' . BASE_URL . '/api/inventory_scan.php", {
      method: "POST", headers: {"Content-Type": "application/json"},
      body: JSON.stringify({session_id: SID, ma: ma, csrf: CSRF})
    });
    const d = await r.json();
    const li = document.createElement("li");
    li.className = "list-group-item d-flex justify-content-between align-items-center " + (d.ok ? (d.ket_qua === "dung_vi_tri" ? "list-group-item-success" : "list-group-item-warning") : "list-group-item-danger");
    li.innerHTML = d.ok 
      ? "<span><strong>" + d.ma + "</strong> - " + d.ten + "</span> <span class=\"badge " + (d.ket_qua === "sai_vi_tri" ? "bg-warning text-dark" : "bg-success") + "\">" + (d.ket_qua === "sai_vi_tri" ? "SAI VỊ TRÍ" : "ĐÚNG VỊ TRÍ") + "</span>"
      : "<span><i class=\"bi bi-x-circle me-1\"></i> " + d.msg + "</span>";
    document.getElementById("log").prepend(li);
  }
  startScanner("reader", ghi);
  document.getElementById("nhaptay").addEventListener("submit", e => {
    e.preventDefault(); const i = document.getElementById("manual"); if (i.value.trim()) { ghi(i.value.trim()); i.value = ""; }
  });
</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="mb-1"><i class="bi bi-qr-code-scan text-primary me-2"></i> Phiên kiểm kê: <?= e($s['khu_vuc']) ?></h3>
    <div class="text-muted small">Phiên #<?= $sid ?> · Bắt đầu lúc: <?= date('d/m/Y H:i', strtotime($s['bat_dau'])) ?></div>
  </div>
  <a href="start.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Danh sách phiên</a>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-body text-center mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-camera text-primary me-1"></i> Quét mã QR liên tục</h6>
      <div id="reader" style="max-width:380px; margin: 0 auto;"></div>
      
      <form id="nhaptay" class="input-group mt-3">
        <input id="manual" class="form-control" placeholder="Hoặc gõ mã tài sản...">
        <button class="btn btn-outline-primary"><i class="bi bi-send"></i> Ghi nhận</button>
      </form>
    </div>

    <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn kết thúc phiên kiểm kê này và xem báo cáo đối soát?')">
      <?= csrf_field() ?>
      <button class="btn btn-danger w-100 py-2 fw-bold">
        <i class="bi bi-check-all"></i> Hoàn tất &amp; Xem báo cáo
      </button>
    </form>
  </div>

  <div class="col-lg-7">
    <div class="card card-body h-100">
      <h6 class="fw-bold mb-3"><i class="bi bi-list-check text-primary me-1"></i> Nhật ký quét thực tế</h6>
      <ul id="log" class="list-group list-group-flush border rounded-3" style="max-height: 480px; overflow-y: auto;">
        <li class="list-group-item text-center text-muted py-4">Chưa có mã nào được quét trong phiên này</li>
      </ul>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>