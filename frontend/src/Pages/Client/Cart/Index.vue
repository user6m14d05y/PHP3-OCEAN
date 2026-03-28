<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';

const router = useRouter();
const cartItems = ref([]);
const cartId = ref(null);
const loading = ref(true);
const updating = ref({});
const selectAll = ref(true);
const toast = ref({ show: false, message: '', type: 'success' });

// Lấy giỏ hàng
const fetchCart = async () => {
    loading.value = true;
    try {
        const response = await api.get('/cart');
        if (response.data.status === 'success') {
            cartId.value = response.data.data.cart_id;
            cartItems.value = response.data.data.items || [];
            updateSelectAllState();
        }
    } catch (error) {
        console.error('Error fetching cart:', error);
        if (error.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/cart' } });
        }
    } finally {
        loading.value = false;
    }
};

// Cập nhật trạng thái "Chọn tất cả"
const updateSelectAllState = () => {
    if (cartItems.value.length === 0) {
        selectAll.value = false;
        return;
    }
    selectAll.value = cartItems.value.every(item => item.selected);
};

// Toggle chọn tất cả
const toggleSelectAll = async () => {
    const newState = !selectAll.value;
    selectAll.value = newState;
    
    const promises = cartItems.value.map(item => {
        item.selected = newState;
        return api.put(`/cart/items/${item.cart_item_id}`, { selected: newState }).catch(() => {});
    });
    await Promise.all(promises);
};

// Toggle chọn 1 item
const toggleSelect = async (item) => {
    item.selected = !item.selected;
    updateSelectAllState();
    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { selected: item.selected });
    } catch (error) {
        item.selected = !item.selected;
        showToast('Không thể cập nhật. Vui lòng thử lại.', 'error');
    }
};

// Cập nhật số lượng
const updateQuantity = async (item, newQuantity) => {
    if (newQuantity < 1) return;
    if (!item.variant || newQuantity > item.variant.stock) {
        showToast(`Chỉ còn ${item.variant?.stock || 0} sản phẩm trong kho.`, 'error');
        return;
    }

    const oldQuantity = item.quantity;
    item.quantity = newQuantity;
    item.line_total = item.variant.price * newQuantity;
    updating.value[item.cart_item_id] = true;

    try {
        await api.put(`/cart/items/${item.cart_item_id}`, { quantity: newQuantity });
    } catch (error) {
        item.quantity = oldQuantity;
        item.line_total = item.variant.price * oldQuantity;
        const msg = error.response?.data?.message || 'Không thể cập nhật số lượng.';
        showToast(msg, 'error');
    } finally {
        updating.value[item.cart_item_id] = false;
    }
};

// Xóa 1 item
const removeItem = async (item) => {
    if (!confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) return;

    try {
        await api.delete(`/cart/items/${item.cart_item_id}`);
        cartItems.value = cartItems.value.filter(i => i.cart_item_id !== item.cart_item_id);
        showToast('Đã xóa sản phẩm khỏi giỏ hàng!', 'success');
        updateSelectAllState();
    } catch (error) {
        showToast('Không thể xóa sản phẩm. Vui lòng thử lại.', 'error');
    }
};

