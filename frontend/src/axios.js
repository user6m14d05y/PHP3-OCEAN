import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8383/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 30000,
});

// ── Helpers: đọc/ghi token từ sessionStorage (SessionSync xử lý cross-tab) ──
export const getToken = () =>
    sessionStorage.getItem('auth_token');

export const getUser = () => {
    try {
        const raw = sessionStorage.getItem('user');
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const saveToken = (token) => {
    // Luôn lưu vào sessionStorage; SessionSync tự chia sẻ sang tab mới
    sessionStorage.setItem('auth_token', token);
};

const clearAuth = () => {
    localStorage.removeItem('auth_token');   // xóa nếu còn từ session cũ
    localStorage.removeItem('user');
    sessionStorage.removeItem('auth_token');
    sessionStorage.removeItem('user');
    sessionStorage.removeItem('ocean_chatbot_messages');
    sessionStorage.removeItem('ocean_chatbot_history');
};

// ── Flag ngăn vòng lặp refresh vô hạn ──────────────────────────────────────
let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
    failedQueue.forEach(prom => {
        if (error) prom.reject(error);
        else prom.resolve(token);
    });
    failedQueue = [];
};

// ── Request interceptor: tự động gắn JWT token ─────────────────────────────
api.interceptors.request.use(
    (config) => {
        const token = getToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Quan trọng: Khi gửi FormData, phải XÓA Content-Type
        // để browser tự set multipart/form-data VỚI boundary.
        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// ── Response interceptor: tự động refresh token khi 401 ────────────────────
api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const originalRequest = error.config;

        // Nếu không phải 401 hoặc đây là chính request refresh → từ chối luôn
        if (
            !error.response ||
            error.response.status !== 401 ||
            originalRequest._retry ||
            originalRequest.url?.includes('/refresh') ||
            originalRequest.url?.includes('/login')
        ) {
            return Promise.reject(error);
        }

        // Không có token → không cần refresh
        const currentToken = getToken();
        if (!currentToken) {
            return Promise.reject(error);
        }

        // Nếu đang refresh rồi → cho các request khác vào hàng đợi
        if (isRefreshing) {
            return new Promise((resolve, reject) => {
                failedQueue.push({ resolve, reject });
            }).then(token => {
                originalRequest.headers.Authorization = `Bearer ${token}`;
                return api(originalRequest);
            }).catch(err => Promise.reject(err));
        }

        originalRequest._retry = true;
        isRefreshing = true;

        try {
            // Thử refresh token
            const response = await api.post('/refresh');
            const newToken = response.data.access_token;

            if (!newToken) throw new Error('No token in refresh response');

            saveToken(newToken);
            api.defaults.headers.common['Authorization'] = `Bearer ${newToken}`;
            processQueue(null, newToken);

            // Retry request gốc với token mới
            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            return api(originalRequest);

        } catch (refreshError) {
            processQueue(refreshError, null);
            clearAuth();

            // Chỉ redirect nếu đang ở trang cần auth
            if (window.location.pathname !== '/client/login') {
                window.dispatchEvent(new CustomEvent('auth-logout'));
                window.location.href = '/client/login';
            }

            return Promise.reject(refreshError);
        } finally {
            isRefreshing = false;
        }
    }
);

export default api;