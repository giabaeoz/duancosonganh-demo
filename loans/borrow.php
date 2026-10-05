<?php
require __DIR__ . '/../includes/init.php';
require_login();

$nguoiMuon = fetch_all(q($conn, "SELECT id, ma_so, ho_ten, lien_he FROM borrowers ORDER BY id DESC"));

$phongCoSan = [
  'A42.101', 'A42.102', 'A42.201', 'A42.205', 'A42.301', 'A42.307',
  'B11.101', 'B11.106', 'B11.201', 'B11.205', 'B11.301', 'B11.302',
  'P.Lab 01', 'P.Lab 02', 'Hội trường A', 'Hội trường B',
  'Phòng thực hành 1', 'Phòng thực hành 2', 'Kho thiết bị', 'Văn phòng Khoa'
];
$dbPhong = fetch_all(q($conn, "SELECT DISTINCT phong_muon FROM loans WHERE phong_muon IS NOT NULL AND phong_muon != ''"));
foreach ($dbPhong as $dp) {
  if (!in_array($dp['phong_muon'], $phongCoSan, true)) {
    $phongCoSan[] = $dp['phong_muon'];
  }
}
sort($phongCoSan);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $maNm = strtoupper(trim($_POST['ma_so_nguoi_muon'] ?? ''));
  $tenNm = trim($_POST['ho_ten_nguoi_muon'] ?? '');
  $lhNm = trim($_POST['lien_he_nguoi_muon'] ?? '');
  $phongMuon = strtoupper(trim($_POST['phong_muon'] ?? ''));
  $han = str_replace('T', ' ', $_POST['han_tra'] ?? '') . ':00';
  $assetIds = $_POST['asset_ids'] ?? [];

  $conn->begin_transaction();
  try {
    if (!is_array($assetIds) || empty($assetIds)) {
      throw new Exception('Vui lòng quét hoặc thêm ít nhất một thiết bị vào danh sách mượn');
    }
    if ($maNm === '' || $tenNm === '') {
      throw new Exception('Vui lòng nhập mã số và họ tên người mượn');
    }
    if ($phongMuon === '') {
      throw new Exception('Vui lòng chọn hoặc nhập vị trí phòng mượn');
    }
    if (strtotime($han) === false || strtotime($han) <= time()) {
      throw new Exception('Hạn trả phải ở thời điểm tương lai');
    }

    $b = fetch_one(q($conn, "SELECT id, ho_ten, lien_he FROM borrowers WHERE ma_so = ?", 's', $maNm));
    if ($b) {
      $bid = (int)$b['id'];
      if ($tenNm !== $b['ho_ten'] || ($lhNm !== '' && $lhNm !== $b['lien_he'])) {
        q($conn, "UPDATE borrowers SET ho_ten = ?, lien_he = ? WHERE id = ?", 'ssi', $tenNm, $lhNm ?: $b['lien_he'], $bid);
      }
    } else {
      q($conn, "INSERT INTO borrowers (ma_so, ho_ten, lien_he) VALUES (?, ?, ?)", 'sss', $maNm, $tenNm, $lhNm);
      $bid = $conn->insert_id;
    }

    $assetIds = array_unique(array_map('intval', $assetIds));
    $inClause = implode(',', array_fill(0, count($assetIds), '?'));
    $types = str_repeat('i', count($assetIds));
    $assets = fetch_all(q($conn, "SELECT id, ma_tai_san, ten, trang_thai FROM assets WHERE id IN ($inClause) FOR UPDATE", $types, ...$assetIds));

    if (count($assets) !== count($assetIds)) {
      throw new Exception('Một số thiết bị không tồn tại trong hệ thống');
    }

    foreach ($assets as $a) {
      if ($a['trang_thai'] === 'dang_muon') {
        throw new Exception("Thiết bị {$a['ma_tai_san']} ({$a['ten']}) hiện đang được người khác mượn");
      }
      if ($a['trang_thai'] !== 'san_sang') {
        throw new Exception("Thiết bị {$a['ma_tai_san']} ({$a['ten']}) không ở trạng thái sẵn sàng");
      }
    }

    foreach ($assets as $a) {
      q(
        $conn,
        "INSERT INTO loans (asset_id, borrower_id, staff_giao_id, ngay_muon, han_tra, phong_muon, trang_thai)
         VALUES (?,?,?,NOW(),?,?,'dang_muon')",
        'iiiss',
        $a['id'],
        $bid,
        $_SESSION['user_id'],
        $han,
        $phongMuon
      );
      q($conn, "UPDATE assets SET trang_thai = 'dang_muon' WHERE id = ?", 'i', $a['id']);
    }

    $conn->commit();
    $soLuong = count($assets);
    flash("Đã cho $tenNm ($maNm) mượn thành công $soLuong thiết bị tại phòng $phongMuon", 'success');
    redirect('/loans/list.php');
  } catch (Exception $ex) {
    $conn->rollback();
    flash($ex->getMessage(), 'danger');
    redirect('/loans/borrow.php');
  }
}