// Xóa toàn bộ
const clearCart = async () => {
    if (!confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?')) return;

    try {
        await api.delete('/cart');
        cartItems.value = [];
        showToast('Đã xóa toàn bộ giỏ hàng!', 'success');
    } catch (error) {
        showToast('Không thể xóa giỏ hàng. Vui lòng thử lại.', 'error');
    }
};

// Tính tổng
const selectedItems = computed(() => cartItems.value.filter(i => i.selected));
const totalSelectedQuantity = computed(() => selectedItems.value.reduce((sum, i) => sum + i.quantity, 0));
const totalPrice = computed(() => selectedItems.value.reduce((sum, i) => sum + (i.variant?.price || 0) * i.quantity, 0));

// Format tiền VND
const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

// Lấy ảnh sản phẩm
const getProductImage = (item) => {
    if (item.variant?.image_url) return `http://localhost:8383/storage/${item.variant.image_url}`;
    if (item.product?.main_image) return `http://localhost:8383/storage/${item.product.main_image}`;
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `http://localhost:8383/storage/${item.product.thumbnail_url}`;
    return 'https://placehold.co/120x120?text=No+Image';
};

// Toast notification
const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

// Chuyển tới trang thanh toán
const proceedToCheckout = () => {
    if (selectedItems.value.length === 0) return;
    router.push('/checkout');
};

onMounted(() => {
    fetchCart();
});
</script>

<template>
    <div class="cart-page">
        <!-- Toast -->
        <Transition name="toast">
            <div v-if="toast.show" class="toast-notification" :class="toast.type">
                <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>{{ toast.message }}</span>
            </div>
        </Transition>

        <!-- Page Header -->
        <div class="page-header animate-in">
            <h1>Giỏ Hàng Của Bạn</h1>
            <p v-if="cartItems.length > 0">Bạn có <strong>{{ cartItems.length }}</strong> sản phẩm trong giỏ hàng</p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang tải giỏ hàng...</p>
        </div>

        <!-- Empty Cart -->
        <div v-else-if="cartItems.length === 0" class="empty-cart animate-in">
            <div class="empty-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#b0c4de" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>
                </svg>
            </div>
            <h2>Giỏ hàng trống</h2>
            <p>Hãy khám phá và thêm sản phẩm yêu thích vào giỏ hàng nhé!</p>
            <router-link to="/product" class="btn-primary btn-shop">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                Tiếp tục mua sắm
            </router-link>
        </div>

        <!-- Cart Content -->
        <div v-else class="cart-layout animate-in" style="animation-delay: 0.1s">
            <!-- Cột trái: Danh sách sản phẩm -->
            <div class="cart-items-section">
                <!-- Action Bar -->
                <div class="cart-action-bar">
                    <label class="checkbox-wrapper" @click.prevent="toggleSelectAll">
                        <div class="custom-checkbox" :class="{ checked: selectAll }">
                            <svg v-if="selectAll" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <span>Chọn tất cả ({{ cartItems.length }})</span>
                    </label>
                    <button class="btn-clear" @click="clearCart" v-if="cartItems.length > 0">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                        Xóa tất cả
                    </button>
                </div>

                <!-- Cart Items List -->
                <TransitionGroup name="cart-item" tag="div" class="items-list">
                    <div v-for="item in cartItems" :key="item.cart_item_id" class="cart-item-card" :class="{ 'item-unavailable': item.variant?.status !== 'active' }">
                        <!-- Checkbox -->
                        <div class="item-checkbox" @click="toggleSelect(item)">
                            <div class="custom-checkbox" :class="{ checked: item.selected }">
                                <svg v-if="item.selected" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="4"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>

                        <!-- Product Image -->
                        <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-image-link">
                            <img :src="getProductImage(item)" :alt="item.product?.name" class="item-image" />
                        </router-link>

                        <!-- Product Info -->
                        <div class="item-details">
                            <router-link :to="item.product ? '/product/' + item.product.slug : '#'" class="item-name">
                                {{ item.product?.name || 'Sản phẩm' }}
                            </router-link>
                            <div class="item-variant" v-if="item.variant">
                                <span v-if="item.variant.color">{{ item.variant.color }}</span>
                                <span v-if="item.variant.color && item.variant.size"> / </span>
                                <span v-if="item.variant.size">{{ item.variant.size }}</span>
                                <span v-if="!item.variant.color && !item.variant.size && item.variant.variant_name">{{ item.variant.variant_name }}</span>
                            </div>
                            <div class="item-stock" v-if="item.variant?.stock <= 5 && item.variant?.stock > 0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                Chỉ còn {{ item.variant.stock }} sản phẩm
                            </div>
                            <div class="item-unavailable-badge" v-if="item.variant?.status !== 'active'">
                                Sản phẩm ngừng kinh doanh
                            </div>
                        </div>

                        <!-- Price & Quantity -->
                        <div class="item-price-qty">
                            <div class="item-price">{{ formatPrice(item.variant?.price) }}</div>
                            <div class="quantity-control">
                                <button class="qty-btn" @click="updateQuantity(item, item.quantity - 1)" :disabled="item.quantity <= 1 || updating[item.cart_item_id]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                                <span class="qty-display" :class="{ 'qty-updating': updating[item.cart_item_id] }">{{ item.quantity }}</span>
                                <button class="qty-btn" @click="updateQuantity(item, item.quantity + 1)" :disabled="item.quantity >= (item.variant?.stock || 0) || updating[item.cart_item_id]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                            </div>
                            <div class="item-total">{{ formatPrice((item.variant?.price || 0) * item.quantity) }}</div>
                        </div>

                        <!-- Remove Button -->
                        <button class="btn-remove" @click="removeItem(item)" title="Xóa">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </TransitionGroup>
            </div>

            <!-- Cột phải: Tóm tắt đơn hàng -->
            <div class="order-summary animate-in" style="animation-delay: 0.2s">
                <div class="summary-card">
                    <h3 class="summary-title">Tóm Tắt Đơn Hàng</h3>
                    
                    <div class="summary-row">
                        <span>Số lượng sản phẩm đã chọn</span>
                        <strong>{{ totalSelectedQuantity }}</strong>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="summary-row summary-total">
                        <span>Tạm tính</span>
                        <strong class="total-price">{{ formatPrice(totalPrice) }}</strong>
                    </div>

                    <p class="summary-note">Phí vận chuyển sẽ được tính ở bước thanh toán</p>

                    <button class="btn-checkout" @click="proceedToCheckout" :disabled="selectedItems.length === 0">
                        Tiến Hành Thanh Toán ({{ totalSelectedQuantity }})
                    </button>

                    <router-link to="/product" class="btn-continue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Tiếp tục mua sắm
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cart-page {
    padding: 24px 0 60px;
    font-family: var(--font-inter, 'Inter', sans-serif);
    color: #102a43;
    min-height: 60vh;
}

/* Page Header */
.page-header {
    text-align: center;
    margin-bottom: 32px;
}
.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    color: #0288d1;
    margin-bottom: 8px;
}
.page-header p {
    font-size: 1rem;
    color: #627d98;
}

