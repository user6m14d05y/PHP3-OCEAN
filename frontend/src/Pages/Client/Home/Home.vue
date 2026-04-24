<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import api from "../../../axios.js";
import ProductCard from "../../../components/ProductCard.vue";
import ProductSkeleton from "../../../components/ProductSkeleton.vue";
import FlashSaleBoard from "../../../components/FlashSaleBoard.vue";

const router = useRouter();

const Products = ref([]);
const Categories = ref([]);
const categoryProducts = ref({});
const isLoadingFeatured = ref(true);
const isLoadingCategories = ref(true);

// ── Hero Slider ──
const currentSlide = ref(0);
const heroSlides = ref([
    {
        image: "https://images.unsplash.com/photo-1469334031218-e382a71b716b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80",
        subtitle: "BST MỚI 2026",
        title: "Phong Cách<br/>Tối Giản",
        desc: "Định hình cá tính với bộ sưu tập thời trang mới nhất.<br/>Thiết kế tối giản, dễ mặc, dễ phối.",
        btn: "Mua sắm ngay",
        tag: "NEW SEASON"
    },
    {
        image: "https://images.unsplash.com/photo-1441984904996-e0b6ba687e04?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80",
        subtitle: "SUMMER SALE",
        title: "Giảm Giá<br/>Sôi Động",
        desc: "Lên đến 50% cho hơn 1000 sản phẩm với mẫu mã thời thượng nhất.",
        btn: "Khám phá sale",
        tag: "SALE -50%"
    }
]);
let slideInterval = null;

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace(/\/api$/, '');

