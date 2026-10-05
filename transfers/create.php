<?php
require __DIR__ . '/../includes/init.php';
require_login();

$viTriDs = fetch_all(q($conn, "SELECT * FROM locations ORDER BY ten"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ma     = strtoupper(trim($_POST['ma_tai_san'] ?? ''));
  $toLoc  = (int)($_POST['to_location_id'] ?? 0);
  $giao   = trim($_POST['nguoi_giao'] ?? '');
  $nhan   = trim($_POST['nguoi_nhan'] ?? '');
  $note   = trim($_POST['ghi_chu'] ?? '');

  $conn->begin_transaction();
  try {
    $a = fetch_one(q($conn, "SELECT id, trang_thai, location_id FROM assets WHERE ma_tai_san = ? FOR UPDATE", 's', $ma));
    if (!$a) throw new Exception('Tài sản không tồn tại');
    if ($a['trang_thai'] === 'dang_muon') throw new Exception('Tài sản đang được mượn, không thể điều chuyển');
    if ($giao === '' || $nhan === '') throw new Exception('Phải nhập người giao và người nhận');

    $dich = fetch_one(q($conn, "SELECT id FROM locations WHERE id = ?", 'i', $toLoc));
    if (!$dich) throw new Exception('Vị trí đến không hợp lệ');
    if ((int)$a['location_id'] === $toLoc) throw new Exception('Vị trí mới trùng vị trí hiện tại');

    q(
      $conn,
      "INSERT INTO transfers (asset_id, nguoi_giao, nguoi_nhan, from_location_id, to_location_id, staff_id, thoi_diem, ghi_chu)
       VALUES (?,?,?,?,?,?,NOW(),?)",
      'issiiis',
      $a['id'],
      $giao,
      $nhan,
      $a['location_id'],
      $toLoc,
      $_SESSION['user_id'],
      $note
    );
    q($conn, "UPDATE assets SET location_id = ? WHERE id = ?", 'ii', $toLoc, $a['id']);

    $conn->commit();
    flash("Đã điều chuyển $ma thành công", 'success');
  } catch (Exception $ex) {
    $conn->rollback();
    flash($ex->getMessage(), 'danger');
  }
  redirect('/transfers/create.php');
}

$title = 'Điều chuyển tài sản';
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . '/static/js/scanner.js?v=2"></script>
<script>
  async function traCuuChuyen(ma) {
    document.getElementById("ma").value = ma;
    const el = document.getElementById("thongtin");
    el.innerHTML = `<div class="p-2 text-muted small"><div class="spinner-border spinner-border-sm text-primary me-1"></div> Đang kiểm tra thông tin tài sản...</div>`;
    
    try {
      const r = await fetch("' . BASE_URL . '/api/asset_lookup.php?ma=" + encodeURIComponent(ma));
      const d = await r.json();
      if (d.ok) {
        const a = d.asset;
        const imgHtml = a.hinh_anh_url 
          ? `<div class="text-center flex-shrink-0">
               <a href="${a.hinh_anh_url}" target="_blank" class="d-block text-decoration-none" title="Bấm để mở ảnh gốc">
                 <img src="${a.hinh_anh_url}" alt="${a.ten}" class="rounded border p-1 bg-white shadow-sm" style="width: 140px; height: 140px; min-width: 140px; object-fit: contain;">
                 <div class="text-muted small mt-1" style="font-size: 0.72rem;"><i class="bi bi-zoom-in"></i> Phóng to</div>
               </a>
             </div>`
          : `<div class="rounded border bg-light d-flex flex-column align-items-center justify-content-center text-muted" style="width: 140px; height: 140px; min-width: 140px; flex-shrink: 0;"><i class="bi bi-image text-secondary" style="font-size: 2.5rem;"></i><span class="text-muted mt-1" style="font-size: 0.72rem;">Không có ảnh</span></div>`;
        
        el.innerHTML = `
          <div class="card card-body p-3 mt-2 border border-info bg-light shadow-sm">
            <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
              ${imgHtml}
              <div class="flex-grow-1 min-w-0 w-100">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                  <span class="badge bg-light text-dark border font-monospace fs-6">${a.ma_tai_san}</span>
                  <strong class="text-dark fs-5">${a.ten}</strong>
                  <span class="badge text-bg-info fs-6">${a.ten_trang_thai}</span>
                </div>
                <div class="small text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Vị trí hiện tại: <strong class="text-danger">${a.vitri || "Chưa gán"}</strong></div>
              </div>
            </div>
          </div>
        `;
      } else {
        el.innerHTML = `<div class="text-danger small mt-1"><i class="bi bi-x-circle-fill me-1"></i> ${d.msg}</div>`;
      }
    } catch(err) {
      el.innerHTML = `<div class="text-danger small mt-1">Lỗi kết nối tra cứu tài sản</div>`;
    }
  }

  startScanner("reader", traCuuChuyen);

  const inputMa = document.getElementById("ma");
  inputMa.addEventListener("change", () => {
    if (inputMa.value.trim()) traCuuChuyen(inputMa.value.trim());
  });
  inputMa.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      if (inputMa.value.trim()) traCuuChuyen(inputMa.value.trim());
    }
  });
</script>';
include __DIR__ . '/../includes/header.php';
?>
<h3><i class="bi bi-arrow-left-right text-primary me-2"></i> Bàn giao / Điều chuyển</h3>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-body text-center">
      <h6 class="fw-bold mb-3"><i class="bi bi-qr-code-scan text-primary me-1"></i> Quét mã QR</h6>
      <div id="reader" style="max-width:380px; margin: 0 auto;"></div>
      <p class="text-muted small mt-3 mb-0">Quét mã QR trên tài sản để tự động hiển thị ảnh &amp; vị trí hiện tại</p>
    </div>
  </div>
  <div class="col-lg-7">
    <form method="POST" class="card card-body">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Mã tài sản *</label>
        <input id="ma" name="ma_tai_san" class="form-control font-monospace" placeholder="VD: TS-000001 (nhập xong nhấn Enter)" required>
        <div id="thongtin"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Vị trí mới *</label>
        <select name="to_location_id" class="form-select" required>
          <option value="">-- Chọn vị trí mới chuyển đến --</option>
          <?php foreach ($viTriDs as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['ten']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Người giao *</label>
          <input name="nguoi_giao" class="form-control" placeholder="Họ tên người giao" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Người nhận *</label>
          <input name="nguoi_nhan" class="form-control" placeholder="Họ tên người nhận" required>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label">Ghi chú</label>
        <textarea name="ghi_chu" class="form-control" rows="2" placeholder="Ghi chú bàn giao..."></textarea>
      </div>
      <button class="btn btn-primary w-100 py-2">
        <i class="bi bi-check2-circle"></i> Xác nhận bàn giao &amp; đổi vị trí
      </button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>