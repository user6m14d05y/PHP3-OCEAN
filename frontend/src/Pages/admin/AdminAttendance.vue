<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from '../../axios'; // Axios instance đã được config authentication

// --- TOAST STATE & LOGIC ---
const toastVisible = ref(false);
let toastTimer = null;
const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  toastVisible.value = true;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toastVisible.value = false; }, 3000);
};

// --- ATTENDANCE LOGIC ---
const loading = ref(false);
const attendanceNote = ref('');
const currentTime = ref('');
let clockInterval = null;

const videoElement = ref(null);
const canvasElement = ref(null);
let videoStream = null;

const startCamera = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        videoStream = stream;
        if (videoElement.value) {
            videoElement.value.srcObject = stream;
        }
    } catch (err) {
        showToast("Không thể truy cập camera. Vui lòng cấp quyền!", "error");
    }
};

const stopCamera = () => {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
};

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', { hour12: false });
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
    startCamera();
});

onUnmounted(() => {
    clearInterval(clockInterval);
    stopCamera();
});

// Hàm lấy GPS bằng Promise
const getGeolocation = () => {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error("Trình duyệt của bạn không hỗ trợ định vị."));
        } else {
            navigator.geolocation.getCurrentPosition(
                (position) => resolve(position),
                (error) => reject(error)
            );
        }
    });
};

const captureImage = () => {
    if (!videoElement.value || !canvasElement.value) return null;
    const canvas = canvasElement.value;
    const context = canvas.getContext('2d');
    const width = videoElement.value.videoWidth || 640;
    const height = videoElement.value.videoHeight || 480;
    
    if (width === 0 || height === 0) return null;
    
    canvas.width = width;
    canvas.height = height;
    context.drawImage(videoElement.value, 0, 0, width, height);
    return canvas.toDataURL('image/jpeg', 0.8);
};

const handleCheckIn = async () => {
  loading.value = true;
  try {
      const position = await getGeolocation();
      const imageBase64 = captureImage();
      
      if (!imageBase64) {
          throw new Error("Không thể chụp ảnh từ camera, hãy đảm bảo camera đang hoạt động!");
      }
      
      const payload = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          note: attendanceNote.value,
          image: imageBase64
      };

      const response = await api.post('/admin/attendance/check-in', payload);
      showToast(response.data.message || 'Check-in thành công!', 'success');
      attendanceNote.value = ''; // Reset note
  } catch (error) {
     if (error.code === error.PERMISSION_DENIED) {
         showToast("Bạn cần cấp quyền truy cập vị trí để check-in!", 'error');
     } else {
         const msg = error.response?.data?.message || error.message || "Đã xảy ra lỗi khi Check-in";
         showToast(msg, 'error');
     }
  } finally {
      loading.value = false;
  }
};

const handleCheckOut = async () => {
  loading.value = true;
  try {
      const position = await getGeolocation();
      const imageBase64 = captureImage();
      
      if (!imageBase64) {
          throw new Error("Không thể chụp ảnh từ camera, hãy đảm bảo camera đang hoạt động!");
      }
      
      const payload = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
          image: imageBase64
      };
      
      const response = await api.post('/admin/attendance/check-out', payload);
      showToast(response.data.message || 'Check-out thành công!', 'success');
      attendanceNote.value = '';
  } catch (error) {
     if (error.code === error.PERMISSION_DENIED) {
         showToast("Bạn cần cấp quyền truy cập vị trí để check-out!", 'error');
     } else {
         const msg = error.response?.data?.message || error.message || "Đã xảy ra lỗi khi Check-out";
         showToast(msg, 'error');
     }
  } finally {
      loading.value = false;
  }
};

