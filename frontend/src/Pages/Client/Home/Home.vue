<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../../axios.js";
import ProductCard from "../../../components/ProductCard.vue";
import ProductSkeleton from "../../../components/ProductSkeleton.vue";

const router = useRouter();

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
        image: "https://images.unsplash.com/photo-1469334031218-e382a71b716b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80",
        subtitle: "BST MỚI 2026",
        title: "Phong Cách<br />Tối Giản",
        desc: "Định hình cá tính của bạn với bộ sưu tập thời trang mới nhất. <br />Thiết kế tối giản, dễ mặc, dễ phối cho mọi lứa tuổi.",
        btn: "Mua sắm ngay"
    },
    {
        image: "https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80",
        subtitle: "SUMMER SALE",
        title: "Giảm Giá<br />Sôi Động",
        desc: "Lên đến 50% cho hơn 1000 sản phẩm với mẫu mã thời thượng nhất.",
        btn: "Khám phá sale"
    }
]);

let slideInterval = null;

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');

const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6"><rect width="400" height="500" fill="#f4f9f9" /><g transform="translate(130, 230)"><path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#1b8a9e" /><path d="M-80,40 C-100,10 -110,-10 -90,0 C-70,10 -60,20 -80,40 Z" fill="#0f4c5c" /><path d="M-30,80 C20,90 80,80 110,60" fill="none" stroke="#f4f9f9" stroke-width="4" /><path d="M-20,70 C30,80 70,70 100,50" fill="none" stroke="#f4f9f9" stroke-width="4" /><circle cx="100" cy="-10" r="4" fill="#062f3a" /><path d="M80,-40 C80,-60 60,-80 50,-70" fill="none" stroke="#48b8c9" stroke-width="4" stroke-linecap="round"/><path d="M90,-40 C95,-60 110,-70 120,-60" fill="none" stroke="#48b8c9" stroke-width="4" stroke-linecap="round"/><path d="M85,-40 C85,-70 90,-90 90,-90" fill="none" stroke="#48b8c9" stroke-width="4" stroke-linecap="round"/></g><path d="M0,320 Q50,290 100,320 T200,320 T300,320 T400,320 L400,500 L0,500 Z" fill="#8de1ed" opacity="0.6"/><path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#48b8c9" opacity="0.4"/></svg>`);

const NEW_IMAGES = [
    'products/luxury_watch_1776303372051.png',
    'products/leather_wallet_1776303390698.png',
    'products/sunglasses_1776303412493.png',
    'products/silver_necklace_1776303426962.png',
    'products/leather_loafer_1776303445224.png',
    'products/white_sneaker_1776303469345.png',
    'products/womens_clutch_1776303489059.png',
    'products/card_holder_1776303513454.png',
    'products/zippered_wallet_1776303528282.png',
    'products/button_down_shirt_1776303542708.png',
    'products/summer_dress_1776303557050.png',
    'products/denim_jeans_1776303576632.png',
    'products/light_jacket_1776303589362.png',
    'products/leather_belt_1776303603972.png'
];

let globalImageIdx = 0;

const getImageUrl = (path) => {
    if (!path || path === '0') {
        const url = `${BASE_URL}/storage/${NEW_IMAGES[globalImageIdx % NEW_IMAGES.length]}`;
        globalImageIdx++;
        return url;
    }
    if (path.startsWith('http')) return path;
    return `${BASE_URL}/storage/${path}`;
};