const NEW_IMAGES = [
    'products/luxury_watch_1776303372051.png','products/leather_wallet_1776303390698.png',
    'products/sunglasses_1776303412493.png','products/silver_necklace_1776303426962.png',
    'products/leather_loafer_1776303445224.png','products/white_sneaker_1776303469345.png',
    'products/womens_clutch_1776303489059.png','products/card_holder_1776303513454.png',
    'products/zippered_wallet_1776303528282.png','products/button_down_shirt_1776303542708.png',
    'products/summer_dress_1776303557050.png','products/denim_jeans_1776303576632.png',
    'products/light_jacket_1776303589362.png','products/leather_belt_1776303603972.png'
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
    const currentPrice = lowest?.effective_price ?? (item.min_price || 0);
    let originalPrice = null;
    if (lowest?.is_on_sale) originalPrice = lowest.price;
    else if (lowest?.compare_at_price > lowest?.price) originalPrice = lowest.compare_at_price;

    let maxDiscount = lowest?.discount_percent || 0;
    if (item.variants?.length) {
        maxDiscount = Math.max(...item.variants.map(v => v.discount_percent || 0), maxDiscount);
    }
    return {
        id: item.product_id,
        name: item.name,
        price: new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(currentPrice),
        originalPrice: originalPrice ? new Intl.NumberFormat("vi-VN", { style: "currency", currency: "VND" }).format(originalPrice) : null,
        discount_percent: maxDiscount,
        is_on_sale: lowest?.is_on_sale || false,
        image: getImageUrl(item.thumbnail_url || item.mainImage?.image_url || null),
        badge: item.is_featured ? "Hot" : null,
        slug: item.slug,
        category_name: item.category_name || '',
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
            image: getImageUrl(item.image_url || null),
        }));

        await Promise.all(
            Categories.value.map(async (cat) => {
                try {
                    const res = await api.get(`/products?limit=4&page=1&category_id=${cat.id}`);
                    categoryProducts.value[cat.id] = (res.data.data || []).map(mapProduct);
                } catch {
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

// ── Countdown ──
const countdown = ref({ days: 0, hours: 0, mins: 0, secs: 0 });
const targetDate = new Date();
targetDate.setDate(targetDate.getDate() + 3);

const countdownInterval = setInterval(() => {
    const now = new Date().getTime();
    const distance = targetDate.getTime() - now;
    if (distance > 0) {
        countdown.value.days  = Math.floor(distance / (1000 * 60 * 60 * 24));
        countdown.value.hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        countdown.value.mins  = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        countdown.value.secs  = Math.floor((distance % (1000 * 60)) / 1000);
    }
}, 1000);

const setSlide = (i) => { currentSlide.value = i; resetSlide(); };
const resetSlide = () => {
    if (slideInterval) clearInterval(slideInterval);
    slideInterval = setInterval(() => {
        currentSlide.value = (currentSlide.value + 1) % heroSlides.value.length;
    }, 5500);
};

// Category icon map — sử dụng SVG paths đơn giản
const catIcons = {
    'thoi-trang-nam': '👔', 'thoi-trang-nu': '👗', 'giay-dep': '👟',
    'tui-xach': '👜', 'phu-kien': '⌚', 'the-thao': '🏃',
    'default': '🏷️'
};
const getCatIcon = (slug) => catIcons[slug] || catIcons['default'];

onMounted(() => {
    fetchProducts();
    fetchCategories();
    resetSlide();
});

onUnmounted(() => {
    if (slideInterval) clearInterval(slideInterval);
    clearInterval(countdownInterval);
});
</script>

<template>
    <main class="home-main">

        <!-- ═══════════════════════════════════════════
             1. HERO BANNER
        ══════════════════════════════════════════════ -->
        <section class="hero-section">
            <div class="hero-slider">
                <div
                    class="slide"
                    v-for="(slide, i) in heroSlides"
                    :key="i"
                    :class="{ active: currentSlide === i }"
                >
                    <img :src="slide.image" alt="banner" class="slide-img" />
                    <div class="slide-overlay"></div>
                    <div class="slide-content">
                        <span class="slide-tag">{{ slide.tag }}</span>
                        <h1 class="slide-title" v-html="slide.title"></h1>
                        <p class="slide-desc" v-html="slide.desc"></p>
                        <button class="btn-hero" @click="router.push('/product')">
                            {{ slide.btn }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Dots -->
                <div class="slide-dots">
                    <span
                        v-for="(s, i) in heroSlides"
                        :key="i"
                        class="dot"
                        :class="{ active: currentSlide === i }"
                        @click="setSlide(i)"
                    ></span>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             2. LUXURY CATEGORY CIRCLES
        ══════════════════════════════════════════════ -->
        <section class="section-inner cat-section" v-if="Categories.length > 0">
            <div class="cat-scroll">
                <router-link
                    v-for="cat in Categories.slice(0, 8)"
                    :key="cat.id"
                    :to="{ name: 'product-category', params: { categorySlug: cat.slug } }"
                    class="luxury-cat-item"
                >
                    <div class="cat-img-wrapper">
                        <img v-if="cat.image" :src="cat.image" :alt="cat.name" class="cat-img" loading="lazy" />
                        <span v-else class="cat-icon-fallback">{{ getCatIcon(cat.slug) }}</span>
                    </div>
                    <span class="luxury-cat-name">{{ cat.name }}</span>
                </router-link>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             3. FEATURED PRODUCTS (Full Width)
        ══════════════════════════════════════════════ -->
        <section class="section-inner section-featured">
            <div class="section-head">
                <h2 class="section-title">Sản phẩm nổi bật</h2>
                <router-link :to="{ name: 'product' }" class="link-more">
                    Xem tất cả
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </router-link>
            </div>
            <div class="products-grid">
                <template v-if="isLoadingFeatured">
                    <ProductSkeleton v-for="i in 8" :key="i" />
                </template>
                <template v-else>
                    <ProductCard
                        v-for="product in Products.slice(0, 8)"
                        :key="product.id"
                        :product="product"
                    />
                </template>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             4. FLASH SALE & PROMO BANNER 
        ══════════════════════════════════════════════ -->
        <section class="promo-banner flash-sale-banner">
            <div class="promo-overlay"></div>
            <div class="promo-inner">
                <div class="promo-left">
                    <span class="promo-tag">⚡ Chương Trình Đặc Biệt</span>
                    <h2 class="promo-title">Giảm đến <span class="highlight">50%</span></h2>
                    <p class="promo-desc">Hàng trăm sản phẩm chất lượng cao. Số lượng có hạn — đừng bỏ lỡ cơ hội sở hữu sẩn phẩm yêu thích với mức giá không tưởng!</p>
                </div>
                <div class="promo-right d-flex justify-content-end">
                    <!-- Sử dụng component FlashSaleBoard hiện có nhưng hiển thị dạng card nổi -->
                    <FlashSaleBoard class="shadow-lg" />
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════
             5. SẢN PHẨM THEO DANH MỤC
        ══════════════════════════════════════════════ -->
        <template v-if="isLoadingCategories">
            <section class="section-inner">
                <div class="section-head">
                    <h2 class="section-title">Đang tải...</h2>
                </div>
                <div class="products-grid">
                    <ProductSkeleton v-for="i in 8" :key="i" />
                </div>
            </section>
        </template>
        <template v-else>
            <section
                v-for="cat in Categories"
                :key="cat.id"
                v-show="categoryProducts[cat.id]?.length > 0"
                class="section-inner"
            >
                <div class="section-head">
                    <h2 class="section-title">{{ cat.name }}</h2>
                    <router-link :to="{ name: 'product-category', params: { categorySlug: cat.slug } }" class="link-more">
                        Xem tất cả
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </router-link>
                </div>
                <div class="products-grid">
                    <ProductCard
                        v-for="product in (categoryProducts[cat.id] || []).slice(0, 4)"
                        :key="product.id"
                        :product="product"
                    />
                </div>
            </section>
        </template>

        <!-- ═══════════════════════════════════════════
             6. TRUST BAR
        ══════════════════════════════════════════════ -->
        <section class="trust-bar">
            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"/><path d="M14 9h4l4 4v5c0 .6-.4 1-1 1h-2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
                </div>
                <h5>Giao hàng 24h</h5>
                <p>Nội thành các tỉnh thành lớn</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </div>
                <h5>Hoàn trả 30 ngày</h5>
                <p>Đổi trả dễ dàng, không phiền toái</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h5>Thanh toán an toàn</h5>
                <p>Bảo mật 100% thông tin cá nhân</p>
            </div>
            <div class="trust-item">
                <div class="trust-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                </div>
                <h5>Hỗ trợ 24/7</h5>
                <p>Đội ngũ CSKH tận tâm, chuyên nghiệp</p>
            </div>
        </section>

    </main>
</template>

<style scoped>
/* ============================================
   TYPOGRAPHY & TOKENS
============================================ */
.home-main {
    width: 100%;
    padding: 0;
    color: #0f172a;
}

/* ============================================
   SECTION WRAPPERS
============================================ */
.section-inner {
    padding: 56px 0;
    border-bottom: 1px solid #f1f5f9;
}

.section-inner:last-of-type {
    border-bottom: none;
}

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
}

.section-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.5px;
    margin: 0;
}

.link-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--ocean-blue, #0288d1);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: gap 0.2s;
}
.link-more:hover { gap: 10px; }

/* ============================================
   1. HERO SLIDER
============================================ */
.hero-section {
    /* Break out of the layout container */
    width: 100vw;
    margin-left: calc(-50vw + 50%);
}

.hero-slider {
    position: relative;
    height: 600px;
    overflow: hidden;
}

.slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.9s ease;
    visibility: hidden;
}

.slide.active {
    opacity: 1;
    visibility: visible;
}

.slide-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 25%;
}

