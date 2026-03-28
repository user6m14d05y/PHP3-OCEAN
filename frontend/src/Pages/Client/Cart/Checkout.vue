<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/axios';
import AddressSelector from '@/components/AddressSelector.vue';

const router = useRouter();
const cartItems = ref([]);
const loading = ref(true);

// --- Địa chỉ ---
const addresses = ref([]);
const selectedAddressId = ref(null);
const showAddAddressForm = ref(false);
const submittingAddress = ref(false);
const formError = ref('');
const addressSelectorKey = ref(0);
const formAddress = ref({
    recipient_name: '',
    phone: '',
    address_line: '',
    ward: '', district: '', province: '',
    ward_code: '', district_code: '', province_code: '',
    address_type: 'home',
    is_default: false,
});

// --- Thanh toán & Khác ---
const paymentMethod = ref('cod'); // cod, vnpay, banking
const note = ref('');
const toast = ref({ show: false, message: '', type: 'success' });

// --- Coupon ---
const couponCode = ref('');
const appliedCoupon = ref(null);
const checkingCoupon = ref(false);

// Format tiền VND
const formatPrice = (price) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

// Lấy giỏ hàng
const fetchCart = async () => {
    try {
        const response = await api.get('/cart');
        if (response.data.status === 'success') {
            cartItems.value = (response.data.data.items || []).filter(i => i.selected);
            if (cartItems.value.length === 0) {
                router.push('/cart');
            }
        }
    } catch (error) {
        if (error.response?.status === 401) {
            router.push({ name: 'login', query: { redirect: '/checkout' } });
        }
    }
};

// Lấy danh sách địa chỉ
const fetchAddresses = async () => {
    try {
        const res = await api.get('/profile/addresses');
        addresses.value = res.data?.data || [];
        // Tự động chọn địa chỉ mặc định hoặc cái đầu tiên
        const defaultAddr = addresses.value.find(a => a.is_default);
        if (defaultAddr) selectedAddressId.value = defaultAddr.address_id;
        else if (addresses.value.length > 0) selectedAddressId.value = addresses.value[0].address_id;
    } catch (e) {
        console.error('Lỗi tải địa chỉ:', e);
    }
};

// Format full address để render UI
const formatFullAddress = (addr) => {
    const parts = [];
    if (addr.address_line) parts.push(addr.address_line);
    if (addr.ward) parts.push(addr.ward);
    if (addr.district) parts.push(addr.district);
    if (addr.province) parts.push(addr.province);
    return parts.join(', ') || 'Chưa có thông tin địa chỉ cụ thể';
};

// Xử lý tạo địa chỉ mới
const onAddressChange = (data) => {
    formAddress.value.province = data.province_name;
    formAddress.value.province_code = data.province_code;
    formAddress.value.district = data.district_name;
    formAddress.value.district_code = data.district_code;
    formAddress.value.ward = data.ward_name;
    formAddress.value.ward_code = data.ward_code;
    formAddress.value.address_line = data.address_detail;
};

const openAddAddressForm = () => {
    formError.value = '';
    formAddress.value = {
        recipient_name: '', phone: '', address_line: '',
        ward: '', district: '', province: '',
        ward_code: '', district_code: '', province_code: '',
        address_type: 'home', is_default: false,
    };
    addressSelectorKey.value++;
    showAddAddressForm.value = true;
};

const submitNewAddress = async () => {
    if (!formAddress.value.recipient_name.trim()) return formError.value = 'Vui lòng nhập họ tên người nhận';
    if (!formAddress.value.phone.trim()) return formError.value = 'Vui lòng nhập số điện thoại';
    if (!formAddress.value.province) return formError.value = 'Vui lòng chọn Tỉnh/Thành phố';
    if (!formAddress.value.district) return formError.value = 'Vui lòng chọn Quận/Huyện';

    submittingAddress.value = true;
    formError.value = '';

    try {
        const res = await api.post('/profile/addresses', formAddress.value);
        showAddAddressForm.value = false;
        await fetchAddresses();
        if (res.data?.data?.address_id) {
            selectedAddressId.value = res.data.data.address_id;
        }
        showToast('Đã lưu sổ địa chỉ an toàn', 'success');
    } catch (e) {
        formError.value = e.response?.data?.message || 'Đã xảy ra lỗi. Vui lòng thử lại.';
    } finally {
        submittingAddress.value = false;
    }
};

