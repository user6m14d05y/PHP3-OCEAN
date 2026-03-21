<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../../axios.js';
import { useRouter } from 'vue-router';

const Products = ref([]);
const Categories = ref([]);

const fetchProducts = async () => {
  try {
    const response = await api.get('/products');
    Products.value = response.data.data.map(item => ({
      id: item.product_id,
      name: item.name,
      price: new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.min_price),
      image: item.thumbnail_url !== "0" ? item.thumbnail_url : 'https://placehold.co/400x500?text=No+Image',
      badge: item.is_featured ? 'Hot' : null
    }));
  } catch (error) {
    console.error('Error fetching products:', error);
  }
};

// State lưu trữ bộ lọc
const selectedCategory = ref('All');
const selectedPriceRange = ref('All');
const sortBy = ref('newest'); // 'newest', 'oldest', 'price-asc', 'price-desc'

// Dữ liệu danh mục
const categories = ref([
    { id: 'All', name: 'Tất cả' },
    { id: 1, name: 'Thời Trang Nam' },
    { id: 2, name: 'Thời Trang Nữ' },
    { id: 3, name: 'Thời Trang Trẻ Em' },
    { id: 4, name: 'Phụ Kiện' }
]);

// Dữ liệu Các khoảng giá
const priceRanges = ref([
    { id: 'All', label: 'Tất cả mức giá' },
    { id: 'under-500k', label: 'Dưới 500.000₫' },
    { id: '500k-1m', label: 'Từ 500.000₫ - 1.000.000₫' },
    { id: 'above-1m', label: 'Trên 1.000.000₫' }
]);

// Dữ liệu sản phẩm mẫu (có giá chuẩn số nguyên và ngày tạo để sắp xếp)
const products = ref([
    {
        id: 1,
        name: 'Áo Khoác Blazer Classic',
        price: 1250000,
        categoryId: 1,
        image: 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: 'Mới',
        date: '2026-03-21'
    },
    {
        id: 2,
        name: 'Đầm Lụa Midi Mùa Thu',
        price: 850000,
        categoryId: 2,
        image: 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: '',
        date: '2026-02-15'
    },
    {
        id: 3,
        name: 'Sơ Mi Cotton Kẻ Sọc',
        price: 450000,
        categoryId: 1,
        image: 'https://images.unsplash.com/photo-1596755094514-f87e32f85e23?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: 'Hot',
        date: '2026-03-10'
    },
    {
        id: 4,
        name: 'Quần Jeans Ống Suông',
        price: 650000,
        categoryId: 1,
        image: 'https://images.unsplash.com/photo-1542272205295-5abcb796bba6?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: '',
        date: '2026-01-20'
    },
    {
        id: 5,
        name: 'Váy Xoè Xếp Ly Tiệc',
        price: 950000,
        categoryId: 2,
        image: 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: '',
        date: '2026-03-01'
    },
    {
        id: 6,
        name: 'Áo Thun Trẻ Em Basic',
        price: 250000,
        categoryId: 3,
        image: 'https://images.unsplash.com/photo-1519238396263-1f1969e6b6eb?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: 'Sale',
        date: '2026-03-15'
    },
    {
        id: 7,
        name: 'Áo Len Cổ Lọ Nữ',
        price: 550000,
        categoryId: 2,
        image: 'https://images.unsplash.com/photo-1551028719-00167b16eac5?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: '',
        date: '2025-11-20'
    },
    {
        id: 8,
        name: 'Áo Sơ Mi Bé Trai Hè',
        price: 320000,
        categoryId: 3,
        image: 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: 'Mới',
        date: '2026-03-18'
    },
    {
        id: 9,
        name: 'Túi Xách Da Minimal',
        price: 1450000,
        categoryId: 4,
        image: 'https://images.unsplash.com/photo-1584916201218-f4242ceb4809?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
        badge: 'Hot',
        date: '2026-03-05'
    }
]);

// Hàm định dạng giá tiền tệ VNĐ (ví dụ: 1.250.000 ₫)
const formatPrice = (value) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

// Hàm tính toán danh sách sản phẩm sau khi Lọc và Sắp Xếp
const filteredProducts = computed(() => {
    let result = [...products.value];

    // 1. Lọc theo danh mục
    if (selectedCategory.value !== 'All') {
        result = result.filter(p => p.categoryId === selectedCategory.value);
    }

    // 2. Lọc theo khoảng giá
    if (selectedPriceRange.value === 'under-500k') {
        result = result.filter(p => p.price < 500000);
    } else if (selectedPriceRange.value === '500k-1m') {
        result = result.filter(p => p.price >= 500000 && p.price <= 1000000);
    } else if (selectedPriceRange.value === 'above-1m') {
        result = result.filter(p => p.price > 1000000);
    }

    // 3. Sắp xếp
    if (sortBy.value === 'newest') {
        result.sort((a, b) => new Date(b.date) - new Date(a.date));
    } else if (sortBy.value === 'oldest') {
        result.sort((a, b) => new Date(a.date) - new Date(b.date));
    } else if (sortBy.value === 'price-asc') {
        result.sort((a, b) => a.price - b.price);
    } else if (sortBy.value === 'price-desc') {
        result.sort((a, b) => b.price - a.price);
    }

    return result;
});

