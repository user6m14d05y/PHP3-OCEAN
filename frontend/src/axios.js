import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8383/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
    timeout: 30000,
});

// Request interceptor: tự động gắn JWT token + fix FormData Content-Type
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("auth_token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Quan trọng: Khi gửi FormData, phải XÓA Content-Type
    // để browser tự set multipart/form-data VỚI boundary.
    // Nếu không xóa, PHP không parse được $_FILES → hasFile() = false → path = 0
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type'];
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor: tự động xử lý 401 (token hết hạn)
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.status === 401) {
      // Xóa token và thông tin user
      localStorage.removeItem('auth_token');
      sessionStorage.removeItem('user');
      
      // Redirect về trang login (nếu chưa ở trang login)
      if (window.location.pathname !== '/client/login') {
        window.location.href = '/client/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;