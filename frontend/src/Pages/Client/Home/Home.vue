<script setup>
import { ref, onMounted } from 'vue';
import api from '../../../axios.js';

const Products = ref([]);
const Categories = ref([]);

const fetchProducts = async () => {
    try {
<<<<<<< HEAD
        const response = await api.get('/products');
=======
        const response = await api.get('/productsFeatured');
>>>>>>> origin/binhbc
        Products.value = response.data.data.map(item => ({
            id: item.product_id,
            name: item.name,
            price: new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.min_price),
            image: item.thumbnail_url !== "0" ? item.thumbnail_url : 'https://placehold.co/400x500?text=No+Image',
            badge: item.is_featured ? 'Hot' : null,
            slug: item.slug
        }));
    } catch (error) {
        console.error('Error fetching products:', error);
    }
};


const fetchCategories = async () => {
    try {
        const response = await api.get('/categories');
        Categories.value = response.data.data.map(item => ({
            id: item.category_id,
            name: item.name,
            image: item.image_url !== "0" ? item.image_url : 'https://placehold.co/400x500?text=No+Image',
        }));
    } catch (error) {
        console.error('Error fetching categories:', error);
    }
};

onMounted(() => {
    fetchProducts();
    fetchCategories();
});

</script>

<template>
    <main class="home-main">
        <!-- Bố cục 1: Banner -->
        <section class="banner-section animate-in">
            <img src="http://localhost:8383/storage/banners/banner_1.png" alt="banner" class="w-100 rounded-4" />
            <div class="banner-content ">
                <span class="banner-subtitle mt-5">BST MỚI 2026</span>
                <h1 class="banner-title text-white">Phong Cách<br />Thời Thượng</h1>
                <p class="banner-desc fs-5 text-white">Định hình cá tính của bạn với bộ sưu tập thời trang mới nhất. <br>
                    Thiết kế tối giản,
                    dễ mặc,
                    dễ phối cho mọi lứa tuổi.</p>
                <button class="btn-primary btn-large mt-5">Mua sắm ngay -></button>
            </div>
        </section>

        <!-- Bố cục 2: Sản phẩm nổi bật -->
        <section class="products-section animate-in" style="animation-delay: 0.1s">
            <div class="section-header">
                <h2 class="section-title">Sản phẩm nổi bật</h2>
                <a href="#" class="link-all">Xem tất cả →</a>
            </div>

            <div class="products-grid">
                <div class="product-card ocean-card" v-for="product in Products" :key="product.id">
                    <router-link :to="{ name: 'product-detail', params: { slug: product.slug } }"
                        class="text-decoration-none">
                        <div class="product-img-wrapper">
                            <!-- <span class="product-badge" v-if="product.badge" :class="{'badge-hot': product.badge === 'Hot'}">{{ product.badge }}</span> -->
                            <img :src="product.image" :alt="product.name" class="product-img" />
                            <div class="product-hover-action">
                                <button class="btn-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <circle cx="9" cy="21" r="1" />
                                        <circle cx="20" cy="21" r="1" />
                                        <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">{{ product.name }}</h3>
                            <span class="product-price">{{ product.price }}</span>
                        </div>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Bố cục 3: Banner quảng cáo -->
        <section class="promo-section animate-in" style="animation-delay: 0.2s">
            <div class="promo-banner ocean-card">
                <div class="promo-text">
                    <h3>Siêu Sale Đầu Mùa</h3>
                    <p>Sở hữu ngay những items hot nhất với mức giảm lên đến <strong>50%</strong>. Giao hàng miễn phí
                        toàn quốc
                        cho đơn từ 500.000đ.</p>
                    <button class="btn-outline">Khám phá ưu đãi</button>
                </div>
                <div class="promo-art">
                    <img src="https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80"
                        alt="Promo Image" />
                </div>
            </div>
        </section>

        <section v-for="item in Categories" class="products-section animate-in" style="animation-delay: 0.1s">
            <div class="section-header">
                <h2 class="section-title">{{ item.name }}</h2>
                <a href="#" class="link-all">Xem tất cả →</a>
            </div>

            <div class="products-grid">
                <div class="product-card ocean-card" v-for="product in Products" :key="product.id">
                    <router-link :to="{ name: 'product-detail', params: { slug: product.slug } }"
                        class="text-decoration-none">
                        <div class="product-img-wrapper">
                            <span class="product-badge" v-if="product.badge"
                                :class="{ 'badge-hot': product.badge === 'Hot' }">{{
                                    product.badge }}</span>
                            <img :src="product.image" :alt="product.name" class="product-img" />
                            <div class="product-hover-action">
                                <button class="btn-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">{{ product.name }}</h3>
                            <span class="product-price">{{ product.price }}</span>
                        </div>
                    </router-link>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.client-home {
    font-family: var(--font-inter, 'Inter', sans-serif);
    background: transparent;
    width: 100%;
    color: var(--text-main, #102a43);
    display: flex;
    flex-direction: column;
}

/* Buttons */
.btn-primary {
    background: var(--ocean-blue, #0288d1);
    color: white;
    padding: 8px 20px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: var(--ocean-bright, #03a9f4);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(2, 136, 209, 0.2);
}

.btn-large {
    padding: 12px 28px;
    font-size: 1.05rem;
    border-radius: 10px;
}

.btn-outline {
    background: transparent;
    color: var(--ocean-blue, #0288d1);
    border: 2px solid var(--ocean-blue, #0288d1);
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline:hover {
    background: var(--ocean-blue, #0288d1);
    color: white;
}

/* Main Layout */
.home-main {
    padding: 24px 0;
    width: 100%;
}

/* 1. Banner Section */
.banner-section {
    position: relative;
    gap: 48px;
    margin-bottom: 60px;
    background: #ffffff;
    border-radius: 20px;
    /* padding: 40px; */
    border: 1px solid var(--border-color, #d9e8f0);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
}

.banner-content {
    flex: 1;
    position: absolute;
    top: 40px;
    left: 40px;
}

.banner-subtitle {
    color: var(--ocean-blue, #0288d1);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-size: 0.85rem;
    margin-bottom: 12px;
    display: block;
}

.banner-title {
    font-size: 3rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 20px;
}

.banner-title .highlight {
    color: var(--ocean-blue, #0288d1);
}

.banner-desc {
    color: var(--text-muted, #627d98);
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 30px;
}

.banner-image {
    flex: 1;
    height: 380px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}

.banner-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 20%;
}

/* Shared Titles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 24px;
}

.section-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-main, #102a43);
    margin-bottom: 24px;
}

.products-section .section-title {
    margin-bottom: 0;
}

.link-all {
    color: var(--ocean-blue, #0288d1);
    font-weight: 600;
    text-decoration: none;
    font-size: 0.95rem;
    transition: color 0.2s;
}

.link-all:hover {
    color: var(--ocean-bright, #03a9f4);
}

/* 2. Products Section */
.products-section {
    margin-bottom: 60px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 24px;
}

.product-card {
    padding: 0;
    /* Changed from 16px to 0 so image hits borders */
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #d9e8f0);
    border-radius: 12px;
    overflow: hidden;
    /* Important for rounding image corners at top */
}

.product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    border-color: rgba(2, 136, 209, 0.3);
}

.product-img-wrapper {
    position: relative;
    width: 100%;
    height: 320px;
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
    /* Zoom in on hover */
}

.product-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--seafoam, #26a69a);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 10;
}

.badge-hot {
    background: var(--coral, #ef5350);
}

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
    width: 40px;
    height: 40px;
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
    padding: 20px;
}

.product-info.center {
    text-align: center;
}

.product-info.center .product-name {
    margin-bottom: 0;
}

.product-name {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--text-main, #102a43);
}

.product-price {
    font-weight: 800;
    color: var(--coral, #ef5350);
    font-size: 1.1rem;
}

/* 3. Promo Banner */
.promo-section {
    margin-bottom: 60px;
}

.promo-banner {
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(2, 136, 209, 0.03) 0%, rgba(79, 195, 247, 0.08) 100%);
    border: 1px solid rgba(2, 136, 209, 0.15);
    border-radius: 20px;
    overflow: hidden;
    position: relative;
}

.promo-text {
    flex: 1;
    padding: 48px;
}

.promo-text h3 {
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--ocean-blue, #0288d1);
    margin-bottom: 16px;
}

.promo-text p {
    font-size: 1.1rem;
    color: var(--text-muted, #627d98);
    margin-bottom: 24px;
    line-height: 1.5;
    max-width: 500px;
}

.promo-text strong {
    color: var(--coral, #ef5350);
    font-size: 1.2rem;
}

.promo-art {
    flex: 1;
    height: 320px;
}

.promo-art img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    clip-path: polygon(10% 0, 100% 0, 100% 100%, 0% 100%);
}

/* 4. Categories Section */
.categories-section {
    margin-bottom: 60px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
}

.category-block {
    cursor: pointer;
}

.category-block .product-img-wrapper {
    height: 360px;
    /* A bit taller for categories */
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
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
@media (max-width: 900px) {
    .banner-section {
        flex-direction: column;
        text-align: center;
        padding: 32px 20px;
    }

    .banner-image {
        width: 100%;
        height: 280px;
    }

    .promo-banner {
        flex-direction: column;
    }

    .promo-art {
        width: 100%;
        height: 200px;
    }

    .promo-art img {
        clip-path: none;
    }

    .promo-text {
        padding: 32px;
        text-align: center;
    }

    .categories-grid {
        grid-template-columns: 1fr;
    }
}
</style>