onMounted(() => {
  fetchProducts();
});
</script>

<template>
        <div class="client-products">
            <main class="products-main-layout">
                <!-- Banner trang sản phẩm -->
                <div class="page-header animate-in">
                    <h1>Bộ Sưu Tập Thời Trang</h1>
                    <p>Khám phá bộ sưu tập với hàng ngàn phong cách đón đầu mọi xu hướng. Tự do định hình cá tính của bạn.</p>
                </div>

                <div class="layout-grid">
                    <!-- Cột trái: Bộ lọc Sidebar Nhỏ hơn -->
                    <aside class="sidebar animate-in" style="animation-delay: 0.1s">
                        
                        <!-- LỌC DANH MỤC -->
                        <div class="filter-box" style="font-size: 13px;">
                            <h3 class="filter-title">Danh Mục</h3>
                            <ul class="category-list">
                                <li v-for="cat in categories" :key="cat.id"
                                    :class="{ active: selectedCategory === cat.id }" @click="selectedCategory = cat.id">
                                    <div class="category-item-content">
                                        <span class="category-name">{{ cat.name }}</span>
                                        <span class="category-arrow" v-if="selectedCategory === cat.id">✓</span>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- LỌC KHOẢNG GIÁ BIỂN (Nhỏ gọn) -->
                        <div class="filter-box mt-4" style="font-size: 13px;">
                            <h3 class="filter-title">Mức Giá</h3>
                            <ul class="price-list">
                                <li v-for="price in priceRanges" :key="price.id" 
                                    class="price-item" 
                                    :class="{ active: selectedPriceRange === price.id }"
                                    @click="selectedPriceRange = price.id"
                                >
                                    <div class="custom-radio">
                                        <div class="radio-inner" v-if="selectedPriceRange === price.id"></div>
                                    </div>
                                    <span class="price-label">{{ price.label }}</span>
                                </li>
                            </ul>
                        </div>

                    </aside>

                    <!-- Cột phải: Danh sách sản phẩm (4 thẻ trên 1 hàng) -->
                    <section class="products-content animate-in" style="animation-delay: 0.2s">

                        <!-- Thanh công cụ (Action Bar) -->
                        <div class="action-bar">
                            <div class="results-count">
                                Hiển thị <strong>{{ filteredProducts.length }}</strong> sản phẩm
                            </div>

                            <div class="sort-box">
                                <label for="sortSelector">Sắp xếp:</label>
                                <select id="sortSelector" v-model="sortBy" class="custom-select">
                                    <option value="newest">Sản phẩm mới nhất</option>
                                    <option value="oldest">Sản phẩm cũ nhất</option>
                                    <option value="price-asc">Giá: Thấp đến cao</option>
                                    <option value="price-desc">Giá: Cao đến thấp</option>
                                </select>
                            </div>
                        </div>

                        <!-- Grid Sản Phẩm (Hiển thị CHÍNH XÁC 4 HÀNG NGANG) -->
                        <div class="products-grid" v-if="Products.length > 0">
                            <div class="product-card ocean-card" v-for="product in Products" :key="product.id">
                                <router-link :to="{ name: 'product-detail', params: { id: product.id } }" class="text-decoration-none">
                                <div class="product-img-wrapper">
                                    <span class="product-badge" v-if="product.badge"
                                        :class="{ 'badge-hot': product.badge === 'Hot' }">{{ product.badge }}</span>
                                    <img :src="product.image" :alt="product.name" class="product-img" />
                                    <!-- Hover Action giống Trang Chủ -->
                                    <div class="product-hover-action">
                                        <button class="btn-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="9" cy="21" r="1" />
                                                <circle cx="20" cy="21" r="1" />
                                                <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">{{ product.name }}</h3>
                                    <span class="product-price">{{product.price }}</span>
                                </div>
                                </router-link>
                            </div>
                        </div>

                        <!-- Empty State khi không có sản phẩm -->
                        <div v-else class="empty-state">
                            <h3>Không tìm thấy sản phẩm nào!</h3>
                            <p>Không có sản phẩm nào phù hợp với bộ lọc bạn vừa chọn.</p>
                            <button class="btn-outline" @click="selectedCategory = 'All'; selectedPriceRange = 'All'">Xóa bộ lọc</button>
                        </div>

                    </section>
                </div>
            </main>
        </div>
</template>