// --- Xử lý tính toán hóa đơn ---
const totalQuantity = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + item.quantity, 0);
});

const subtotal = computed(() => {
    return cartItems.value.reduce((sum, item) => sum + (item.variant?.price || 0) * item.quantity, 0);
});

const shippingFee = computed(() => {
    // Miễn phí vận chuyển nếu đơn trên 500k, ngược lại 30k (Mockup)
    return subtotal.value > 500000 ? 0 : 30000;
});

const discount = computed(() => {
    if (!appliedCoupon.value) return 0;
    let disc = 0;
    if (appliedCoupon.value.discount_type === 'percentage') {
        disc = (subtotal.value * appliedCoupon.value.discount_amount) / 100;
    } else {
        disc = appliedCoupon.value.discount_amount;
    }
    return Math.min(disc, subtotal.value);
});

const total = computed(() => {
    return subtotal.value + shippingFee.value - discount.value;
});

// Appy coupon (Mã cứng mockup cho UI: OCEAN10)
const applyCoupon = () => {
    if (!couponCode.value.trim()) return;
    checkingCoupon.value = true;
    setTimeout(() => {
        checkingCoupon.value = false;
        if (couponCode.value.toUpperCase() === 'OCEAN10') {
            appliedCoupon.value = { code: 'OCEAN10', discount_type: 'percentage', discount_amount: 10 };
            showToast('Đã áp dụng mã giảm giá 10% (OCEAN10)!', 'success');
        } else {
            showToast('Mã giảm giá không hợp lệ hoặc đã hết hạn', 'error');
            appliedCoupon.value = null;
        }
    }, 800);
};

const removingCoupon = () => {
    appliedCoupon.value = null;
    couponCode.value = '';
}

// Đặt hàng
const placingOrder = ref(false);
const placeOrder = () => {
    if (!selectedAddressId.value) {
        return showToast('Vui lòng chọn hoặc thêm địa chỉ giao nhận hàng', 'error');
    }
    
    const payload = {
        address_id: selectedAddressId.value,
        payment_method: paymentMethod.value,
        note: note.value,
        coupon_applied: appliedCoupon.value?.code || null,
        items: cartItems.value.map(i => i.cart_item_id),
        subtotal: subtotal.value,
        shippingFee: shippingFee.value,
        discount: discount.value,
        total: total.value
    };

    console.log('[DUMMY POST] Khởi tạo đơn hàng với payload:', payload);

    placingOrder.value = true;
    setTimeout(() => {
        placingOrder.value = false;
        showToast('Đặt hàng thành công! Đơn hàng đã được ghi nhận', 'success');
        setTimeout(() => {
            router.push('/');
        }, 1500);
    }, 1200);
};

// Các hàm tiện ích
const getProductImage = (item) => {
    if (item.variant?.image_url) return `http://localhost:8383/storage/${item.variant.image_url}`;
    if (item.product?.main_image) return `http://localhost:8383/storage/${item.product.main_image}`;
    if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `http://localhost:8383/storage/${item.product.thumbnail_url}`;
    return 'https://placehold.co/120x120?text=No+Image';
};

const showToast = (message, type = 'success') => {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3000);
};

onMounted(async () => {
    await Promise.all([fetchCart(), fetchAddresses()]);
    loading.value = false;
});
</script>

