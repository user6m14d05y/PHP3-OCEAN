<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import api from "../../../axios.js";
import ProductCard from "../../../components/ProductCard.vue";
import ProductSkeleton from "../../../components/ProductSkeleton.vue";

const Products = ref([]);
const Categories = ref([]);
const categoryProducts = ref({});
const isLoadingFeatured = ref(true);
const isLoadingCategories = ref(true);

const currentCategoryId = ref(null);

// Hero Slider Banner
const currentSlide = ref(0);
const heroSlides = ref([
    {
        image: "http://localhost:8383/storage/banners/banner_1.png",
        subtitle: "BST MỚI 2026",
        title: "Phong Cách<br />Thời Thượng",
        desc: "Định hình cá tính của bạn với bộ sưu tập thời trang mới nhất. <br />Thiết kế tối giản, dễ mặc, dễ phối cho mọi lứa tuổi.",
        btn: "Mua sắm ngay"
    },
    {
        image: "https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80",
        subtitle: "SUMMER SALE",
        title: "Giảm Giá<br />Mùa Hè Sôi Động",
        desc: "Lên đến 50% cho hơn 1000 sản phẩm với mẫu mã thời thượng nhất.",
        btn: "Khám phá sale"
    }
]);

let slideInterval = null;

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const getImageUrl = (path) => {
    if (!path || path === '0') return 'https://placehold.co/400x500?text=No+Image';
    if (path.startsWith('http')) return path;
    return `${BASE_URL}/storage/${path}`;
};

const mapProduct = (item) => ({
    id: item.product_id,
    name: item.name,
    price: new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(item.min_price),
    image: getImageUrl(item.thumbnail_url),
    badge: item.is_featured ? "Hot" : null,
    slug: item.slug,
});

const fetchProducts = async () => {
    isLoadingFeatured.value = true;
    try {
        const response = await api.get("/productsFeatured");
        Products.value = response.data.data.map(mapProduct);
    } catch (error) {
        console.error("Error fetching products:", error);
    } finally {
        isLoadingFeatured.value = false;
    }
};

const fetchCategories = async () => {
    isLoadingCategories.value = true;
    try {
        const response = await api.get("/categories");
        Categories.value = response.data.data.map((item) => ({
            id: item.category_id,
            name: item.name,
            slug: item.slug,
        }));
        
        if (Categories.value.length > 0) {
            currentCategoryId.value = Categories.value[0].id;
        }

        // Fetch products cho từng danh mục
        await Promise.all(
            Categories.value.map(async (cat) => {
                try {
                    const res = await api.get(`/products?limit=4&page=1&category_id=${cat.id}`);
                    categoryProducts.value[cat.id] = (res.data.data || []).map(mapProduct);
                } catch (e) {
                    categoryProducts.value[cat.id] = [];
                }
            })
        );
    } catch (error) {
        console.error("Error fetching categories:", error);
    } finally {
        isLoadingCategories.value = false;
    }
};

const setSlideMenu = (index) => {
    currentSlide.value = index;
    resetSlideInterval();
};

const resetSlideInterval = () => {
    if (slideInterval) clearInterval(slideInterval);
    slideInterval = setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % heroSlides.value.length;
    }, 5000);
};

onMounted(() => {
    fetchProducts();
    fetchCategories();
    resetSlideInterval();
});

onUnmounted(() => {
    if (slideInterval) clearInterval(slideInterval);
});

// Countdown logic for Promo Banner
const countdown = ref({ days: 0, hours: 0, mins: 0, secs: 0 });
// Set target 3 days from now for demo
const targetDate = new Date();
targetDate.setDate(targetDate.getDate() + 3);

setInterval(() => {
    const now = new Date().getTime();
    const distance = targetDate.getTime() - now;

    if (distance > 0) {
        countdown.value.days = Math.floor(distance / (1000 * 60 * 60 * 24));
        countdown.value.hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        countdown.value.mins = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        countdown.value.secs = Math.floor((distance % (1000 * 60)) / 1000);
    }
}, 1000);
</script>

