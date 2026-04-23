import { ref } from 'vue';
import api from '@/axios'; // Giả sử axios instance được cấu hình sẵn token JWT
import { Toast } from 'bootstrap';

// Hàm hiển thị Bootstrap Toast động
const showBootstrapToast = (message, type = 'success') => {
    let container = document.getElementById('global-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'global-toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3 mt-5 mt-md-0';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    const bgClass = type === 'success' ? 'text-bg-success' : (type === 'danger' || type === 'error' ? 'text-bg-danger' : (type === 'warning' ? 'text-bg-warning' : 'text-bg-info'));
    
    toastEl.className = `toast align-items-center border-0 ${bgClass}`;
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-medium" style="font-family: 'Inter', sans-serif;">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toastEl);
    const toastInstance = new Toast(toastEl, { delay: 3000 });
    toastInstance.show();

    // Dọn dẹp DOM sau khi ẩn
    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
        if (container.children.length === 0) {
            container.remove();
        }
    });
};

// State global, chỉ khởi tạo 1 lần
const favoriteIds = ref([]);
const isInitialized = ref(false);

// Helper: kiểm tra user đã đăng nhập chưa (chỉ cần dùng sessionStorage)
const isLoggedIn = () => !!sessionStorage.getItem('auth_token');

export function useFavorites() {
    /**
     * Mặc định load ids yêu thích của user
     */
    const fetchFavoriteIds = async () => {
        if (!isLoggedIn()) return; // Chỉ load khi có đăng nhập
        try {
            const response = await api.get('/profile/favorites/ids');
            if (response.data && response.data.status === 'success') {
                favoriteIds.value = response.data.data;
                isInitialized.value = true;
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách yêu thích:', error);
        }
    };

    /**
     * Toggle trái tim (thêm/xoá)
     */
    const toggleFavorite = async (productId) => {
        if (!isLoggedIn()) {
            showBootstrapToast('Vui lòng đăng nhập để yêu thích sản phẩm', 'warning');
            return false;
        }

        // Cập nhật Optimistic UI trước khi call API (cho nhanh nhạy)
        const index = favoriteIds.value.indexOf(productId);
        const originallyFavorited = index !== -1;

        if (originallyFavorited) {
            favoriteIds.value.splice(index, 1);
        } else {
            favoriteIds.value.push(productId);
        }

        try {
            const response = await api.post('/profile/favorites/toggle', { product_id: productId });
            if (response.data && response.data.status === 'success') {
                const customMsg = response.data.message; // Bắt message từ backend (VD: Admin không hỗ trợ)
                
                if (originallyFavorited) {
                    showBootstrapToast(customMsg || 'Đã bỏ yêu thích sản phẩm', 'info');
                } else {
                    showBootstrapToast(customMsg || 'Đã thêm vào yêu thích', 'success');
                }
                return true;
            }
        } catch (error) {
            console.error('Lỗi khi toggle yêu thích:', error);
            // Rollback state nếu lỗi
            if (originallyFavorited) {
                favoriteIds.value.push(productId);
            } else {
                const idx = favoriteIds.value.indexOf(productId);
                if (idx !== -1) favoriteIds.value.splice(idx, 1);
            }
            showBootstrapToast('Có lỗi xảy ra, vui lòng thử lại.', 'danger');
            return false;
        }
    };

    /**
     * Kiểm tra xem 1 product_id có được yêu thích chưa
     */
    const isFavorited = (productId) => {
        return favoriteIds.value.includes(productId);
    };

    // Auto load khi dùng hook (nếu chưa init)
    if (!isInitialized.value && isLoggedIn()) {
        fetchFavoriteIds();
    }

    return {
        favoriteIds,
        fetchFavoriteIds,
        toggleFavorite,
        isFavorited
    };
}