<template>
    <div class="checkout-page">
        <!-- Toast Notification -->
        <Transition name="toast">
            <div v-if="toast.show" class="toast-notification" :class="toast.type">
                <svg v-if="toast.type === 'success'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <span>{{ toast.message }}</span>
            </div>
        </Transition>

        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <p>Đang chuẩn bị trang thanh toán...</p>
        </div>

        <div v-else class="checkout-wrapper">
            <div class="page-header animate-in">
                <h1>Thanh toán đơn hàng</h1>
                <nav class="breadcrumb">
                    <router-link to="/cart">Giỏ hàng</router-link>
                    <span class="separator">/</span>
                    <span class="current">Thanh toán</span>
                </nav>
            </div>

            <div class="checkout-layout animate-in" style="animation-delay: 0.1s">
                <!-- ================= BÊN TRÁI: THÔNG TIN GIAO HÀNG & THANH TOÁN ================= -->
                <div class="checkout-main">
                    
                    <!-- 1. Địa chỉ giao hàng -->
                    <section class="checkout-section">
                        <div class="section-header">
                            <h2><span class="step-num">1</span> BƯỚC 1: Sổ địa chỉ giao hàng</h2>
                            <button class="btn-text" @click="openAddAddressForm">+ Thêm địa chỉ mới</button>
                        </div>
                        <div class="section-body">
                            <div v-if="addresses.length > 0" class="address-grid">
                                <label 
                                    v-for="addr in addresses" 
                                    :key="addr.address_id" 
                                    class="address-card" 
                                    :class="{ 'is-selected': selectedAddressId === addr.address_id }"
                                >
                                    <input type="radio" v-model="selectedAddressId" :value="addr.address_id" class="hidden-radio" />
                                    <div class="addr-card-content">
                                        <div class="addr-header">
                                            <span class="addr-name">{{ addr.recipient_name }}</span>
                                            <span class="addr-phone">{{ addr.phone }}</span>
                                            <span v-if="addr.is_default" class="badge-default">Mặc định</span>
                                        </div>
                                        <div class="addr-body">{{ formatFullAddress(addr) }}</div>
                                    </div>
                                    <div class="check-indicator">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="check-icon" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </label>
                            </div>
                            <div v-else class="empty-address-box">
                                <p>Bạn chưa có địa chỉ giao hàng nào.</p>
                                <button class="btn-outline" @click="openAddAddressForm">Tạo sổ địa chỉ ngay</button>
                            </div>
                        </div>
                    </section>
                    
                    <!-- 2. Ghi chú cá nhân -->
                    <section class="checkout-section">
                        <div class="section-header">
                            <h2><span class="step-num">2</span> BƯỚC 2: Thông tin tùy thuộc</h2>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label for="order-note" class="form-label">Ghi chú cho đơn vị vận chuyển (Không bắt buộc)</label>
                                <textarea id="order-note" v-model="note" class="form-input" rows="3" placeholder="Ví dụ: Giao hàng vào giờ hành chính, gọi trước khi giao..."></textarea>
                            </div>
                        </div>
                    </section>

                    <!-- 3. Phương thức thanh toán -->
                    <section class="checkout-section">
                        <div class="section-header">
                            <h2><span class="step-num">3</span> BƯỚC 3: Phương thức thanh toán</h2>
                        </div>
                        <div class="section-body">
                            <div class="payment-methods">
                                <label class="payment-card" :class="{ 'is-selected': paymentMethod === 'cod' }">
                                    <input type="radio" v-model="paymentMethod" value="cod" class="hidden-radio" />
                                    <div class="payment-icon">
                                        <img src="https://ocean.store/icons/cod.svg" onerror="this.src='https://placehold.co/40x40?text=COD'" alt="COD" />
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Thanh toán khi nhận hàng (COD)</span>
                                        <span class="payment-desc">Thanh toán bằng tiền mặt khi giao hàng tận nơi</span>
                                    </div>
                                    <div class="check-indicator">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="check-icon" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </label>

                                <label class="payment-card" :class="{ 'is-selected': paymentMethod === 'vnpay' }">
                                    <input type="radio" v-model="paymentMethod" value="vnpay" class="hidden-radio" />
                                    <div class="payment-icon">
                                        <img src="https://ocean.store/icons/vnpay.svg" onerror="this.src='https://placehold.co/40x40?text=VNP'" alt="VNPay" />
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Thanh toán qua cổng VNPAY</span>
                                        <span class="payment-desc">Hỗ trợ ATM nội địa, QR Code và Thẻ quốc tế Visa/MasterCard</span>
                                    </div>
                                    <div class="check-indicator">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="check-icon" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </label>

                                <label class="payment-card" :class="{ 'is-selected': paymentMethod === 'momo' }">
                                    <input type="radio" v-model="paymentMethod" value="momo" class="hidden-radio" />
                                    <div class="payment-icon">
                                        <img src="https://ocean.store/icons/momo.svg" onerror="this.src='https://placehold.co/40x40?text=Momo'" alt="Momo" />
                                    </div>
                                    <div class="payment-info">
                                        <span class="payment-name">Thanh toán qua Ví điện tử MoMo</span>
                                        <span class="payment-desc">Quét QR MoMo an toàn, liền mạch và nhiều ưu đãi hời</span>
                                    </div>
                                    <div class="check-indicator">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="check-icon" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ================= BÊN PHẢI: BILL SUMMARY VÀ COUPON ================= -->
                <div class="checkout-sidebar animate-in" style="animation-delay: 0.2s">
                    <div class="sticky-sidebar">
                        <div class="bill-summary-card">
                            <h3 class="bill-title">Đơn hàng của bạn ({{ totalQuantity }} sản phẩm)</h3>
                            
                            <!-- Danh sách list item -->
                            <div class="bill-items">
                                <div v-for="item in cartItems" :key="item.cart_item_id" class="bill-item">
                                    <div class="bill-item-img-wrapper">
                                        <img :src="getProductImage(item)" :alt="item.product?.name" class="bill-item-img" />
                                        <span class="bill-item-badge">{{ item.quantity }}</span>
                                    </div>
                                    <div class="bill-item-info">
                                        <h4 class="bill-item-name">{{ item.product?.name }}</h4>
                                        <p class="bill-item-variant">
                                            {{ item.variant?.color || '' }} {{ item.variant?.color && item.variant?.size ? '/' : '' }} {{ item.variant?.size || '' }}
                                        </p>
                                    </div>
                                    <div class="bill-item-price">{{ formatPrice((item.variant?.price || 0) * item.quantity) }}</div>
                                </div>
                            </div>

                            <div class="summary-divider"></div>

                            <!-- Nhập Coupon -->
                            <div class="coupon-section">
                                <label class="coupon-label">Mã giảm giá hay Voucher của bạn / Store</label>
                                <div class="coupon-input-group" v-if="!appliedCoupon">
                                    <input type="text" v-model="couponCode" placeholder="Nhập mã ưu đãi tại đây..." class="coupon-input" @keyup.enter="applyCoupon"/>
                                    <button class="btn-apply-coupon" @click="applyCoupon" :disabled="checkingCoupon || !couponCode">
                                        <span v-if="checkingCoupon" class="small-spinner"></span>
                                        <span v-else>Áp dụng mã</span>
                                    </button>
                                </div>
                                <div v-else class="coupon-applied-box">
                                    <div class="coupon-tag">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                        {{ appliedCoupon.code }}
                                    </div>
                                    <button class="btn-remove-coupon" @click="removingCoupon">Gỡ bỏ</button>
                                </div>
                                <p class="coupon-hint" v-if="!appliedCoupon">Tip tiết kiệm: Gõ thử mã <b>OCEAN10</b> (Giảm thẳng 10%)</p>
                            </div>

                            <div class="summary-divider"></div>

                            <!-- Tính tiền -->
                            <div class="totals-section">
                                <div class="total-row">
                                    <span>Tạm tính giỏ hàng</span>
                                    <span>{{ formatPrice(subtotal) }}</span>
                                </div>
                                <div class="total-row">
                                    <span>Cước phí vận chuyển toàn quốc</span>
                                    <span>{{ shippingFee === 0 ? 'Miễn phí FreeShip' : formatPrice(shippingFee) }}</span>
                                </div>
                                <div class="total-row discount-row" v-if="discount > 0">
                                    <span>Tiền chiết khấu Coupon</span>
                                    <span>- {{ formatPrice(discount) }}</span>
                                </div>
                                <div class="summary-divider"></div>
                                <div class="total-final-row">
                                    <span class="total-label">Thành tiền / Tổng cộng toán</span>
                                    <span class="total-price">{{ formatPrice(total) }}</span>
                                </div>
                            </div>

                            <button class="btn-place-order" @click="placeOrder" :disabled="placingOrder || !selectedAddressId">
                                <div v-if="placingOrder" class="spinner-small"></div>
                                <span v-else>🔒 Đặt đơn - Trả tiền ngay sau khi check-out</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL LỚN: THÊM ĐỊA CHỈ NHANH ================= -->
        <teleport to="body">
            <transition name="modal-fade">
                <div v-if="showAddAddressForm" class="modal-overlay" @click.self="showAddAddressForm = false">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title">Tạo sổ địa chỉ giao nhận mới</h2>
                            <button class="modal-close" @click="showAddAddressForm = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Tên người nhận đại diện ghi bill <span class="required">*</span></label>
                                    <input v-model="formAddress.recipient_name" type="text" class="form-input" placeholder="Ví dụ: Huỳnh Quang Minh" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Điện di động để liên lạc ngay <span class="required">*</span></label>
                                    <input v-model="formAddress.phone" type="text" class="form-input" placeholder="Ví dụ: 09012xxx9" />
                                </div>
                            </div>
                            <!-- Address Selector Components -->
                            <div class="form-group pb-2">
                                <label class="form-label">Thuộc vào Tỉnh hay Huyện nào đây? Xin hãy chọn chuẩn <span class="required">*</span></label>
                                <AddressSelector
                                    :key="addressSelectorKey"
                                    :initial-province="formAddress.province_code"
                                    :initial-district="formAddress.district_code"
                                    :initial-ward="formAddress.ward_code"
                                    :initial-detail="formAddress.address_line"
                                    @change="onAddressChange"
                                />
                            </div>
                            <div class="form-group form-group-checkbox mt-2">
                                <label class="checkbox-label">
                                    <input type="checkbox" v-model="formAddress.is_default" class="checkbox-input" />
                                    <span class="checkbox-custom"></span>Lưu thông tin này vô làm Sổ địa chỉ ưu tiên mặc định Profile cá nhân
                                </label>
                            </div>
                            <div v-if="formError" class="form-error-msg">{{ formError }}</div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-cancel" @click="showAddAddressForm = false">Bỏ qua / Hủy đi</button>
                            <button class="btn-save" @click="submitNewAddress" :disabled="submittingAddress">
                                {{ submittingAddress ? 'Processing Đang xử lý tạo sổ...' : 'Lưu Ngay' }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>

    </div>
</template>

<style scoped>
.checkout-page {
    padding: 20px 0 80px;
    font-family: var(--font-inter, 'Inter', sans-serif);
    color: #102a43;
    min-height: 80vh;
    background-color: #f8fafc;
}

/* Base states & Header */
.checkout-wrapper { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
.loading-state { text-align: center; padding: 100px 0; color: #627d98; }
.spinner { width: 44px; height: 44px; border: 4px solid #e2e8f0; border-top-color: #0288d1; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px; }
@keyframes spin { 100% { transform: rotate(360deg); } }

.page-header { margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0; }
.page-header h1 { font-size: 2rem; font-weight: 800; color: #0288d1; margin-bottom: 8px; }
.breadcrumb { font-size: 0.95rem; color: #64748b; font-weight: 500;}
.breadcrumb a { color: #0288d1; text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb .separator { margin: 0 12px; color: #cbd5e1; }
.breadcrumb .current { color: #334155; }

/* Grid Layout */
.checkout-layout {
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 32px;
}

/* Left Section */
.checkout-section {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.03);
    border: 1px solid #f1f5f9;
}
.checkout-section:last-child { margin-bottom: 0; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-header h2 { font-size: 1.15rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
.step-num { width: 26px; height: 26px; background: #0288d1; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; }
.btn-text { color: #0288d1; font-weight: 600; font-size: 0.95rem; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s; }
.btn-text:hover { background: #f0f9ff; }

/* Address Grid */
.address-grid { display: flex; flex-direction: column; gap: 12px; }
.address-card {
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    background: #fff;
}
.hidden-radio { position: absolute; opacity: 0; }
.address-card:hover { border-color: #bae6fd; background: #f8fafc; }
.address-card.is-selected {
    border-color: #0288d1;
    background: #f0f9ff;
}
.addr-card-content { flex: 1; }
.addr-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.addr-name { font-weight: 700; font-size: 1.05rem; }
.addr-phone { color: #64748b; font-size: 0.95rem; }
.badge-default { background: #0288d1; color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
.addr-body { font-size: 0.95rem; color: #475569; line-height: 1.5; }
.check-indicator { width: 24px; height: 24px; border-radius: 50%; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; }
.check-icon { display: none; stroke: white; }
.is-selected .check-indicator { background: #0288d1; border-color: #0288d1; }
.is-selected .check-icon { display: block; }
.empty-address-box { text-align: center; padding: 30px; border: 2px dashed #cbd5e1; border-radius: 12px; }
.empty-address-box p { color: #64748b; margin-bottom: 16px; }

/* Form inputs */
.form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; width: 100%; }
.form-label { font-size: 0.9rem; font-weight: 600; color: #334155; }
.form-input { padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
.form-input:focus { border-color: #0288d1; }
textarea.form-input { resize: vertical; min-height: 80px; }

/* Payment Methods */
.payment-methods { display: flex; flex-direction: column; gap: 12px; }
.payment-card { position: relative; display: flex; align-items: center; gap: 16px; padding: 16px 20px; border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s; background: #fff; }
.payment-card:hover { border-color: #bae6fd; background: #f8fafc; }
.payment-card.is-selected { border-color: #0288d1; background: #f0f9ff; }
.payment-icon { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; }
.payment-icon img { width: 100%; height: 100%; object-fit: contain; }
.payment-info { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.payment-name { font-weight: 700; font-size: 1rem; color: #1e293b; }
.payment-desc { font-size: 0.85rem; color: #64748b; }

/* Right Section - Sidebar Sticky */
.sticky-sidebar { position: sticky; top: 100px; }
.bill-summary-card { background: #fff; border-radius: 16px; padding: 24px; border: 1px solid #f1f5f9; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
.bill-title { font-size: 1.15rem; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
.summary-divider { height: 1px; background: #e2e8f0; margin: 24px 0; }

/* Bill items */
.bill-items { display: flex; flex-direction: column; gap: 16px; max-height: 380px; overflow-y: auto; padding-right: 8px; }
.bill-items::-webkit-scrollbar { width: 6px; }
.bill-items::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.bill-item { display: flex; align-items: center; gap: 14px; }
.bill-item-img-wrapper { position: relative; }
.bill-item-img { width: 66px; height: 66px; object-fit: cover; border-radius: 10px; border: 1px solid #e2e8f0; }
.bill-item-badge { position: absolute; top: -6px; right: -6px; background: #475569; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; width: 22px; height: 22px; font-weight: 700; border-radius: 50%; opacity: 0.9; }
.bill-item-info { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.bill-item-name { margin: 0; font-size: 0.95rem; font-weight: 600; color: #1e293b; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
.bill-item-variant { margin: 0; font-size: 0.8rem; color: #64748b; }
.bill-item-price { font-weight: 700; color: #0f172a; font-size: 0.95rem; }

/* Coupons */
.coupon-section { display: flex; flex-direction: column; gap: 10px; }
.coupon-label { font-size: 0.95rem; font-weight: 600; color: #334155; }
.coupon-input-group { display: flex; gap: 10px; }
.coupon-input { flex: 1; min-width: 0; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-family: inherit; font-size: 0.95rem; outline: none; transition: border-color 0.2s; text-transform: uppercase; }
.coupon-input:focus { border-color: #0ea5e9; }
.btn-apply-coupon { padding: 0 20px; font-weight: 600; font-family: inherit; background: #0f172a; color: white; border: none; border-radius: 10px; cursor: pointer; transition: background 0.2s; }
.btn-apply-coupon:hover:not(:disabled) { background: #1e293b; }
.btn-apply-coupon:disabled { opacity: 0.7; cursor: not-allowed; }
.coupon-applied-box { display: flex; align-items: center; justify-content: space-between; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 10px 14px; }
.coupon-tag { display: flex; align-items: center; gap: 8px; font-weight: 700; color: #166534; font-size: 1rem; }
.btn-remove-coupon { background: none; border: none; font-size: 0.85rem; color: #dc2626; font-weight: 600; cursor: pointer; padding: 4px; }
.btn-remove-coupon:hover { text-decoration: underline; }
.coupon-hint { margin: 0; font-size: 0.8rem; color: #94a3b8; }

/* Totals */
.totals-section { display: flex; flex-direction: column; gap: 12px; }
.total-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; color: #475569; }
.discount-row { color: #0288d1; font-weight: 600; }
.total-final-row { display: flex; justify-content: space-between; align-items: center; }
.total-label { font-size: 1.1rem; font-weight: 600; color: #1e293b; }
.total-price { font-size: 1.6rem; font-weight: 800; color: #ef5350; }

.btn-place-order { width: 100%; background: #0288d1; color: white; border: none; border-radius: 10px; padding: 16px; font-size: 1.1rem; font-weight: 800; cursor: pointer; transition: all 0.25s; box-shadow: 0 4px 14px rgba(2, 136, 209, 0.2); display: flex; align-items: center; justify-content: center; margin-top: 16px; font-family: inherit; }
.btn-place-order:hover:not(:disabled) { background: #039be5; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(2, 136, 209, 0.3); }
.btn-place-order:disabled { background: #cbd5e1; cursor: not-allowed; box-shadow: none; transform: none; }

/* MODAL THÊM ĐỊA CHỈ - COPIED/ADAPTED FROM PROFILE ADDRESS */
.modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); display: flex; justify-content: center; align-items: center; z-index: 1000; padding: 20px; }
.modal-content { background: white; border-radius: 16px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin: 0; }
.modal-close { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 10px; display: flex; justify-content: center; align-items: center; cursor: pointer; color: #64748b; transition: background 0.2s; }
.modal-close:hover { background: #e2e8f0; color: #0f172a; }
.modal-body { padding: 24px; flex: 1; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.required { color: #ef4444; }
.pb-2 { padding-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.95rem; color: #475569; font-weight: 500; }
.checkbox-input { display: none; }
.checkbox-custom { width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.checkbox-input:checked + .checkbox-custom { background: #0288d1; border-color: #0288d1; }
.checkbox-input:checked + .checkbox-custom::after { content: ''; width: 6px; height: 11px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); display: block; margin-bottom: 2px; }
.form-error-msg { background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-top: 16px; font-weight: 500; }
.modal-footer { padding: 20px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; background: #f8fafc; }
.btn-cancel { padding: 10px 20px; font-weight: 600; background: white; border: 1.5px solid #cbd5e1; color: #475569; border-radius: 10px; cursor: pointer; font-family: inherit; }
.btn-cancel:hover { background: #f1f5f9; }
.btn-save { padding: 10px 24px; font-weight: 600; background: #0288d1; border: none; color: white; border-radius: 10px; cursor: pointer; font-family: inherit; box-shadow: 0 4px 10px rgba(2, 136, 209, 0.2); }
.btn-save:hover:not(:disabled) { background: #039be5; }
.btn-save:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

.btn-outline { background: white; border: 1.5px solid #0288d1; color: #0288d1; padding: 10px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; }
.btn-outline:hover { background: #f0f9ff; }

/* Utils */
.small-spinner { display: inline-block; width: 16px; height: 16px; border: 2.5px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease infinite; }
.spinner-small { display: inline-block; width: 22px; height: 22px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease infinite; }

/* Toast */
.toast-notification { position: fixed; top: 90px; right: 24px; display: flex; align-items: center; gap: 10px; padding: 14px 22px; border-radius: 10px; font-size: 0.95rem; font-weight: 600; z-index: 10000; box-shadow: 0 8px 30px rgba(0,0,0,0.12); }
.toast-notification.success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.toast-notification.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.toast-enter-active { animation: slideInRight 0.3s ease; }
.toast-leave-active { animation: slideOutRight 0.3s ease; }
@keyframes slideInRight { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
@keyframes slideOutRight { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(40px); } }

/* Animations */
.animate-in { animation: fadeIn 0.4s ease-out forwards; opacity: 0; transform: translateY(15px); }
@keyframes fadeIn { to { opacity: 1; transform: translateY(0); } }

.modal-fade-enter-active { animation: modalFadeIn 0.3s ease; }
.modal-fade-leave-active { animation: modalFadeOut 0.2s ease; }
@keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes modalFadeOut { from { opacity: 1; transform: scale(1) translateY(0); } to { opacity: 0; transform: scale(0.95) translateY(10px); } }

/* RESPONSIVE */
@media (max-width: 900px) {
    .checkout-layout { grid-template-columns: 1fr; }
    .sticky-sidebar { position: static; }
    .form-row { grid-template-columns: 1fr; }
}
</style>