<template>
    <main class="home-main">
        <!-- 1. Cải tiến Khu vực Hero Banner với Carousel & Glassmorphism -->
        <section class="banner-section animate-in">
            <div class="hero-slider">
                <div class="slide-container" v-for="(slide, i) in heroSlides" :key="i" :class="{'active-slide': currentSlide === i}">
                    <img :src="slide.image" alt="banner" class="slider-bg-img" />
                    <!-- Lớp Glassmorphism overlay cho text readability -->
                    <div class="banner-content glass-card">
                        <span class="banner-subtitle">{{ slide.subtitle }}</span>
                        <h1 class="banner-title text-main" v-html="slide.title"></h1>
                        <p class="banner-desc fs-6 text-muted" v-html="slide.desc"></p>
                        <button class="btn-primary btn-large mt-4">
                            {{ slide.btn }} <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Slider Indicators -->
                <div class="slider-indicators">
                    <span v-for="(slide, i) in heroSlides" :key="i" class="dot" :class="{'active': currentSlide === i}" @click="setSlideMenu(i)"></span>
                </div>
            </div>
        </section>

        <!-- 2. Sản phẩm Shop By Category Cards -->
        <section class="shop-by-category animate-in" style="animation-delay: 0.1s" v-if="Categories.length > 0">
            <div class="category-cards-wrapper d-flex justify-content-center gap-4 overflow-auto pb-4 pt-4 px-3">
                <router-link :to="{ name: 'product', query: { category: cat.slug } }" class="cat-circle-item text-decoration-none" v-for="cat in Categories.slice(0, 6)" :key="cat.id">
                    <div class="cat-icon-container">
                        <i class="fas fa-tag"></i>
                    </div>
                    <span class="cat-name">{{ cat.name }}</span>
                </router-link>
            </div>
        </section>

        <!-- 3. Khu vực Sản phẩm nổi bật -->
        <section class="products-section animate-in" style="animation-delay: 0.15s">
            <div class="section-header d-flex justify-content-between align-items-end mb-4">
                <h2 class="section-title mb-0">Sản phẩm nổi bật</h2>
                <router-link :to="{name: 'product'}" class="link-all">Xem tất cả <i class="fas fa-chevron-right ms-1"></i></router-link>
            </div>

            <div class="products-grid">
                <template v-if="isLoadingFeatured">
                    <ProductSkeleton v-for="i in 8" :key="i" />
                </template>
                <template v-else>
                    <ProductCard v-for="product in Products" :key="product.id" :product="product" />
                </template>
            </div>
        </section>

        <!-- 4. Promo Banner Mới (Parallax + Countdown) -->
        <section class="promo-section animate-in" style="animation-delay: 0.2s">
            <div class="promo-parallax-banner">
                <div class="promo-overlay"></div>
                <div class="promo-text-wrap d-flex align-items-center justify-content-between flex-wrap gap-4">
                    <div class="promo-left text-white">
                        <h3 class="fw-bold mb-3 display-5">Siêu Sale Đầu Mùa</h3>
                        <p class="fs-5 mb-0" style="max-width: 500px;">
                            Sở hữu ngay những items hot nhất với mức giảm lên đến <strong class="text-warning">50%</strong>. Giao hàng miễn phí.
                        </p>
                        <button class="btn btn-warning btn-lg fw-bold rounded-pill px-5 mt-4 text-dark shadow-sm border-0" style="background-color: #f7b731; color:  #102a43;">Khám phá ưu đãi</button>
                    </div>
                    
                    <div class="promo-right-countdown text-white text-center">
                        <h5 class="mb-3 text-uppercase fw-semibold tracking-wider">Thời gian còn lại</h5>
                        <div class="d-flex gap-3 justify-content-center">
                            <div class="cd-box"><span class="cd-num">{{ countdown.days }}</span><span class="cd-lbl">Ngày</span></div>
                            <div class="cd-box"><span class="cd-num">{{ countdown.hours }}</span><span class="cd-lbl">Giờ</span></div>
                            <div class="cd-box"><span class="cd-num">{{ countdown.mins }}</span><span class="cd-lbl">Phút</span></div>
                            <div class="cd-box"><span class="cd-num">{{ countdown.secs }}</span><span class="cd-lbl">Giây</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Sản phẩm theo danh mục (Giữ layout theo chiều dọc như ban đầu) -->
        <template v-if="isLoadingCategories">
            <section class="products-section animate-in" style="animation-delay: 0.25s">
                <div class="section-header d-flex justify-content-between align-items-end mb-4">
                    <h2 class="section-title mb-0">Đang tải danh mục...</h2>
                </div>
                <div class="products-grid">
                    <ProductSkeleton v-for="i in 8" :key="i" />
                </div>
            </section>
        </template>
        <template v-else>
            <template v-for="item in Categories" :key="item.id">
                <section
                    v-if="categoryProducts[item.id] && categoryProducts[item.id].length > 0"
                    class="products-section animate-in"
                    style="animation-delay: 0.25s"
                >
                    <div class="section-header d-flex justify-content-between align-items-end mb-4">
                        <h2 class="section-title mb-0">{{ item.name }}</h2>
                        <router-link :to="{ name: 'product', query: { category: item.slug } }" class="link-all">Xem tất cả <i class="fas fa-chevron-right ms-1"></i></router-link>
                    </div>

                    <div class="products-grid">
                        <ProductCard
                            v-for="product in categoryProducts[item.id].slice(0, 4)"
                            :key="product.id"
                            :product="product"
                        />
                    </div>
                </section>
            </template>
        </template>
        
        <!-- Ext: Social Proof (Hiệu ứng) -->
        <section class="trust-section text-center py-5 d-flex flex-wrap justify-content-center gap-5 mt-4 border-top">
            <div class="trust-item"><i class="fas fa-truck fa-2x text-ocean-blue mb-3"></i><h5 class="fw-bold">Giao hàng cực nhanh</h5><p class="text-muted fs-6">Trong vòng 24h đối với nội thành</p></div>
            <div class="trust-item"><i class="fas fa-sync fa-2x text-ocean-blue mb-3"></i><h5 class="fw-bold">Hoàn trả linh hoạt</h5><p class="text-muted fs-6">Đổi trả trong 30 ngày dễ dàng</p></div>
            <div class="trust-item"><i class="fas fa-shield-alt fa-2x text-ocean-blue mb-3"></i><h5 class="fw-bold">Thanh toán an toàn</h5><p class="text-muted fs-6">Bảo mật tuyệt đối thông tin</p></div>
            <div class="trust-item"><i class="fas fa-headset fa-2x text-ocean-blue mb-3"></i><h5 class="fw-bold">Hỗ trợ 24/7</h5><p class="text-muted fs-6">Chăm sóc khách hàng tận tâm</p></div>
        </section>

    </main>
