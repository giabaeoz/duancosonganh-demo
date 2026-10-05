<?php
require __DIR__ . '/includes/init.php';
require_login();
$title = 'Quét QR thử';
$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . '/static/js/scanner.js?v=2"></script>
<script>
  async function traCuu(ma) {
    const el = document.getElementById("kq");
    el.innerHTML = `
      <div class="d-flex align-items-center justify-content-center p-3 text-muted">
        <div class="spinner-border spinner-border-sm text-primary me-2"></div> Đang tra cứu thông tin tài sản <strong>${ma}</strong>...
      </div>`;
    
    try {
      const r = await fetch("' . BASE_URL . '/api/asset_lookup.php?ma=" + encodeURIComponent(ma));
      const d = await r.json();
      if (d.ok) {
        const a = d.asset;
        const imgHtml = a.hinh_anh_url 
          ? `<div class="position-relative d-inline-block text-center">
               <a href="${a.hinh_anh_url}" target="_blank" class="d-inline-block text-decoration-none" title="Bấm để mở ảnh gốc">
                 <img src="${a.hinh_anh_url}" alt="${a.ten}" class="rounded border p-2 bg-white shadow-sm" style="width: 220px; height: 220px; min-width: 220px; object-fit: contain;">
                 <div class="small text-muted mt-1" style="font-size: 0.75rem;"><i class="bi bi-zoom-in me-1"></i>Bấm ảnh để phóng to</div>
               </a>
             </div>`
          : `<div class="rounded border bg-light d-flex flex-column align-items-center justify-content-center text-muted" style="width: 220px; height: 220px; min-width: 220px; flex-shrink: 0;"><i class="bi bi-image text-secondary" style="font-size: 3rem;"></i><span class="small mt-2 text-muted fw-semibold">Không có ảnh chụp</span></div>`;
        
        const badgeClass = a.trang_thai === "san_sang" ? "text-bg-success" : (a.trang_thai === "dang_muon" ? "text-bg-warning" : "text-bg-danger");

        el.className = "card card-body mt-3 p-3 p-md-4 text-start border-success border-2 shadow-sm bg-white";
        el.innerHTML = `
          <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
            <div class="flex-shrink-0 text-center">
              ${imgHtml}
            </div>
            <div class="flex-grow-1 min-w-0 w-100">
              <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge bg-light text-dark border font-monospace fs-6 px-2 py-1">${a.ma_tai_san}</span>
                <span class="badge ${badgeClass} fs-6 px-2 py-1">${a.ten_trang_thai}</span>
              </div>
              <h4 class="fw-bold text-dark mb-2">${a.ten}</h4>
              <div class="text-muted mb-3 fs-6">
                <div class="mb-1"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Vị trí: <strong class="text-dark">${a.vitri || "Chưa gán"}</strong></div>
                ${a.loai ? `<div class="mb-1"><i class="bi bi-tag-fill text-primary me-2"></i>Phân loại: <span class="text-dark">${a.loai}</span></div>` : ""}
              </div>
              <div class="d-flex gap-2 flex-wrap pt-1">
                <a href="' . BASE_URL . '/assets/view.php?id=${a.id}" class="btn btn-primary px-3">
                  <i class="bi bi-eye me-1"></i> Xem hồ sơ chi tiết
                </a>
                <a href="' . BASE_URL . '/loans/borrow.php?ma=${encodeURIComponent(a.ma_tai_san)}" class="btn btn-outline-success">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Mượn tài sản
                </a>
              </div>
            </div>
          </div>`;
        el.scrollIntoView({ behavior: "smooth", block: "nearest" });
      } else {
        el.className = "alert alert-danger mt-3 mb-0 text-start";
        el.innerHTML = `<i class="bi bi-x-circle-fill me-2 fs-5"></i> ${d.msg}`;
      }
    } catch (e) {
      el.className = "alert alert-danger mt-3 mb-0 text-start";
      el.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> Lỗi kết nối máy chủ`;
    }
  }

  startScanner("reader", traCuu);

  document.getElementById("formManual").addEventListener("submit", (e) => {
    e.preventDefault();
    const val = document.getElementById("manualCode").value.trim();
    if (val) traCuu(val);
  });
</script>';
include __DIR__ . '/includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h3><i class="bi bi-qr-code-scan text-primary me-2"></i> Thử nghiệm quét mã QR</h3>
</div>

<div class="row justify-content-center">
  <div class="col-md-9 col-lg-8">
    <div class="card card-body text-center mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-camera text-primary me-1"></i> Khung quét Camera</h6>
      <div id="reader"></div>

      <form id="formManual" class="input-group mt-3">
        <input id="manualCode" class="form-control font-monospace" placeholder="Hoặc gõ mã tài sản (VD: TS-000001)...">
        <button class="btn btn-outline-primary"><i class="bi bi-search"></i> Tra cứu</button>
      </form>

      <div id="kq" class="alert alert-info mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i> Hướng camera vào tem mã QR tài sản hoặc gõ mã để tra cứu hình ảnh &amp; thông tin...
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>