.slide-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(10, 20, 40, 0.80) 0%, rgba(10, 20, 40, 0.35) 60%, transparent 100%);
}

.slide-content {
    position: absolute;
    top: 50%;
    left: 8%;
    transform: translateY(-50%);
    max-width: 520px;
    z-index: 2;
}

.slide-tag {
    display: inline-block;
    background: var(--ocean-blue, #0288d1);
    color: white;
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 2px;
    padding: 5px 14px;
    border-radius: 4px;
    margin-bottom: 20px;
    text-transform: uppercase;
}

.slide-title {
    font-size: 3.8rem;
    font-weight: 900;
    line-height: 1.05;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: -1px;
    margin-bottom: 20px;
    text-shadow: 0 2px 20px rgba(0,0,0,0.3);
}

.slide-desc {
    font-size: 1rem;
    color: #cbd5e1;
    line-height: 1.7;
    margin-bottom: 36px;
}

.btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: var(--ocean-blue, #0288d1);
    color: white;
    border: none;
    padding: 16px 36px;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(2, 136, 209, 0.4);
}

.btn-hero:hover {
    background: #0277bd;
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(2, 136, 209, 0.5);
}

.slide-dots {
    position: absolute;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
    z-index: 10;
}

.dot {
    width: 28px;
    height: 5px;
    background: rgba(255,255,255,0.35);
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.3s;
}

.dot.active {
    background: #ffffff;
    width: 44px;
}

/* ============================================
   2. LUXURY CATEGORY CIRCLES
============================================ */
.cat-section {
    padding: 48px 0 32px;
}

.cat-scroll {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 36px;
    padding: 0 16px;
}

.luxury-cat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    gap: 12px;
    outline: none;
}