</template>

<style scoped>
.client-home {
    font-family: var(--font-inter, "Inter", sans-serif);
    background: transparent;
    width: 100%;
    color: var(--text-main, #102a43);
    display: flex;
    flex-direction: column;
}

/* Utilities */
.text-ocean-blue { color: var(--ocean-blue, #0288d1); }
.tracking-wider { letter-spacing: 0.1em; }

/* Buttons */
.btn-primary {
    background: var(--ocean-blue, #0288d1);
    color: white;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--ocean-bright, #03a9f4);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(2, 136, 209, 0.3);
}

.btn-large {
    padding: 14px 32px;
    font-size: 1.1rem;
    border-radius: 30px;
}

.btn-outline {
    display: inline-block;
    background: transparent;
    color: var(--ocean-blue, #0288d1);
    border: 2px solid var(--ocean-blue, #0288d1);
    padding: 12px 28px;
    border-radius: 30px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-outline:hover {
    background: var(--ocean-blue, #0288d1);
    color: white;
    box-shadow: 0 4px 12px rgba(2,136,209,0.2);
}

.home-main {
    padding: 24px 0;
    width: 100%;
}

/* Titles */
.section-title {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-main, #102a43);
    text-transform: capitalize;
}

.link-all {
    color: var(--ocean-blue, #0288d1);
    font-weight: 700;
    text-decoration: none;
    font-size: 1rem;
    transition: color 0.2s;
}

.link-all:hover {
    color: var(--ocean-bright, #03a9f4);
}

/* 1. Carousel Hero Banner (New Design) */
.hero-slider {
    position: relative;
    width: 100%;
    height: 500px;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 50px;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
}

.slide-container {
    position: absolute;
    inset: 0;
    opacity: 0;
    visibility: hidden;
    transition: all 0.8s ease-in-out;
}

.slide-container.active-slide {
    opacity: 1;
    visibility: visible;
}

.slider-bg-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 20%;
}

/* Glassmorphism Card on Hero */
.glass-card {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 8%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255, 0.4);
    padding: 40px;
    border-radius: 20px;
    max-width: 480px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.1);
}

.banner-subtitle {
    color: var(--ocean-blue, #0288d1);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 0.85rem;
    margin-bottom: 12px;
    display: inline-block;
}

.banner-title {
    font-size: 3rem;
    font-weight: 900;
    line-height: 1.15;
    margin-bottom: 16px;
    color: #1a202c;
}

.slider-indicators {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    z-index: 10;
}

.dot {
    width: 32px;
    height: 6px;
    background: rgba(255,255,255,0.4);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.dot.active {
    background: var(--ocean-blue, #0288d1);
    width: 48px;
}

/* Category Circles */
.shop-by-category {
    margin-bottom: 60px;
}
.category-cards-wrapper {
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.category-cards-wrapper::-webkit-scrollbar { display: none; }

.cat-circle-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    min-width: 90px;
}

.cat-icon-container {
    width: 72px;
    height: 72px;
    min-height: 72px;  /* Prevent shrinking */
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    color: var(--ocean-blue, #0288d1);
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}

.cat-circle-item:hover .cat-icon-container {
    background: white;
    box-shadow: 0 0 0 2px var(--ocean-blue, #0288d1), 0 8px 16px rgba(2, 136, 209, 0.15);
    color: var(--ocean-bright, #03a9f4);
    transform: translateY(-6px);
}

.cat-name { 
    font-weight: 600; 
    font-size: 0.95rem; 
    color: #475569; 
    transition: color 0.3s;
}
.cat-circle-item:hover .cat-name {
    color: var(--ocean-blue, #0288d1);
}

/* Grid */
 .products-section, .category-tabs-section {
    margin-bottom: 80px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 30px;
}

/* Promo Parallax Banner */
.promo-parallax-banner {
    width: 100%;
    border-radius: 20px;
    background-image: url('https://images.unsplash.com/photo-1490481651829-270f644b9ff3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
    background-attachment: fixed;
    background-position: center;
    background-size: cover;
    position: relative;
    overflow: hidden;
    margin-bottom: 80px;
}

.promo-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.4) 100%);
    z-index: 1;
}

.promo-text-wrap {
    position: relative;
    z-index: 2;
    padding: 80px 60px;
}

.cd-box {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 16px;
    min-width: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.cd-num { font-size: 2rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
.cd-lbl { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }

/* Tabs Design */
.btn-tab-pill {
    background: #f1f5f9;
    color: #475569;
    border: 2px solid transparent;
    padding: 10px 24px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 1.05rem;
    transition: all 0.3s ease;
}

.btn-tab-pill:hover, .btn-tab-pill.active {
    background: var(--ocean-blue, #0288d1);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(2,136,209,0.3);
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-in {
    animation: fadeIn 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
    opacity: 0;
}

/* Trust block */
.trust-item {
    max-width: 200px;
}

/* Web responsiveness */
@media (max-width: 991px) {
    .promo-text-wrap {
        flex-direction: column;
        text-align: center;
        padding: 50px 30px;
    }
    .promo-left p { margin: 0 auto; }
}

@media (max-width: 768px) {
    .glass-card {
        top: auto;
        bottom: 10%;
        left: 5%;
        right: 5%;
        transform: none;
        padding: 24px;
    }
    
    .hero-slider { height: 600px; }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }
}
</style>
