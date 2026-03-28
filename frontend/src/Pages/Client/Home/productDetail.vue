<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
const route = useRoute();
const router = useRouter();
const slug = route.params.slug;
const product = ref(null);
const selectedVariant = ref(null);
const selectedColor = ref(null);
const selectedSize = ref(null);
const addingToCart = ref(false);
const toast = ref({ show: false, message: '', type: 'success' });

const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');
const getImageUrl = (path) => {
    if (!path || path === '0') return 'https://placehold.co/600x600?text=No+Image';
    if (path.startsWith('http')) return path;
    return `${BASE_URL}/storage/${path}`;
};

const allImages = computed(() => {
    if (!product.value) return [];
    const imgs = product.value.images || [];
    const variants = product.value.variants || [];
    const hasVariants = variants.length > 0;

    // Sản phẩm biến thể + đã chọn màu → hiện ảnh của variant màu đó
    if (hasVariants && selectedColor.value) {
        const colorVariants = variants.filter(v => v.color === selectedColor.value);
        const variantIds = colorVariants.map(v => v.variant_id);

        const variantImgs = imgs.filter(img => img.variant_id && variantIds.includes(img.variant_id));
        if (variantImgs.length > 0) return variantImgs;

        const directImgs = colorVariants
            .filter(v => v.image_url)
            .map(v => ({ image_url: v.image_url, variant_id: v.variant_id }));
        if (directImgs.length > 0) return directImgs;
    }

    // Sản phẩm biến thể + chưa chọn màu → chỉ hiện thumbnail
    if (hasVariants && !selectedColor.value) {
        if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
            return [{ image_url: product.value.thumbnail_url }];
        }
        return [{ image_url: null }];
    }

    // Sản phẩm đơn giản → hiện tất cả ảnh
    if (imgs.length > 0) return imgs;
    if (product.value.thumbnail_url && product.value.thumbnail_url !== '0') {
        return [{ image_url: product.value.thumbnail_url }];
    }
    return [{ image_url: null }];
});

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

// Lấy danh sách màu duy nhất
const uniqueColors = computed(() => {
    if (!product.value?.variants) return [];
    const colors = [...new Set(product.value.variants.map(v => v.color).filter(Boolean))];
    return colors;
});

// Lấy danh sách size khả dụng — luôn hiện tất cả size, đánh dấu disabled theo màu đã chọn
const availableSizes = computed(() => {
    if (!product.value?.variants) return [];
    const variants = product.value.variants;

    // Lấy tất cả sizes duy nhất
    const allSizes = [...new Set(variants.map(v => v.size).filter(Boolean))];

    return allSizes.map(size => {
        // Nếu đã chọn màu, kiểm tra variant (color + size) có tồn tại và khả dụng không
        if (selectedColor.value) {
            const match = variants.find(v => v.color === selectedColor.value && v.size === size);
            return {
                size,
                stock: match?.stock ?? 0,
                status: match?.status ?? 'inactive',
                variant_id: match?.variant_id ?? null,
                available: !!match && match.status === 'active' && match.stock > 0,
            };
        }
        // Chưa chọn màu → kiểm tra có BẤT KỲ variant nào có size này khả dụng không
        const anyAvailable = variants.some(v => v.size === size && v.status === 'active' && v.stock > 0);
        const first = variants.find(v => v.size === size);
        return {
            size,
            stock: first?.stock ?? 0,
            status: first?.status ?? 'inactive',
            variant_id: first?.variant_id ?? null,
            available: anyAvailable,
        };
    });
});

// Khi chọn màu → reset size + reset gallery, auto-select nếu chỉ có 1 size
watch(selectedColor, (newColor) => {
    selectedSize.value = null;
    selectedVariant.value = null;
    activeImageIndex.value = 0;
    if (newColor) {
        const sizes = product.value?.variants?.filter(v => v.color === newColor) || [];
        if (sizes.length === 1) {
            selectedSize.value = sizes[0].size;
            selectedVariant.value = sizes[0];
        }
    }
});

// Khi chọn size → tìm variant đúng
watch(selectedSize, (newSize) => {
    if (newSize && selectedColor.value && product.value?.variants) {
        const match = product.value.variants.find(
            v => v.color === selectedColor.value && v.size === newSize
        );
        selectedVariant.value = match || null;
    }
});
const mainImageUrl = computed(() => {
    const imgs = allImages.value;
    if (imgs.length === 0) return getImageUrl(null);
    const idx = activeImageIndex.value < imgs.length ? activeImageIndex.value : 0;
    return getImageUrl(imgs[idx]?.image_url);
});

const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const increaseQuantity = () => quantity.value++;
const decreaseQuantity = () => { if (quantity.value > 1) quantity.value-- };

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

