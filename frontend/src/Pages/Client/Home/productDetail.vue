<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '@/axios';
// Kỹ thuật cơ bản: 
// Lấy ID từ URL (nếu có API, ta sẽ dùng ID này để fetch() dữ liệu)
const route = useRoute();
const slug = route.params.slug;
const product = ref(null);

const fetchProduct = async () => {
    try {
        const response = await api.get(`/products/${slug}`);
        product.value = response.data;
    } catch (error) {
        console.error("Error fetching product:", error);
    }
};

const isDescriptionExpanded = ref(false);
const activeImageIndex = ref(0);
const quantity = ref(1);

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const increaseQuantity = () => quantity.value++;
const decreaseQuantity = () => { if (quantity.value > 1) quantity.value-- };
onMounted(() => {
    fetchProduct();
});

</script>

<template>
  <main class="product-detail-wrapper" v-if="product">
    <!-- Breadcrumb (Đường dẫn) -->
    <nav class="breadcrumb">
      <router-link to="/">Trang chủ</router-link>
      <span class="separator">/</span>
      <router-link to="/product">Sản phẩm</router-link>
      <span class="separator">/</span>
      <span class="current-page">{{ product.name }}</span>
    </nav>

    <!-- Main Content: Ảnh và Info -->
    <section class="product-main-grid">
      
      <!-- Cột Trái: Ảnh sản phẩm (Gallery) -->
      <div class="product-gallery">
        <!-- Ảnh chính -->
        <div class="main-image-container ocean-card">
          <img :src="product.images[activeImageIndex]" :alt="product.name" class="main-image animate-fade-in" :key="activeImageIndex" />
        </div>
        
        <!-- Danh sách Ảnh nhỏ (Thumbnails) -->
        <div class="thumbnail-list">
          <div 
            v-for="(img, index) in product.images" 
            :key="index"
            class="thumbnail-item"
            :class="{ 'active': activeImageIndex === index }"
            @click="activeImageIndex = index"
          >
            <img :src="img" :alt="`${product.name} - ảnh ${index + 1}`" />
          </div>
        </div>
      </div>

      <!-- Cột Phải: Thông tin sản phẩm -->
      <div class="product-info-box">
        <div class="category-badge">{{ product.category.name }}</div>
        <h1 class="product-title">{{ product.name }}</h1>
        
        <!-- Đánh giá sao -->
        <div class="product-rating">
          <div class="stars">
            <i class="fas fa-star" v-for="i in 5" :key="i" :class="{'active': i <= Math.round(product.rating)}"></i>
          </div>
          <span class="rating-text">{{ product.rating  ?? 0}} <span class="reviews-count">({{ product.reviewsCount }} đánh giá)</span></span>
        </div>

        <!-- Giá tiền -->
        <div class="product-pricing">
          <span class="current-price">{{ formatPrice(product.min_price) }}</span>
          <span class="original-price" v-if="product.originalPrice">{{ formatPrice(product.originalPrice) }}</span>
          <span class="discount-badge" v-if="product.originalPrice">
            -{{ Math.round((1 - product.price / product.originalPrice) * 100) }}%
          </span>
        </div>

        <!-- Mô tả ngắn (Kỹ thuật) -->
        <div class="short-description" v-html="product.short_description">
        </div>

        <!-- Chức năng Số lượng & Mua hàng -->
        <div class="purchase-actions">
          <div class="quantity-selector">
            <button @click="decreaseQuantity"><i class="fas fa-minus"></i></button>
            <input type="number" v-model="quantity" readonly />
            <button @click="increaseQuantity"><i class="fas fa-plus"></i></button>
          </div>
          <button class="btn-primary btn-addToCart">
            <i class="fas fa-cart-plus"></i> Thêm vào giỏ
          </button>
        </div>
        <button class="btn-primary btn-buyNow">Mua ngay</button>

        <!-- Tiện ích đi kèm -->
        <div class="service-perks">
          <div class="perk-item"><i class="fas fa-truck-fast"></i> Giao hàng miễn phí toàn quốc</div>
          <div class="perk-item"><i class="fas fa-rotate-left"></i> Đổi trả dễ dàng trong 7 ngày</div>
          <div class="perk-item"><i class="fas fa-shield-halved"></i> Bảo hành chính hãng 12 tháng</div>
        </div>
      </div>
    </section>

    <!-- Hàng dưới: Tabs Mô tả chi tiết & Đánh giá -->
    <section class="product-details-reviews">
      <!-- Cột Trái: Mô tả chi tiết -->
      <div class="content-box description-section ocean-card">
        <h2 class="section-title">Chi tiết sản phẩm</h2>
        <div class="long-description" :class="{ 'expanded': isDescriptionExpanded }">
          <div v-html="product.description"></div>
          <div class="fade-overlay" v-if="!isDescriptionExpanded"></div>
        </div>
        <button class="btn-outline toggle-desc-btn" @click="isDescriptionExpanded = !isDescriptionExpanded">
          {{ isDescriptionExpanded ? 'Thu gọn' : 'Xem thêm nội dung' }}
          <i class="fas" :class="isDescriptionExpanded ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>
      </div>

      <!-- Cột Phải: Feedback & Đánh giá -->
      <div class="content-box reviews-section ocean-card">
        <h2 class="section-title">Khách hàng đánh giá</h2>
        
        <div class="review-list">
          <div class="review-item" v-for="review in product.reviews" :key="review.id">
            <div class="review-header">
              <img :src="review.avatar" :alt="review.user" class="reviewer-avatar" />
              <div class="reviewer-info">
                <h4 class="reviewer-name">{{ review.user }}</h4>
                <div class="stars small">
                  <i class="fas fa-star active" v-for="i in review.rating" :key="`star-${i}`"></i>
                  <i class="fas fa-star" v-for="i in 5 - review.rating" :key="`empty-${i}`"></i>
                </div>
              </div>
              <span class="review-date">{{ review.date }}</span>
            </div>
            <p class="review-content">{{ review.content }}</p>
          </div>
        </div>
        
        <button class="btn-outline w-100 mt-3">Đăng đánh giá của bạn</button>
      </div>
    </section>

  </main>