$macDinh = date('Y-m-d\TH:i', strtotime('+7 days'));
$title = 'Mượn tài sản';
$dsBorrowersJson = json_encode($nguoiMuon, JSON_UNESCAPED_UNICODE);

$extra_js = '
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . '/static/js/scanner.js?v=2"></script>
<script>
  const borrowersList = ' . $dsBorrowersJson . ';
  const inputMaNm = document.getElementById("ma_so_nguoi_muon");
  const inputTenNm = document.getElementById("ho_ten_nguoi_muon");
  const inputLhNm = document.getElementById("lien_he_nguoi_muon");
  const borrowerStatus = document.getElementById("borrower_status_badge");

  function lookupBorrower(ma) {
    if (!ma) {
      borrowerStatus.innerHTML = "";
      return;
    }
    ma = ma.trim().toUpperCase();
    const found = borrowersList.find(b => b.ma_so.toUpperCase() === ma);
    if (found) {
      inputTenNm.value = found.ho_ten;
      if (found.lien_he) inputLhNm.value = found.lien_he;
      borrowerStatus.innerHTML = `<span class="badge text-bg-success"><i class="bi bi-person-check-fill me-1"></i>${found.ho_ten}</span>`;
    } else {
      borrowerStatus.innerHTML = `<span class="badge text-bg-primary"><i class="bi bi-person-plus-fill me-1"></i>Người mượn mới</span>`;
    }
  }

  inputMaNm.addEventListener("input", () => lookupBorrower(inputMaNm.value));
  inputMaNm.addEventListener("change", () => lookupBorrower(inputMaNm.value));

  const inputPhong = document.getElementById("phong_muon");
  const menuPhong = document.getElementById("menuPhongMuon");
  const btnTogglePhong = document.getElementById("btnTogglePhong");
  const roomOptions = document.querySelectorAll(".room-option");
  const emptyPhongMsg = document.getElementById("emptyPhongMsg");

  function showMenuPhong() {
    menuPhong.style.display = "block";
    filterPhong();
  }

  function filterPhong() {
    const val = inputPhong.value.trim().toUpperCase();
    let hasMatch = false;
    roomOptions.forEach(opt => {
      const text = opt.getAttribute("data-value").toUpperCase();
      if (!val || text.includes(val)) {
        opt.style.display = "block";
        hasMatch = true;
      } else {
        opt.style.display = "none";
      }
    });
    emptyPhongMsg.style.display = hasMatch ? "none" : "block";
  }

  inputPhong.addEventListener("focus", showMenuPhong);
  inputPhong.addEventListener("input", () => {
    menuPhong.style.display = "block";
    filterPhong();
  });

  btnTogglePhong.addEventListener("click", () => {
    if (menuPhong.style.display === "block") {
      menuPhong.style.display = "none";
    } else {
      inputPhong.focus();
      showMenuPhong();
    }
  });

  roomOptions.forEach(opt => {
    opt.addEventListener("click", () => {
      inputPhong.value = opt.getAttribute("data-value");
      menuPhong.style.display = "none";
    });
  });

  document.addEventListener("click", (e) => {
    const wrapper = document.getElementById("phongMuonDropdown");
    if (wrapper && !wrapper.contains(e.target)) {
      menuPhong.style.display = "none";
    }
  });

  // Danh sách các thiết bị được cộng dồn khi quét QR hoặc nhập mã
  let selectedAssets = [];

  function updateAssetsTable() {
    const tbody = document.getElementById("selectedAssetsBody");
    const emptyRow = document.getElementById("emptyAssetsRow");
    const countBadge = document.getElementById("selectedCountBadge");
    const countBtn = document.getElementById("btnCountNumber");
    const btnSubmit = document.getElementById("btnSubmitLoan");

    const n = selectedAssets.length;
    countBadge.innerText = n + " thiết bị";
    countBtn.innerText = "(" + n + " thiết bị)";
    btnSubmit.disabled = (n === 0);

    if (n === 0) {
      tbody.innerHTML = "";
      emptyRow.style.display = "";
      return;
    }

    emptyRow.style.display = "none";
    let html = "";
    selectedAssets.forEach((a, idx) => {
      const imgTag = a.hinh_anh_url 
        ? `<img src="${a.hinh_anh_url}" alt="${a.ten}" class="rounded border" style="width: 38px; height: 38px; object-fit: contain; background: #fff;">`
        : `<div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 38px; height: 38px;"><i class="bi bi-box-seam"></i></div>`;

      html += `
        <tr>
          <td class="text-muted small">${idx + 1}</td>
          <td>${imgTag}</td>
          <td><span class="badge bg-light text-dark border font-monospace">${a.ma_tai_san}</span></td>
          <td>
            <div class="fw-semibold text-dark">${a.ten}</div>
            <div class="text-muted small">${a.loai || ""}</div>
          </td>
          <td><i class="bi bi-geo-alt text-danger me-1"></i>${a.vitri || "Chưa gán"}</td>
          <td class="text-end">
            <input type="hidden" name="asset_ids[]" value="${a.id}">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAsset(${a.id})" title="Xóa thiết bị này">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `;
    });
    tbody.innerHTML = html;
  }

  function removeAsset(id) {
    selectedAssets = selectedAssets.filter(a => a.id !== id);
    updateAssetsTable();
  }

  function clearAllAssets() {
    if (selectedAssets.length === 0) return;
    selectedAssets = [];
    updateAssetsTable();
  }

  let isLookingUp = false;

  async function traCuuVaCongDon(ma) {
    if (!ma || isLookingUp) return;
    ma = ma.trim().toUpperCase();
    const msgEl = document.getElementById("assetScanNotice");
    
    // Kiểm tra xem thiết bị đã có trong danh sách chưa
    if (selectedAssets.some(a => a.ma_tai_san === ma)) {
      msgEl.innerHTML = `<div class="alert alert-warning py-2 px-3 small mb-0"><i class="bi bi-info-circle-fill me-1"></i> Thiết bị <strong>${ma}</strong> đã có trong danh sách mượn.</div>`;
      document.getElementById("inputMa").value = "";
      return;
    }

    isLookingUp = true;
    msgEl.innerHTML = `<div class="p-2 text-muted small"><div class="spinner-border spinner-border-sm text-primary me-1"></div> Đang kiểm tra thiết bị ${ma}...</div>`;

    try {
      const r = await fetch("' . BASE_URL . '/api/asset_lookup.php?ma=" + encodeURIComponent(ma));
      const d = await r.json();
      if (d.ok) {
        const a = d.asset;
        if (a.trang_thai !== "san_sang") {
          msgEl.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0"><i class="bi bi-exclamation-triangle-fill me-1"></i> Thiết bị <strong>${a.ma_tai_san}</strong> (${a.ten}) hiện đang <strong>${a.ten_trang_thai}</strong>, không thể mượn.</div>`;
        } else {
          selectedAssets.push(a);
          updateAssetsTable();
          msgEl.innerHTML = `<div class="alert alert-success py-2 px-3 small mb-0"><i class="bi bi-check-circle-fill me-1"></i> Đã thêm <strong>${a.ma_tai_san}</strong> - ${a.ten} vào danh sách. Tiếp tục quét thiết bị tiếp theo.</div>`;
          document.getElementById("inputMa").value = "";
          setTimeout(() => {
            msgEl.innerHTML = "";
          }, 3500);
        }
      } else {
        msgEl.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0"><i class="bi bi-x-circle-fill me-1"></i> ${d.msg}</div>`;
      }
    } catch(err) {
      msgEl.innerHTML = `<div class="alert alert-danger py-2 px-3 small mb-0">Lỗi kết nối tra cứu thiết bị</div>`;
    } finally {
      isLookingUp = false;
    }
  }

  startScanner("reader", traCuuVaCongDon);

  const inputMa = document.getElementById("inputMa");
  const btnThemMa = document.getElementById("btnThemMa");

  btnThemMa.addEventListener("click", () => {
    if (inputMa.value.trim()) traCuuVaCongDon(inputMa.value.trim());
  });

  inputMa.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      if (inputMa.value.trim()) traCuuVaCongDon(inputMa.value.trim());
    }
  });

  updateAssetsTable();