/* Loading */
.loading-state {
    text-align: center;
    padding: 80px 20px;
    color: #627d98;
}
.spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e8ecf1;
    border-top-color: #0288d1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: #fff;
    border-radius: 16px;
    border: 1px dashed #b0c4de;
}
.empty-icon { margin-bottom: 24px; }
.empty-cart h2 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #334e68;
    margin-bottom: 8px;
}
.empty-cart p {
    color: #627d98;
    margin-bottom: 28px;
    font-size: 1rem;
}
.btn-shop {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 10px;
    font-size: 1rem;
    text-decoration: none;
}

/* Layout */
.cart-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
.cart-items-section {
    flex: 1;
}

/* Action Bar */
.cart-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8ecf1;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.92rem;
    font-weight: 600;
    color: #334e68;
    user-select: none;
}
.custom-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid #c8d6e0;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}
.custom-checkbox.checked {
    background: #0288d1;
    border-color: #0288d1;
}
.btn-clear {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff5f5;
    color: #dc2626;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.btn-clear:hover {
    background: #fee2e2;
    border-color: #f87171;
}

/* Cart Items */
.items-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.cart-item-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e8ecf1;
    transition: all 0.25s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.cart-item-card:hover {
    border-color: rgba(2, 136, 209, 0.25);
    box-shadow: 0 4px 16px rgba(2, 136, 209, 0.06);
}
.cart-item-card.item-unavailable {
    opacity: 0.55;
    background: #fafafa;
}

/* Checkbox */
.item-checkbox {
    cursor: pointer;
    flex-shrink: 0;
}

/* Product Image */
.item-image-link {
    flex-shrink: 0;
}
.item-image {
    width: 90px;
    height: 90px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #eef2f6;
    transition: transform 0.3s;
}
.item-image:hover {
    transform: scale(1.05);
}