const addToCart = async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        router.push({ name: 'login', query: { redirect: route.fullPath } });
        return;
    }

    if (!selectedVariant.value) {
        showToast('Vui lòng chọn phiên bản sản phẩm!', 'error');
        return;
    }

    if (quantity.value < 1) {
        showToast('Số lượng tối thiểu là 1!', 'error');
        return;
    }

    addingToCart.value = true;
    try {
        const response = await api.post('/cart/items', {
            variant_id: selectedVariant.value.variant_id,
            quantity: quantity.value,
        });
        if (response.data.status === 'success') {
            showToast(response.data.message, 'success');
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Không thể thêm vào giỏ hàng.';
        showToast(msg, 'error');
    } finally {
        addingToCart.value = false;
    }
};

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
          <img :src="mainImageUrl" :alt="product.name" class="main-image animate-fade-in" :key="activeImageIndex" />
        </div>
        
        <!-- Danh sách Ảnh nhỏ (Thumbnails) -->
        <div class="thumbnail-list" v-if="allImages.length > 1">
          <div 
            v-for="(img, index) in allImages" 
            :key="index"
            class="thumbnail-item"
            :class="{ 'active': activeImageIndex === index }"
            @click="activeImageIndex = index"
          >
            <img :src="getImageUrl(img.image_url)" :alt="`${product.name} - ảnh ${index + 1}`" />
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

        <!-- Chọn Màu sắc -->
        <div class="variant-selector" v-if="uniqueColors.length > 0">
          <h4 class="variant-label">Màu sắc:</h4>
          <div class="variant-options">
            <button
              v-for="color in uniqueColors"
              :key="color"
              class="variant-btn"
              :class="{ active: selectedColor === color }"
              @click="selectedColor = selectedColor === color ? null : color"
            >
              <span>{{ color }}</span>
            </button>
          </div>
        </div>

        <!-- Chọn Kích cỡ -->
        <div class="variant-selector" v-if="availableSizes.length > 0">
          <h4 class="variant-label">Kích cỡ:</h4>
          <div class="variant-options">
            <button
              v-for="s in availableSizes"
              :key="s.size"
              class="variant-btn"
              :class="{ active: selectedSize === s.size, disabled: !s.available }"
              @click="s.available && (selectedSize = selectedSize === s.size ? null : s.size)"
              :disabled="!s.available"
            >
              <span>{{ s.size || 'Mặc định' }}</span>
              <span class="variant-stock" v-if="selectedColor && s.stock <= 5 && s.stock > 0">(còn {{ s.stock }})</span>
              <span class="variant-stock out" v-if="selectedColor && s.stock <= 0">Hết hàng</span>
            </button>
          </div>
        </div>

        <!-- Chức năng Số lượng & Mua hàng -->
        <div class="purchase-actions">
          <div class="quantity-selector">
            <button @click="decreaseQuantity"><i class="fas fa-minus"></i></button>
            <input type="number" v-model="quantity" readonly />
            <button @click="increaseQuantity"><i class="fas fa-plus"></i></button>
          </div>
          <button class="btn-primary btn-addToCart" @click="addToCart" :disabled="addingToCart">
            <i class="fas fa-cart-plus" v-if="!addingToCart"></i>
            <span v-if="addingToCart">Đang thêm...</span>
            <span v-else>Thêm vào giỏ</span>
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

  <!-- Toast -->
  <Transition name="toast">
    <div v-if="toast.show" class="toast-notification" :class="toast.type">
      <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
      <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <span>{{ toast.message }}</span>
    </div>
  </Transition>
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
  grid-template-columns: 5fr 7fr;
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
  aspect-ratio: 1/1;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border-color, #d9e8f0);
  background: white;
}
.main-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
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

/* Variant Selector */
.variant-selector { margin-bottom: 24px; }
.variant-label {
  font-size: 0.95rem;
  font-weight: 700;
  color: #334e68;
  margin-bottom: 10px;
}
.variant-options {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.variant-btn {
  padding: 8px 16px;
  border: 1.5px solid #d9e2ec;
  border-radius: 8px;
  background: #fff;
  font-size: 0.88rem;
  font-weight: 600;
  color: #334e68;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  display: flex;
  align-items: center;
  gap: 6px;
}
.variant-btn:hover:not(:disabled) {
  border-color: #0288d1;
  color: #0288d1;
}
.variant-btn.active {
  border-color: #0288d1;
  background: rgba(2, 136, 209, 0.08);
  color: #0288d1;
}
.variant-btn.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.variant-stock {
  font-size: 0.75rem;
  color: #f59e0b;
  font-weight: 500;
}
.variant-stock.out { color: #dc2626; }

.btn-primary:disabled,
.btn-addToCart:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Toast */
.toast-notification {
  position: fixed;
  top: 90px;
  right: 24px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 22px;
  border-radius: 10px;
  font-size: 0.92rem;
  font-weight: 600;
  z-index: 999;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.toast-notification.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}
.toast-notification.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.toast-enter-active { animation: slideInRight 0.3s ease; }
.toast-leave-active { animation: slideOutRight 0.3s ease; }
@keyframes slideInRight {
  from { opacity: 0; transform: translateX(40px); }
  to { opacity: 1; transform: translateX(0); }
}
@keyframes slideOutRight {
  from { opacity: 1; transform: translateX(0); }
  to { opacity: 0; transform: translateX(40px); }
}
</style>