</script>';
include __DIR__ . '/../includes/header.php';
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <h3><i class="bi bi-box-arrow-up-right text-primary me-2"></i> Mượn tài sản</h3>
  <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-check"></i> Danh sách mượn</a>
</div>

<div class="row g-4">
  <!-- Cột bên trái: Quét QR & Nhập mã -->
  <div class="col-lg-5">
    <div class="card card-body text-center mb-3">
      <h6 class="fw-bold mb-3"><i class="bi bi-qr-code-scan text-primary me-1"></i> Quét mã QR thiết bị</h6>
      <div id="reader" style="max-width:380px; margin: 0 auto;"></div>
    </div>

    <div class="card card-body">
      <h6 class="fw-bold mb-2"><i class="bi bi-keyboard text-primary me-1"></i> Nhập mã thiết bị thủ công</h6>
      <div class="input-group">
        <input id="inputMa" class="form-control font-monospace" placeholder="VD: TS-000001 (gõ rồi nhấn Enter)" autocomplete="off">
        <button class="btn btn-primary" type="button" id="btnThemMa">
          <i class="bi bi-plus-lg"></i> Thêm
        </button>
      </div>
      <div id="assetScanNotice" class="mt-2"></div>
    </div>
  </div>

  <!-- Cột bên phải: Form thông tin & Danh sách thiết bị cộng dồn -->
  <div class="col-lg-7">
    <form method="POST" class="card card-body" id="borrowForm">
      <?= csrf_field() ?>

      <!-- 1. Danh sách thiết bị đã quét / cộng dồn -->
      <div class="mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <label class="form-label fw-bold text-dark mb-0">
            <i class="bi bi-boxes text-primary me-1"></i> Danh sách thiết bị mượn
          </label>
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-primary fs-7" id="selectedCountBadge">0 thiết bị</span>
            <button type="button" class="btn btn-link text-danger p-0 text-decoration-none small" onclick="clearAllAssets()" title="Xóa tất cả">
              Xóa tất cả
            </button>
          </div>
        </div>

        <div class="table-responsive rounded border bg-white">
          <table class="table align-middle mb-0">
            <thead class="table-light small">
              <tr>
                <th style="width: 35px;">STT</th>
                <th style="width: 50px;">Ảnh</th>
                <th>Mã</th>
                <th>Tên thiết bị</th>
                <th>Vị trí gốc</th>
                <th class="text-end" style="width: 45px;"></th>
              </tr>
            </thead>
            <tbody id="selectedAssetsBody"></tbody>
            <tr id="emptyAssetsRow">
              <td colspan="6" class="text-center text-muted py-4 small">
                <i class="bi bi-qr-code-scan fs-4 text-secondary d-block mb-1"></i>
                Chưa có thiết bị nào được chọn. Quét QR hoặc gõ mã bên trái để cộng dồn vào danh sách.
              </td>
            </tr>
          </table>
        </div>
      </div>

      <!-- 2. Thông tin người mượn -->
      <div class="mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <label class="form-label fw-bold text-dark mb-0"><i class="bi bi-person-fill text-primary me-1"></i> Người mượn</label>
          <div id="borrower_status_badge"></div>
        </div>

        <div class="row g-2">
          <div class="col-md-5">
            <label class="form-label small text-muted">Mã số SV / NV</label>
            <input list="listBorrowers" name="ma_so_nguoi_muon" id="ma_so_nguoi_muon" class="form-control font-monospace" placeholder="VD: SV202601" required autocomplete="off">
            <datalist id="listBorrowers">
              <?php foreach ($nguoiMuon as $b): ?>
                <option value="<?= e($b['ma_so']) ?>"><?= e($b['ho_ten'] . ($b['lien_he'] ? ' (' . $b['lien_he'] . ')' : '')) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-7">
            <label class="form-label small text-muted">Họ và tên người mượn</label>
            <input name="ho_ten_nguoi_muon" id="ho_ten_nguoi_muon" class="form-control" placeholder="Họ và tên đầy đủ" required>
          </div>
          <div class="col-12 mt-2">
            <label class="form-label small text-muted">Số điện thoại / Email</label>
            <input name="lien_he_nguoi_muon" id="lien_he_nguoi_muon" class="form-control" placeholder="Số điện thoại hoặc email">
          </div>
        </div>
      </div>

      <!-- 3. Vị trí phòng mượn & Hạn trả -->
      <div class="mb-4">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Vị trí phòng mượn</label>
            
            <div class="position-relative" id="phongMuonDropdown">
              <div class="input-group">
                <input type="text" name="phong_muon" id="phong_muon" class="form-control text-uppercase" placeholder="Tìm hoặc chọn phòng..." required autocomplete="off">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="btnTogglePhong" title="Chọn phòng"></button>
              </div>

              <div id="menuPhongMuon" class="dropdown-menu w-100 shadow border p-1" style="max-height: 220px; overflow-y: auto; display: none; z-index: 1050;">
                <div id="listPhongOptions">
                  <?php foreach ($phongCoSan as $p): ?>
                    <button type="button" class="dropdown-item py-1 px-2 rounded room-option" data-value="<?= e($p) ?>">
                      <i class="bi bi-door-closed text-primary me-2"></i><?= e($p) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <div id="emptyPhongMsg" class="text-muted small text-center py-2" style="display: none;">Không tìm thấy, có thể gõ trực tiếp tên phòng</div>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-1"></i> Hạn trả dự kiến</label>
            <input type="datetime-local" name="han_tra" class="form-control" value="<?= $macDinh ?>" required>
          </div>
        </div>
      </div>

      <button class="btn btn-primary w-100 py-2 fs-6" id="btnSubmitLoan" disabled>
        <i class="bi bi-check2-circle me-1"></i> Xác nhận cho mượn <span id="btnCountNumber">(0 thiết bị)</span>
      </button>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>