</script>
<template>
  <div class="attendance-container p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0 fw-bold text-gray-800">Chấm Công - Ca Làm Việc</h2>
      <div class="clock-badge">
        <i class="bi bi-clock me-2"></i>{{ currentTime }}
      </div>
    </div>

    <div class="row g-4">
      <!-- Cột Trái: Hướng dẫn -->
      <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body p-4">
            <h5 class="card-title fw-bold text-primary mb-4">
              <i class="bi bi-info-circle me-2"></i>Hướng dẫn chấm công
            </h5>
            
            <div class="stepper">
                <div class="step mb-4">
                    <div class="step-icon bg-blue-100 text-blue-600">1</div>
                    <div class="step-content">
                        <h6 class="fw-bold mb-1">Cấp quyền vị trí</h6>
                        <p class="text-muted small mb-0">Hệ thống yêu cầu truy cập GPS để xác minh bạn đang ở cửa hàng. Vui lòng chọn "Allow" khi trình duyệt yêu cầu.</p>
                    </div>
                </div>
                <div class="step mb-4">
                    <div class="step-icon bg-green-100 text-green-600">2</div>
                    <div class="step-content">
                        <h6 class="fw-bold mb-1">Check-in khi tới ca</h6>
                        <p class="text-muted small mb-0">Nhấn nút "Check-in" khi bạn vừa tới cửa hàng để bắt đầu ca làm việc.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-icon bg-red-100 text-red-600">3</div>
                    <div class="step-content">
                        <h6 class="fw-bold mb-1">Check-out khi về</h6>
                        <p class="text-muted small mb-0">Kết thúc ca, hãy ghi chú lại các báo cáo (nếu có) và nhấn "Check-out".</p>
                    </div>
                </div>
            </div>
            
          </div>
        </div>
      </div>

      <!-- Cột Phải: Hành động -->
      <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center text-center">
            
            <div class="mb-4 w-100 px-3">
               <video ref="videoElement" autoplay playsinline class="w-100 rounded shadow-sm border" style="max-height: 250px; background: #000; object-fit: cover;"></video>
               <canvas ref="canvasElement" style="display: none;"></canvas>
            </div>

            <!-- Ghi chú nếu muốn lưu -->
            <div class="w-100 mb-4" style="max-width: 400px;">
                <label class="form-label text-start w-100 fw-medium">Ghi chú công việc hôm nay (Không bắt buộc)</label>
                <textarea v-model="attendanceNote" class="form-control" rows="2" placeholder="Ví dụ: Ca sáng đông khách, đã nhập hàng..."></textarea>
            </div>

            <div v-if="loading" class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            
            <div v-else class="w-100 d-flex justify-content-center gap-3">
              <button 
                @click="handleCheckIn" 
                class="btn btn-primary d-flex align-items-center justify-content-center py-2 px-4 rounded-pill fw-bold shadow-sm"
                style="min-width: 180px;"
              >
                <i class="bi bi-box-arrow-in-right me-2 fs-5"></i>
                CHECK-IN
              </button>
              
              <button 
                @click="handleCheckOut" 
                class="btn btn-outline-danger d-flex align-items-center justify-content-center py-2 px-4 rounded-pill fw-bold"
                style="min-width: 180px;"
              >
                <i class="bi bi-box-arrow-left me-2 fs-5"></i>
                CHECK-OUT
              </button>
            </div>

            <div class="mt-4 text-muted small">
                Tọa độ GPS sẽ tự động được thu thập khi bạn nhấn nút.
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- ===== TOAST ===== -->
    <Transition name="attendance-toast">
      <div v-if="toastVisible" class="attendance-toast" :class="'attendance-toast-' + toast.type">
        {{ toast.message }}
      </div>
    </Transition>
  </div>
</template>



<style scoped>
.attendance-container {
    max-width: 1100px;
    margin: 0 auto;
}

.clock-badge {
    background: #eef2ff;
    color: #4f46e5;
    padding: 8px 16px;
    border-radius: 50px;
    font-weight: bold;
    font-size: 1.1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* Stepper Style */
.stepper {
    display: flex;
    flex-direction: column;
}

.step {
    display: flex;
    align-items: flex-start;
}

.step-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 15px;
    flex-shrink: 0;
}

.bg-blue-100 { background-color: #dbeafe; }
.text-blue-600 { color: #2563eb; }

.bg-green-100 { background-color: #dcfce3; }
.text-green-600 { color: #16a34a; }

.bg-red-100 { background-color: #fee2e2; }
.text-red-600 { color: #dc2626; }

/* Toast */
.attendance-toast {
  position: fixed;
  bottom: 30px;
  right: 30px;
  padding: 12px 24px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 9999;
  font-size: 0.95rem;
}

.attendance-toast-success { background: #26a69a; }
.attendance-toast-error { background: #ef5350; }

.attendance-toast-enter-active { transition: all 0.3s ease; }
.attendance-toast-leave-active { transition: all 0.2s ease; }
.attendance-toast-enter-from { opacity: 0; transform: translateX(40px); }
.attendance-toast-leave-to { opacity: 0; transform: translateX(40px); }
</style>
