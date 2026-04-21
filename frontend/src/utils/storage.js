/**
 * Helper để tạo URL storage tĩnh từ backend.
 * Dùng chung cho mọi nơi cần hiển thị ảnh từ backend storage.
 *
 * - Nếu path đã là URL đầy đủ (http/https) → trả nguyên
 * - Nếu path là đường dẫn tương đối → nối với VITE_BASE_URL
 *
 * @param {string} path - Đường dẫn ảnh (vd: "products/abc.jpg" hoặc "https://...")
 * @returns {string} URL đầy đủ
 */
export function storageUrl(path) {
    if (!path || path === '0') return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;

    const base = (
        import.meta.env.VITE_BASE_URL ||
        import.meta.env.VITE_API_URL?.replace(/\/api$/, '') ||
        `${window.location.protocol}//${window.location.hostname}:8383`
    );

    // Đảm bảo không bị double slash
    const cleanBase = base.replace(/\/+$/, '');
    const cleanPath = path.replace(/^\/+/, '');

    return `${cleanBase}/storage/${cleanPath}`;
}

/**
 * Trả về base URL của API (không có /api suffix).
 * Dùng cho các trường hợp cần nối path tùy ý vào API base.
 */
export function apiBaseUrl() {
    return (
        import.meta.env.VITE_BASE_URL ||
        import.meta.env.VITE_API_URL?.replace(/\/api$/, '') ||
        `${window.location.protocol}//${window.location.hostname}:8383`
    );
}