.cat-img-wrapper {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #ffffff;
    padding: 3px; 
    border: 1px solid #e2e8f0;
    transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}

.cat-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.cat-icon-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    background: #f1f5f9;
    border-radius: 50%;
}

.luxury-cat-item:hover .cat-img-wrapper {
    border-color: var(--ocean-blue, #0288d1);
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(2, 136, 209, 0.15);
}

.luxury-cat-item:hover .cat-img {
    transform: scale(1.1);
}

.luxury-cat-name {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: color 0.3s;
}

.luxury-cat-item:hover .luxury-cat-name {
    color: var(--ocean-blue, #0288d1);
}

/* ============================================
   3. FEATURED PRODUCTS
============================================ */
/* Removed .section-2col since Featured is now full width */

/* ============================================
   PRODUCT GRID (4-col)
============================================ */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

/* ============================================
   4. FLASH SALE & PROMO BANNER 
============================================ */
.promo-banner {
    position: relative;
    width: 100vw;
    margin-left: calc(-50vw + 50%);
    background-image: url('https://images.unsplash.com/photo-1490481651829-270f644b9ff3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    padding: 72px 0;
    margin-top: 56px;
    margin-bottom: 0;
}

.promo-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(10, 25, 50, 0.92) 0%, rgba(2, 100, 165, 0.85) 100%);
}

.promo-inner {
    position: relative;
    z-index: 1;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 48px;
    flex-wrap: nowrap; /* Prevent wrapping so they stay side-by-side if possible */
}

.promo-left {
    flex: 1;
    max-width: 600px;
}

.promo-tag {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 2px;
    padding: 5px 14px;
    border-radius: 4px;
    margin-bottom: 16px;
    text-transform: uppercase;
}

.promo-title {
    font-size: 3rem;
    font-weight: 900;
    color: white;
    margin-bottom: 12px;
    line-height: 1.1;
}

.promo-title .highlight {
    color: #fbbf24;
}

.promo-desc {
    color: #bfdbfe;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 0;
}

.promo-right {
    flex-shrink: 0;
    width: 480px; 
}

/* ============================================
   6. TRUST BAR
============================================ */
.trust-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: #f1f5f9;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
    margin-top: 56px;
}

.trust-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 40px 24px;
    background: #fff;
    transition: background 0.2s;
}
.trust-item:hover { background: #f0f9ff; }

.trust-icon {
    width: 56px;
    height: 56px;
    background: #e0f2fe;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ocean-blue, #0288d1);
    margin-bottom: 16px;
    transition: all 0.3s;
}

.trust-item:hover .trust-icon {
    background: var(--ocean-blue, #0288d1);
    color: white;
    transform: translateY(-4px);
}

.trust-item h5 {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}

.trust-item p {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

/* ============================================
   RESPONSIVE
============================================ */
@media (max-width: 1200px) {
    .section-2col {
        grid-template-columns: 1fr;
    }
    .col-flash {
        position: static;
        max-width: 480px;
    }
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1024px) {
    .hero-slider { height: 520px; }
    .slide-title { font-size: 3rem; }
    .products-grid { grid-template-columns: repeat(2, 1fr); }
    .trust-bar { grid-template-columns: repeat(2, 1fr); }
    .promo-inner { flex-direction: column; text-align: center; gap: 32px; }
    .promo-right { width: 100%; display: flex; justify-content: center !important; }
}

@media (max-width: 768px) {
    .hero-slider { height: 440px; }
    .slide-title { font-size: 2.4rem; }
    .slide-content { left: 5%; right: 5%; }
    .section-inner { padding: 40px 0; }
    .section-title { font-size: 1.3rem; }
    .promo-title { font-size: 2.2rem; }
    .trust-bar { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .hero-slider { height: 360px; }
    .slide-title { font-size: 2rem; }
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .trust-bar { grid-template-columns: 1fr 1fr; }
    .cd-num { font-size: 1.6rem; }
    .cd-box { min-width: 56px; padding: 12px 14px; }
    .btn-hero { padding: 14px 24px; font-size: 0.9rem; }
}
</style>