const mapProduct = (item) => {
    const lowest = item.lowest_price_variant || item.lowestPriceVariant || null;
    const currentPrice = lowest && lowest.effective_price ? lowest.effective_price : (item.min_price || 0);
    
    let originalPrice = null;
    if (lowest && lowest.is_on_sale) {
        originalPrice = lowest.price;
    } else if (lowest && lowest.compare_at_price > lowest.price) {
        originalPrice = lowest.compare_at_price;
    }

    let maxDiscount = lowest ? lowest.discount_percent || 0 : 0;
    if (item.variants && Array.isArray(item.variants)) {
        const variantsDiscounts = item.variants.map(v => v.discount_percent || 0);
        if (variantsDiscounts.length > 0) {
            maxDiscount = Math.max(...variantsDiscounts, maxDiscount);
        }
    }

    return {
        id: item.product_id,
        name: item.name,
        price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(currentPrice),
        originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
        discount_percent: maxDiscount,
        is_on_sale: lowest ? lowest.is_on_sale : false,
        image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
        badge: item.is_featured ? "Hot" : null,
        slug: item.slug,
    };
};

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
                    <div class="hero-dark-overlay"></div>
                    <!-- Lớp Glassmorphism overlay cho text readability -->
                    <div class="banner-content glass-card p-5">
                        <span class="banner-subtitle">{{ slide.subtitle }}</span>
                        <h1 class="banner-title layered-text" v-html="slide.title"></h1>
                        <p class="banner-desc fs-5 lh-lg" v-html="slide.desc"></p>
                        
                        <div class="d-flex align-items-center gap-4 mt-4 flex-wrap">
                            <button class="btn-primary btn-hero-slider" @click="router.push('/product')">
                                {{ slide.btn }} <i class="fas fa-arrow-right ms-2"></i>
                            </button>

                            <div class="hero-countdown-glass" v-if="i === 1 && countdown.days >= 0">
                                <div class="cd-label text-white fw-bold mb-1" style="font-size: 0.8rem; letter-spacing: 1px;">⚡ FLASH SALE KẾT THÚC SAU</div>
                                <div class="d-flex gap-2">
                                    <div class="glass-cd-box"><span class="num">{{ countdown.hours + (countdown.days * 24) }}</span></div>
                                    <span class="text-white fw-bold ds-colon">:</span>
                                    <div class="glass-cd-box"><span class="num">{{ countdown.mins }}</span></div>
                                    <span class="text-white fw-bold ds-colon">:</span>
                                    <div class="glass-cd-box"><span class="num">{{ countdown.secs }}</span></div>
                                </div>
                            </div>
                        </div>
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
                        <button class="btn btn-warning btn-lg fw-bold rounded-pill px-5 mt-4 text-white shadow-sm border-0" style="background-color: var(--coral);">Khám phá ưu đãi</button>
                    </div>
                    
                    <div class="promo-right-countdown text-white text-center">
                        <h5 class="mb-4 text-uppercase fw-semibold tracking-wider" style="color: #cbd5e1;">Thời gian còn lại</h5>
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
        <section class="trust-section text-center py-5 d-flex flex-wrap justify-content-center gap-5 border-top">
            <div class="trust-item">
                <div class="trust-icon-wrap"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"/><path d="M14 9h4l4 4v5c0 .6-.4 1-1 1h-2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></div>
                <h5 class="fw-bold mt-3">Giao hàng cực nhanh</h5><p class="text-muted fs-6 mb-0">Trong vòng 24h đối với nội thành</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon-wrap"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></div>
                <h5 class="fw-bold mt-3">Hoàn trả linh hoạt</h5><p class="text-muted fs-6 mb-0">Đổi trả trong 30 ngày dễ dàng</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon-wrap"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div>
                <h5 class="fw-bold mt-3">Thanh toán an toàn</h5><p class="text-muted fs-6 mb-0">Bảo mật tuyệt đối thông tin</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon-wrap"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg></div>
                <h5 class="fw-bold mt-3">Hỗ trợ 24/7</h5><p class="text-muted fs-6 mb-0">Chăm sóc khách hàng tận tâm</p>
            </div>
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
    background: var(--ocean-blue, #0F172A);
    color: white;
    padding: 12px 28px;
    border-radius: var(--radius-micro);
    font-weight: 700;
    border: 1px solid var(--ocean-blue, #0F172A);
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary:hover {
    background: transparent;
    color: var(--ocean-blue, #0F172A);
    transform: none;
    box-shadow: none;
}

.btn-large {
    padding: 16px 40px;
    font-size: 0.95rem;
}

.btn-outline {
    display: inline-block;
    background: transparent;
    color: var(--ocean-blue, #0F172A);
    border: 1px solid var(--ocean-blue, #0F172A);
    padding: 12px 28px;
    border-radius: var(--radius-micro);
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.btn-outline:hover {
    background: var(--ocean-blue, #0F172A);
    color: white;
    box-shadow: none;
}

.home-main {
    padding: 0;
    width: 100%;
}

.home-main section {
    padding: 64px 0;
}

.banner-section {
    padding-top: 0 !important;
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
    width: 100vw;
    margin-left: calc(-50vw + 50%);
    height: 640px;
    border-radius: 0;
    overflow: hidden;
    margin-bottom: 0;
    box-shadow: none;
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

.hero-dark-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(15, 23, 42, 0.85) 0%, rgba(15, 23, 42, 0.2) 60%, transparent 100%);
    z-index: 1;
}

.glass-card {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 10%;
    background: transparent;
    backdrop-filter: none;
    border: none;
    padding: 0;
    max-width: 500px;
    box-shadow: none;
    z-index: 2;
}

.banner-subtitle {
    color: #e2e8f0;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: 0.85rem;
    margin-bottom: 12px;
    display: inline-block;
}

.banner-title {
    font-size: 3.5rem;
    font-weight: 900;
    line-height: 1.1;
    margin-bottom: 24px;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.layered-text {
    text-shadow: 0px 4px 10px rgba(0, 0, 0, 0.4), 
                 -2px -2px 0px rgba(255, 255, 255, 0.1),
                 4px 4px 0px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 5;
}

.banner-desc {
    color: #cbd5e1;
}

.btn-hero-slider {
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: #ffffff;
    border: none;
    padding: 16px 44px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    box-shadow: 0 4px 14px rgba(2, 132, 199, 0.4);
    transition: all 0.3s ease;
}

.btn-hero-slider:hover {
    background: linear-gradient(135deg, #0284c7, #0ea5e9);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(2, 132, 199, 0.6);
}

.hero-countdown-glass {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 12px 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.glass-cd-box {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    padding: 6px 10px;
    color: #ffffff;
    font-weight: 800;
    font-size: 1.25rem;
    line-height: 1;
    min-width: 44px;
    text-align: center;
}
.ds-colon {
    font-size: 1.25rem;
    line-height: 1.8;
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
    padding-top: 40px !important;
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
    width: 80px;
    height: 80px;
    min-height: 80px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: #334155;
    margin-bottom: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
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
    font-weight: 700; 
    font-size: 1rem; 
    color: #1e293b; 
    transition: color 0.3s;
}
.cat-circle-item:hover .cat-name {
    color: var(--ocean-blue, #0288d1);
}

/* Grid */
 .products-section, .category-tabs-section {
    margin-bottom: 100px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 24px;
}

/* Promo Parallax Banner */
.promo-parallax-banner {
    width: 100vw;
    margin-left: calc(-50vw + 50%);
    border-radius: 0;
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
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(2, 132, 199, 0.8) 100%);
    z-index: 1;
}

.promo-text-wrap {
    position: relative;
    z-index: 2;
    padding: 80px 60px;
}

.cd-box {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    padding: 20px 16px;
    min-width: 90px;
    display: flex;
    flex-direction: column;
    align-items: center;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.cd-num { font-size: 2.5rem; font-weight: 900; line-height: 1; margin-bottom: 8px; font-family: 'Inter', sans-serif; }
.cd-lbl { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.7; font-weight: 600; }

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
    max-width: 220px;
}
.trust-icon-wrap {
    width: 64px;
    height: 64px;
    margin: 0 auto;
    background: #e0f2fe;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ocean-blue);
    transition: all 0.3s ease;
}
.trust-item:hover .trust-icon-wrap {
    background: var(--ocean-blue);
    color: #ffffff;
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(2, 132, 199, 0.2);
}
/* Web responsiveness */
@media (max-width: 1280px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }
}

@media (max-width: 1024px) {
    .promo-text-wrap {
        flex-direction: column;
        text-align: center;
        padding: 40px 24px;
    }
    .promo-left p { margin: 0 auto; }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .home-main section {
        padding: 48px 0;
    }
}

@media (max-width: 768px) {
    .glass-card {
        top: 50%;
        bottom: auto;
        left: 5%;
        right: 5%;
        text-align: center;
    }
    
    .banner-title { font-size: 2.5rem; }
    .hero-slider { height: 480px; }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .home-main section {
        padding: 40px 0;
    }
    
    .cd-box {
        min-width: 70px;
        padding: 16px 12px;
    }
    .cd-num {
        font-size: 2rem;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .hero-slider { height: 400px; }
    .banner-title { font-size: 2rem; }
    .btn-hero-slider { padding: 12px 32px; font-size: 0.9rem; }
    
    .home-main section {
        padding: 32px 0;
    }
    
    .promo-text-wrap {
        padding: 32px 16px;
    }
    .promo-right-countdown .gap-3 {
        gap: 8px !important;
    }
    .cd-box {
        min-width: 60px;
        padding: 12px 8px;
    }
    .cd-num {
        font-size: 1.6rem;
    }
    .cd-lbl {
        font-size: 0.7rem;
    }
}
</style>
