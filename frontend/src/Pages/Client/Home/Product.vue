<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import api from '../../../axios.js';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();
const Products = ref([]);
const Categories = ref([]);

// State lưu trữ bộ lọc
const selectedCategory = ref('All');
const selectedSubcategory = ref('All'); // Thêm state cho sub category
const selectedPriceRange = ref('All');
const sortBy = ref('newest'); // 'newest', 'oldest', 'price-asc', 'price-desc'

// Dữ liệu Các khoảng giá
const priceRanges = ref([
    { id: 'All', label: 'Tất cả mức giá' },
    { id: 'under-500k', label: 'Dưới 500.000₫' },
    { id: '500k-1m', label: 'Từ 500.000₫ - 1.000.000₫' },
    { id: 'above-1m', label: 'Trên 1.000.000₫' }
]);

const fetchProducts = async () => {
    try {
        // Gọi API lấy toàn bộ sản phẩm (hoặc một số lượng lớn, dùng /products thay vì /productsAll nếu sản phẩm đang là bản nháp)
        const response = await api.get('/products?limit=100');
        Products.value = response.data.data.map(item => ({
            id: item.product_id,
            name: item.name,
            price: Number(item.min_price || 0), // Lưu giá thô để so sánh (number)
            formatted_price: new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.min_price || 0), // Giá hiển thị
            image: item.thumbnail_url !== "0" ? item.thumbnail_url : 'https://placehold.co/400x500?text=No+Image',
            badge: item.is_featured ? 'Hot' : null,
            slug: item.slug,
            category_id: item.category_id,
            date: item.created_at
        }));
    } catch (error) {
        console.error('Error fetching products:', error);
    }
};

const fetchCategories = async () => {
    try {
        const response = await api.get('/categories');
        Categories.value = response.data.data;
    } catch (error) {
        console.error('Error fetching categories:', error);
    }
};

const gotoDetail = (id) => {
    router.push({ name: 'product-detail', params: { id: id } });
}

// Đọc query param "category" từ URL và set bộ lọc tương ứng
const skipCategoryWatch = ref(false);

const applyCategoryFromURL = () => {
    const catId = route.query.category;
    if (!catId) {
        selectedCategory.value = 'All';
        selectedSubcategory.value = 'All';
        return;
    }

    const numId = Number(catId);

    // Kiểm tra xem catId có phải là danh mục cha không
    const isParent = Categories.value.find(c => c.category_id === numId);
    if (isParent) {
        selectedCategory.value = numId;
        selectedSubcategory.value = 'All';
        return;
    }

    // Kiểm tra xem catId có phải là danh mục con không
    for (const parent of Categories.value) {
        if (parent.children) {
            const child = parent.children.find(c => c.category_id === numId);
            if (child) {
                skipCategoryWatch.value = true;
                selectedCategory.value = parent.category_id;
                selectedSubcategory.value = numId;
                return;
            }
        }
    }

    // Fallback
    selectedCategory.value = numId;
    selectedSubcategory.value = 'All';
};

// Theo dõi khi danh mục cha thay đổi thì reset danh mục con
watch(selectedCategory, () => {
    if (skipCategoryWatch.value) {
        skipCategoryWatch.value = false;
        return;
    }
    selectedSubcategory.value = 'All';
});

// Theo dõi khi URL query thay đổi (navigate từ mega dropdown)
watch(() => route.query.category, () => {
    if (Categories.value.length > 0) {
        applyCategoryFromURL();
    }
});

// Tính toán danh sách danh mục con tùy thuộc vào danh mục cha được chọn
const subcategories = computed(() => {
    if (selectedCategory.value === 'All') return [];
    const parent = Categories.value.find(c => c.category_id === selectedCategory.value);
    return parent && parent.children ? parent.children : [];
});

// Sử dụng computed property để tự động lọc thay vì function gọi 1 lần
const filteredProducts = computed(() => {
    let result = [...Products.value];

    // 1. Lọc theo danh mục
    if (selectedCategory.value !== 'All') {
        if (selectedSubcategory.value !== 'All') {
            // Lọc chính xác theo danh mục con
            result = result.filter(p => p.category_id === selectedSubcategory.value);
        } else {
            // Lọc bao gồm cả danh mục cha VÀ TẤT CẢ các danh mục con của nó
            const parent = Categories.value.find(c => c.category_id === selectedCategory.value);
            const validCategoryIds = [selectedCategory.value];

            if (parent && parent.children) {
                parent.children.forEach(child => validCategoryIds.push(child.category_id));
            }

            result = result.filter(p => validCategoryIds.includes(p.category_id));
        }
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
        result.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
    } else if (sortBy.value === 'oldest') {
        result.sort((a, b) => new Date(a.date || 0) - new Date(b.date || 0));
    } else if (sortBy.value === 'price-asc') {
        result.sort((a, b) => a.price - b.price);
    } else if (sortBy.value === 'price-desc') {
        result.sort((a, b) => b.price - a.price);
    }

    return result;
});