<style scoped>
.client-products {
    font-family: var(--font-inter, 'Inter', sans-serif);
    background: #f9fbfd;
    width: 100%;
    color: var(--text-main, #102a43);
    min-height: 100vh;
}

/* Layout Container kéo rộng ra một tí để chứa 4 cột */
.products-main-layout {
    max-width: 1300px;
    margin: 0 auto;
    padding: 40px 16px;
    width: 100%;
}

/* Header */
.page-header {
    text-align: center;
    margin-bottom: 40px;
}

.page-header h1 {
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--ocean-blue, #0288d1);
    margin-bottom: 12px;
}

.page-header p {
    font-size: 1.05rem;
    color: var(--text-muted, #627d98);
    max-width: 600px;
    margin: 0 auto;
}

/* Main Grid (Sidebar + Content) */
.layout-grid {
    display: flex;
    gap: 24px;   /* Khoảng cách gọn lại */
    align-items: flex-start;
}

/* ======== SIDEBAR thu nhỏ lại ======== */
.sidebar {
    width: 250px; /* Cũ là 280px -> Thu gọn lại theo ý muốn người dùng */
    flex-shrink: 0;
    position: sticky;
    top: 40px;
}
.mt-4 {
    margin-top: 24px;
}

.filter-box {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    border: 1px solid var(--border-color, #d9e8f0);
}

.filter-title {
    font-size: 1.15rem;
    font-weight: 800;
    margin-bottom: 16px;
    color: var(--text-main);
    border-bottom: 2px solid var(--ocean-light, #e1f5fe);
    padding-bottom: 10px;
}

/* Category Filter Style */
.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.category-list li {
    margin-bottom: 4px;
    cursor: pointer;
}
.category-item-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    border-radius: 8px;
    transition: all 0.2s;
    color: var(--text-muted, #627d98);
    font-weight: 500;
    font-size: 0.95rem;
}
.category-list li:hover .category-item-content {
    background: var(--ocean-light, #e1f5fe);
    color: var(--ocean-blue, #0288d1);
}
.category-list li.active .category-item-content {
    background: var(--ocean-blue, #0288d1);
    color: white;
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(2, 136, 209, 0.2);
}

/* Price Range Filter Style */
.price-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.price-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 4px;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--text-muted);
}
.price-item:hover {
    color: var(--ocean-blue);
}
.price-item.active {
    color: var(--text-main);
    font-weight: 700;
}
.custom-radio {
    width: 18px;
    height: 18px;
    border: 2px solid #ccc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.price-item.active .custom-radio {
    border-color: var(--ocean-blue);
}
.radio-inner {
    width: 10px;
    height: 10px;
    background: var(--ocean-blue);
    border-radius: 50%;
}

/* ======== MAIN CONTENT ======== */
.products-content {
    flex: 1;
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: white;
    padding: 14px 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    border: 1px solid var(--border-color, #d9e8f0);
}

.results-count {
    font-size: 0.95rem;
    color: var(--text-muted);
}

.results-count strong {
    color: var(--text-main);
    font-weight: 800;
}

.sort-box {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sort-box label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-main);
}

.custom-select {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #c9d6df;
    background: white;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-main);
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s;
}

.custom-select:focus {
    border-color: var(--ocean-blue);
}

/* ======== PRODUCTS GRID (Đúng 4 cột hàng ngang) ======== */
.products-grid {
    display: grid;
    /* Quy định chia bảng thành chính xác 4 cột đều nhau */
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.product-card {
    padding: 0;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #d9e8f0);
    border-radius: 12px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
    border-color: rgba(2, 136, 209, 0.3);
}

/* Cho nhỏ chiều cao ảnh lại một xíu tẹo để bù trừ với việc chia 4 côt */
.product-img-wrapper {
    position: relative;
    width: 100%;
    height: 280px; 
    background: var(--ocean-deepest, #f0f7fa);
    overflow: hidden;
}

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.6s ease;
}

.product-card:hover .product-img {
    transform: scale(1.08);
}

.product-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--seafoam, #26a69a);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 10;
}

.badge-hot {
    background: var(--coral, #ef5350);
}

/* Nút thêm vào giỏ giống Home */
.product-hover-action {
    position: absolute;
    bottom: -40px;
    right: 12px;
    transition: all 0.3s ease;
    z-index: 10;
}

.product-card:hover .product-hover-action {
    bottom: 12px;
}

.btn-icon {
    background: white;
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-main);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: var(--ocean-blue, #0288d1);
    color: white;
    transform: scale(1.1);
}

.product-info {
    padding: 16px 16px 20px 16px; 
}

.product-name {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 6px;
    color: var(--text-main, #102a43);
    /* Giới hạn tên quá dài làm mất tỉ lệ 4 hàng ngang */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-price {
    font-weight: 800;
    color: var(--coral, #ef5350);
    font-size: 1.05rem;
}

/* ======= EMPTY STATE ======= */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    border: 1px dashed var(--ocean-blue);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 1.35rem;
    color: var(--ocean-blue);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 24px;
}

.btn-outline {
    background: transparent;
    color: var(--ocean-blue, #0288d1);
    border: 2px solid var(--ocean-blue, #0288d1);
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline:hover {
    background: var(--ocean-blue, #0288d1);
    color: white;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 1024px) {
    .products-grid {
        /* Màn hình nhỏ thì lùi xuống 3 ô ngang */
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (max-width: 900px) {
    .layout-grid {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        position: static;
    }
    .products-grid {
        /* Tablet thì xuống 2 */
        grid-template-columns: repeat(2, 1fr);
    }
    .action-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
}
</style>