</template>

<style scoped>
.product-detail-wrapper {
  padding: 30px 0;
  font-family: var(--font-inter, 'Inter', sans-serif);
  color: var(--text-main, #102a43);
  width: 100%;
}

/* Breadcrumb */
.breadcrumb {
  font-size: 0.9rem;
  margin-bottom: 24px;
  color: var(--text-muted, #627d98);
}
.breadcrumb a {
  color: var(--ocean-blue, #0288d1);
  text-decoration: none;
  font-weight: 500;
}
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb .separator { margin: 0 8px; color: #bcccdc; }
.breadcrumb .current-page { color: #102a43; font-weight: 600; }

/* Grid Layout */
.product-main-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  margin-bottom: 40px;
}

/* Thư viện ảnh */
.product-gallery {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.main-image-container {
  width: 100%;
  aspect-ratio: 4/5;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border-color, #d9e8f0);
  background: white;
}
.main-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumbnail-list {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 8px;
}
.thumbnail-list::-webkit-scrollbar { height: 6px; }
.thumbnail-list::-webkit-scrollbar-thumb { background: #d9e8f0; border-radius: 4px; }
.thumbnail-item {
  width: 80px;
  height: 80px;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  border: 2px solid transparent;
  opacity: 0.6;
  transition: all 0.2s;
  flex-shrink: 0;
}
.thumbnail-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumbnail-item.active, .thumbnail-item:hover {
  opacity: 1;
  border-color: var(--ocean-blue, #0288d1);
}

/* Khối thông tin */
.category-badge {
  display: inline-block;
  background: rgba(2, 136, 209, 0.1);
  color: var(--ocean-blue, #0288d1);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 16px;
}
.product-title {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1.3;
  margin-bottom: 16px;
}
.product-rating {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
}
.stars i { color: #d9e8f0; }
.stars i.active { color: #ffb300; }
.rating-text { font-weight: 600; font-size: 0.95rem; }
.reviews-count { color: var(--text-muted, #627d98); font-weight: 400; }

/* Pricing */
.product-pricing {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border-color, #d9e8f0);
}
.current-price {
  font-size: 2rem;
  font-weight: 800;
  color: var(--coral, #ef5350);
}
.original-price {
  font-size: 1.25rem;
  color: #a0aec0;
  text-decoration: line-through;
}
.discount-badge {
  background: #ffebee;
  color: var(--coral, #ef5350);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 700;
}

/* Short Desc */
.short-description {
  font-size: 1.05rem;
  line-height: 1.6;
  color: #486581;
  margin-bottom: 30px;
}

/* Actions */
.purchase-actions {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
}
.quantity-selector {
  display: flex;
  align-items: center;
  border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 10px;
  overflow: hidden;
  background: white;
}
.quantity-selector button {
  width: 44px;
  height: 48px;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #102a43;
  transition: background 0.2s;
}
.quantity-selector button:hover { background: #f0f4f8; }
.quantity-selector input {
  width: 50px;
  text-align: center;
  border: none;
  font-weight: 700;
  font-size: 1rem;
  outline: none;
}
.btn-addToCart {
  flex: 1;
  padding: 0;
  height: 48px;
  border-radius: 10px;
  font-size: 1.05rem;
}
.btn-buyNow {
  width: 100%;
  height: 48px;
  background: var(--text-main, #102a43);
  margin-bottom: 30px;
}
.btn-buyNow:hover { background: #0b1d30; }

/* Perks */
.service-perks {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.perk-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.95rem;
  font-weight: 500;
  color: #334e68;
}
.perk-item i {
  color: var(--ocean-blue, #0288d1);
  font-size: 1.2rem;
  width: 24px;
  text-align: center;
}

/* Bottom Sections (Description & Reviews) */
.product-details-reviews {
  display: grid;
  grid-template-columns: 3fr 2fr;
  gap: 30px;
}
.content-box {
  background: white;
  border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 16px;
  padding: 30px;
  box-shadow: 0 4px 16px rgba(0,0,0, 0.02);
}
.section-title {
  font-size: 1.35rem;
  font-weight: 800;
  margin-bottom: 24px;
  padding-bottom: 12px;
  border-bottom: 2px solid #f0f4f8;
}

/* Long Description */
.long-description {
  position: relative;
  max-height: 250px;
  overflow: hidden;
  transition: max-height 0.4s ease;
  line-height: 1.7;
  color: #334e68;
}
.long-description.expanded {
  max-height: 2000px; /* Số lớn đủ để hiện full */
}
.long-description p { margin-bottom: 12px; }
.long-description ul { margin-left: 20px; margin-bottom: 16px; }
.long-description li { margin-bottom: 6px; }
.fade-overlay {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 100px;
  background: linear-gradient(rgba(255,255,255,0), rgba(255,255,255,1));
  pointer-events: none;
}
.toggle-desc-btn {
  margin-top: 16px;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}

/* Reviews */
.review-list {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.review-item {
  border-bottom: 1px dashed var(--border-color, #d9e8f0);
  padding-bottom: 20px;
}
.review-item:last-child { border-bottom: none; padding-bottom: 0; }
.review-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}
.reviewer-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  object-fit: cover;
}
.reviewer-info { flex: 1; }
.reviewer-name {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 4px;
}
.stars.small i { font-size: 0.8rem; }
.review-date {
  font-size: 0.85rem;
  color: #829ab1;
}
.review-content {
  font-size: 0.95rem;
  line-height: 1.5;
  color: #334e68;
}

.w-100 { width: 100%; }
.mt-3 { margin-top: 16px; }

/* Buttons & Utils */
.btn-primary {
  background: var(--ocean-blue, #0288d1);
  color: white;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.btn-primary:hover { background: #039be5; transform: translateY(-1px); }
.btn-outline {
  background: white;
  color: var(--ocean-blue, #0288d1);
  border: 1px solid var(--border-color, #d9e8f0);
  border-radius: 8px;
  padding: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-outline:hover {
  background: #f0f7fa;
  border-color: var(--ocean-blue, #0288d1);
}

.animate-fade-in { animation: fadeIn 0.4s ease; }
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Responsive */
@media (max-width: 900px) {
  .product-main-grid { grid-template-columns: 1fr; gap: 24px; }
  .product-details-reviews { grid-template-columns: 1fr; }
}
</style>
