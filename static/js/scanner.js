let _scanner = null;
let _currentElementId = null;
let _currentOnResult = null;

function applyCameraFlip(elementId) {
  const container = document.getElementById(elementId);
  if (container) {
    container.classList.add('mirrored');
    const vid = container.querySelector('video');
    if (vid) {
      vid.style.transform = 'scaleX(-1)';
      vid.style.webkitTransform = 'scaleX(-1)';
    }
  }
}

async function startScanner(elementId, onResult) {
  _currentElementId = elementId;
  _currentOnResult = onResult;

  const container = document.getElementById(elementId);
  if (!container) return;

  container.innerHTML = `
    <div class="d-flex flex-column align-items-center justify-content-center py-5 text-muted" style="min-height: 250px;">
      <div class="spinner-border text-primary mb-2" role="status" style="width: 2rem; height: 2rem;"></div>
      <div class="small fw-semibold text-dark">Đang khởi động camera...</div>
      <div class="small text-muted mt-1">Vui lòng chấp nhận quyền truy cập camera</div>
    </div>
  `;

  if (_scanner) { 
    await stopScanner(); 
  }
  
  _scanner = new Html5Qrcode(elementId);
  let daQuet = false;

  const handleScanSuccess = (text) => {
    if (daQuet) return;
    daQuet = true;
    if (_currentOnResult) {
      _currentOnResult(text.trim());
    }
    setTimeout(() => { daQuet = false; }, 1500);
  };

  const qrConfig = { 
    fps: 15, 
    qrbox: { width: 250, height: 250 },
    disableFlip: false
  };

  const onCameraStarted = () => {
    applyCameraFlip(elementId);
    setTimeout(() => applyCameraFlip(elementId), 150);
    setTimeout(() => applyCameraFlip(elementId), 500);
  };

  try {
    try {
      await _scanner.start({ facingMode: "environment" }, qrConfig, handleScanSuccess, () => {});
      onCameraStarted();
      return;
    } catch (e1) {}

    try {
      await _scanner.start({ facingMode: "user" }, qrConfig, handleScanSuccess, () => {});
      onCameraStarted();
      return;
    } catch (e2) {}

    const devices = await Html5Qrcode.getCameras();
    if (devices && devices.length > 0) {
      await _scanner.start(devices[0].id, qrConfig, handleScanSuccess, () => {});
      onCameraStarted();
      return;
    }

    throw new Error("Không tìm thấy thiết bị camera khả dụng.");
  } catch (err) {
    container.innerHTML = `
      <div class="d-flex flex-column align-items-center justify-content-center p-3 text-center" style="min-height: 250px; background: #f8fafc; border-radius: 8px;">
        <i class="bi bi-camera-video-off fs-2 text-secondary mb-2"></i>
        <div class="fw-bold text-dark mb-1">Khung quét Camera</div>
        <div class="small text-muted mb-3" style="max-width: 300px;">
          Chưa kết nối được camera (hoặc chưa cấp quyền). Bạn có thể <strong>nhập mã tài sản vào ô bên cạnh</strong> hoặc thử lại camera.
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRetryCam">
          <i class="bi bi-arrow-clockwise"></i> Thử kết nối lại camera
        </button>
      </div>
    `;
    const btnRetry = document.getElementById("btnRetryCam");
    if (btnRetry) {
      btnRetry.addEventListener("click", () => {
        startScanner(_currentElementId, _currentOnResult);
      });
    }
  }
}

async function stopScanner() {
  if (_scanner) {
    try { 
      await _scanner.stop(); 
    } catch (e) {}
    _scanner = null;
  }
}
