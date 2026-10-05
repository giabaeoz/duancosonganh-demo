<?php
require __DIR__ . '/../includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $ma   = strtoupper(trim($_POST['ma_tai_san'] ?? ''));
  $tt   = $_POST['tinh_trang'] ?? 'tot';
  $note = trim($_POST['ghi_chu'] ?? '');

  $conn->begin_transaction();
  try {
    if (!in_array($tt, ['tot', 'hu_hong'], true)) throw new Exception('Tình trạng không hợp lệ');

    $lo = fetch_one(q(
      $conn,
      "SELECT lo.id, lo.asset_id FROM loans lo
       JOIN assets a ON a.id = lo.asset_id
       WHERE a.ma_tai_san = ? AND lo.trang_thai = 'dang_muon' FOR UPDATE",
      's',
      $ma
    ));
    if (!$lo) throw new Exception('Tài sản này hiện không có giao dịch mượn nào đang hiệu lực');

    q(
      $conn,
      "UPDATE loans SET ngay_tra = NOW(), tinh_trang_khi_tra = ?, ghi_chu = ?,
                  staff_nhan_id = ?, trang_thai = 'da_tra' WHERE id = ?",
      'ssii',
      $tt,
      $note,
      $_SESSION['user_id'],
      $lo['id']
    );

    $trangThaiMoi = ($tt === 'hu_hong') ? 'bao_tri' : 'san_sang';
    q($conn, "UPDATE assets SET trang_thai = ? WHERE id = ?", 'si', $trangThaiMoi, $lo['asset_id']);

    $conn->commit();
    flash($tt === 'hu_hong' ? "Đã nhận trả $ma — chuyển sang Cần bảo trì" : "Đã nhận trả $ma thành công", $tt === 'hu_hong' ? 'warning' : 'success');
  } catch (Exception $ex) {
    $conn->rollback();
    flash($ex->getMessage(), 'danger');
  }
  redirect('/loans/return.php');
}

$title = 'Trả tài sản';
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . '/static/js/scanner.js?v=2"></script>
<script>
  async function traCuuTra(ma) {
    document.getElementById("ma").value = ma;
    const el = document.getElementById("thongtin");
    el.innerHTML = `<div class="p-2 text-muted small"><div class="spinner-border spinner-border-sm text-primary me-1"></div> Đang kiểm tra...</div>`;
    
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
        
        const loanHtml = a.loan_info 
          ? `<div class="mt-2 pt-2 border-top small">
               <div class="mb-1"><i class="bi bi-person-fill text-primary me-1"></i>Người mượn: <strong>${a.loan_info.nguoi_muon}</strong> <span class="text-muted font-monospace">(${a.loan_info.ma_nguoi_muon})</span></div>
               ${a.loan_info.phong_muon ? `<div class="mb-1"><i class="bi bi-door-open text-danger me-1"></i>Phòng mượn: <span class="badge bg-light text-dark border font-monospace fs-7">${a.loan_info.phong_muon}</span></div>` : ""}
               <div class="text-muted"><i class="bi bi-clock me-1"></i>Thời gian: ${a.loan_info.ngay_muon} → Hạn: ${a.loan_info.han_tra}</div>
             </div>`
          : "";

        el.innerHTML = `
          <div class="card card-body p-3 mt-2 border border-primary bg-light shadow-sm">
            <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3">
              ${imgHtml}
              <div class="flex-grow-1 min-w-0 w-100">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                  <span class="badge bg-light text-dark border font-monospace fs-6">${a.ma_tai_san}</span>
                  <strong class="text-dark fs-5">${a.ten}</strong>
                  <span class="badge ${a.trang_thai === "dang_muon" ? "text-bg-warning" : "text-bg-secondary"} fs-6">${a.ten_trang_thai}</span>
                </div>
                <div class="small text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Vị trí gốc: <strong>${a.vitri || "Chưa gán"}</strong> ${a.loai ? ` · ${a.loai}` : ""}</div>
                ${loanHtml}
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

  startScanner("reader", traCuuTra);

  const inputMa = document.getElementById("ma");
  inputMa.addEventListener("change", () => {
    if (inputMa.value.trim()) traCuuTra(inputMa.value.trim());
  });
  inputMa.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      if (inputMa.value.trim()) traCuuTra(inputMa.value.trim());
    }
  });
</script>';
include __DIR__ . '/../includes/header.php';
?>
<h3><i class="bi bi-box-arrow-in-down-left text-success me-2"></i> Trả tài sản</h3>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card card-body text-center">
      <h6 class="fw-bold mb-3"><i class="bi bi-qr-code-scan text-primary me-1"></i> Quét mã QR tiếp nhận</h6>
      <div id="reader" style="max-width:380px; margin: 0 auto;"></div>
    </div>
  </div>
  <div class="col-lg-7">
    <form method="POST" class="card card-body">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Mã tài sản</label>
        <input id="ma" name="ma_tai_san" class="form-control font-monospace" placeholder="VD: TS-000001 (nhập xong nhấn Enter)" required>
        <div id="thongtin"></div>
      </div>
      <div class="mb-3">
        <label class="form-label">Tình trạng kiểm tra khi trả</label>
        <select name="tinh_trang" class="form-select">
          <option value="tot">Hoạt động tốt / Bình thường</option>
          <option value="hu_hong">Hư hỏng / Cần bảo trì</option>
        </select>
      </div>
      <div class="mb-4">
        <label class="form-label">Ghi chú kiểm tra</label>
        <textarea name="ghi_chu" class="form-control" rows="3" placeholder="Ghi chú thêm về phụ kiện, vỏ ngoài..."></textarea>
      </div>
      <button class="btn btn-success w-100 py-2">
        <i class="bi bi-check2-circle"></i> Xác nhận hoàn tất trả tài sản
      </button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>