onMounted(async () => {
    await Promise.all([fetchProducts(), fetchCategories()]);
    applyCategoryFromURL();
});
</script>

<template>
    <div class="client-products">
        <main class="products-main-layout">
            <!-- Banner trang sản phẩm -->
            <div class="page-header animate-in">
                <h1>Bộ Sưu Tập Thời Trang</h1>
                <p>Khám phá bộ sưu tập với hàng ngàn phong cách đón đầu mọi xu hướng. Tự do định hình cá tính của bạn.
                </p>
            </div>

            <div class="layout-grid">
                <!-- Cột trái: Bộ lọc Sidebar Nhỏ hơn -->
                <aside class="sidebar animate-in" style="animation-delay: 0.1s">

                    <!-- LỌC DANH MỤC -->
                    <div class="filter-box" style="font-size: 13px;">
                        <h3 class="filter-title">Danh Mục</h3>
                        <ul class="category-list">
                            <!-- Active tất cả -->
                            <li :class="{ active: selectedCategory === 'All' }" @click="selectedCategory = 'All'"> <div class="category-item-content"><span class="category-name">Tất cả</span> <span class="category-arrow" v-if="selectedCategory === 'All'">✓</span></div></li>
                            <li v-for="cat in Categories" :key="cat.category_id" :class="{ active: selectedCategory === cat.category_id }" @click="selectedCategory = cat.category_id">
                                <div class="category-item-content">
                                    <span class="category-name">{{ cat.name }}</span>
                                    <span class="category-arrow" v-if="selectedCategory === cat.category_id">✓</span>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- LỌC KHOẢNG GIÁ BIỂN (Nhỏ gọn) -->
                    <div class="filter-box mt-4" style="font-size: 13px;">
                        <h3 class="filter-title">Mức Giá</h3>
                        <ul class="price-list">
                            <li v-for="price in priceRanges" :key="price.id" class="price-item"
                                :class="{ active: selectedPriceRange === price.id }"
                                @click="selectedPriceRange = price.id">
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

                    <div class="subcategories-bar mb-3" v-if="subcategories.length > 0">
                        <button class="btn btn-outline-primary" :class="{ active: selectedSubcategory === 'All' }"
                            @click="selectedSubcategory = 'All'">
                            Tất cả
                        </button>
                        <button class="btn btn-outline-primary ms-2" v-for="subcat in subcategories"
                            :key="subcat.category_id" :class="{ active: selectedSubcategory === subcat.category_id }"
                            @click="selectedSubcategory = subcat.category_id">
                            {{ subcat.name }}
                        </button>
                    </div>

                    <div class="products-grid" v-if="filteredProducts.length > 0">
                        <div class="product-card ocean-card" v-for="product in filteredProducts" :key="product.id">
                            <div class="text-decoration-none">
                                <div class="product-img-wrapper">
                                    <span class="product-badge" v-if="product.badge"
                                        :class="{ 'badge-hot': product.badge === 'Hot' }">{{ product.badge }}</span>
                                    <img @click=gotoDetail(product.id) :src="product.image" :alt="product.name"
                                        class="product-img" />
                                    <div class="product-hover-action">
                                        <button class="btn-icon">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <circle cx="9" cy="21" r="1" />
                                                <circle cx="20" cy="21" r="1" />
                                                <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">{{ product.name }}</h3>
                                    <span class="product-price">{{ product.formatted_price }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State khi không có sản phẩm -->
                    <div v-else class="empty-state">
                        <h3>Không tìm thấy sản phẩm nào!</h3>
                        <p>Không có sản phẩm nào phù hợp với bộ lọc bạn vừa chọn.</p>
                        <button class="btn-outline" @click="selectedCategory = 'All'; selectedPriceRange = 'All'">Xóa bộ
                            lọc</button>
                    </div>

                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.client-products {
    font-family: var(--font-inter, 'Inter', sans-serif);
    width: 100%;
    color: var(--text-main, #102a43);
    min-height: 100vh;
}

/* Layout Container kéo rộng ra một tí để chứa 4 cột */
.products-main-layout {
    padding: 24px 0;
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
    gap: 24px;
    /* Khoảng cách gọn lại */
    align-items: flex-start;
}

/* ======== SIDEBAR thu nhỏ lại ======== */
.sidebar {
    width: 250px;
    /* Cũ là 280px -> Thu gọn lại theo ý muốn người dùng */
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
    height: 315px;
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