/* Product Details */
.item-details {
    flex: 1;
    min-width: 0;
}
.item-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a2b4a;
    text-decoration: none;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.4;
    transition: color 0.2s;
}
.item-name:hover { color: #0288d1; }
.item-variant {
    font-size: 0.82rem;
    color: #627d98;
    margin-top: 4px;
    font-weight: 500;
}
.item-stock {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.78rem;
    color: #f59e0b;
    font-weight: 600;
    margin-top: 6px;
}
.item-unavailable-badge {
    font-size: 0.78rem;
    color: #dc2626;
    font-weight: 600;
    margin-top: 6px;
}

/* Price & Quantity */
.item-price-qty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 140px;
}
.item-price {
    font-size: 0.92rem;
    font-weight: 700;
    color: #ef5350;
}
.quantity-control {
    display: flex;
    align-items: center;
    border: 1px solid #d9e2ec;
    border-radius: 8px;
    overflow: hidden;
    background: #fff;
}
.qty-btn {
    width: 34px;
    height: 34px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #486581;
    transition: all 0.15s;
}
.qty-btn:hover:not(:disabled) {
    background: #f0f7fa;
    color: #0288d1;
}
.qty-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.qty-display {
    width: 38px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 700;
    color: #102a43;
    border-left: 1px solid #e8ecf1;
    border-right: 1px solid #e8ecf1;
    line-height: 34px;
}
.qty-updating {
    color: #a0aec0;
}
.item-total {
    font-size: 0.85rem;
    font-weight: 800;
    color: #1a2b4a;
}

/* Remove Button */
.btn-remove {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a0aec0;
    border-radius: 8px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.btn-remove:hover {
    background: #fff5f5;
    color: #dc2626;
}

/* Order Summary */
.order-summary {
    width: 340px;
    flex-shrink: 0;
    position: sticky;
    top: 100px;
}
.summary-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e8ecf1;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}
.summary-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1a2b4a;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 2px solid #f0f4f8;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    font-size: 0.92rem;
    color: #486581;
}
.summary-row strong { color: #102a43; }
.summary-divider {
    height: 1px;
    background: #eef2f6;
    margin: 8px 0;
}
.summary-total {
    font-size: 1.05rem;
    padding: 14px 0;
}
.total-price {
    font-size: 1.3rem;
    font-weight: 800;
    color: #ef5350 !important;
}
.summary-note {
    font-size: 0.8rem;
    color: #829ab1;
    text-align: center;
    margin: 12px 0 20px;
    font-style: italic;
}
.btn-checkout {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #0288d1, #039be5);
    color: #fff;
    font-size: 1.05rem;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.25s;
    box-shadow: 0 4px 14px rgba(2, 136, 209, 0.3);
}
.btn-checkout:hover:not(:disabled) {
    background: linear-gradient(135deg, #0277bd, #0288d1);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(2, 136, 209, 0.4);
}
.btn-checkout:disabled {
    background: #c8d6e0;
    cursor: not-allowed;
    box-shadow: none;
}
.btn-continue {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 11px;
    margin-top: 10px;
    color: #0288d1;
    border: 1px solid #c8d6e0;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.92rem;
    transition: all 0.2s;
}
.btn-continue:hover {
    background: #f0f7fa;
    border-color: #0288d1;
}

/* BTN Primary */
.btn-primary {
    background: #0288d1;
    color: #fff;
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    border-radius: 10px;
}
.btn-primary:hover {
    background: #039be5;
    transform: translateY(-1px);
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

/* Transitions */
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

.cart-item-enter-active { animation: fadeSlideIn 0.3s ease; }
.cart-item-leave-active { animation: fadeSlideOut 0.25s ease; }
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}
@keyframes fadeSlideOut {
    from { opacity: 1; transform: translateX(0); height: auto; }
    to { opacity: 0; transform: translateX(20px); height: 0; padding: 0; margin: 0; overflow: hidden; }
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-in {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive */
@media (max-width: 900px) {
    .cart-layout {
        flex-direction: column;
    }
    .order-summary {
        width: 100%;
        position: static;
    }
    .cart-item-card {
        flex-wrap: wrap;
        gap: 12px;
    }
    .item-price-qty {
        flex-direction: row;
        min-width: auto;
        gap: 12px;
        width: 100%;
        justify-content: space-between;
        padding-left: 36px;
    }
}

@media (max-width: 600px) {
    .item-image {
        width: 70px;
        height: 70px;
    }
    .cart-item-card {
        padding: 12px 14px;
    }
}
</style>
