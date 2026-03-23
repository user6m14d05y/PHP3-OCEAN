<script setup>
import { ref, onMounted, computed, nextTick } from 'vue';
import api from '../../axios.js';
import { Toast, Modal } from 'bootstrap';

const coupons = ref([]);
const isLoading = ref(true);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const isEditing = ref(false);
const searchQuery = ref('');

const defaultForm = () => ({
    id: null,
    code: '',
    type: 'fixed',
    value: '',
    max_discount_value: '',
    min_order_value: '',
    usage_limit: '',
    user_usage_limit: 1,
    is_public: 1,
    is_first_order: 0,
    start_date: '',
    end_date: '',
    is_active: 1,
});

const form = ref(defaultForm());

const toast = ref({ message: '', type: 'success' });
const deletingCouponId = ref(null);
let deleteModalInstance = null;

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('couponToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const fetchCoupons = async () => {
    try {
        isLoading.value = true;
        const response = await api.get('/admin/coupons');
        if (response.data.status === 'success') {
            coupons.value = response.data.data;
        }
    } catch (error) {
        showToast('Lỗi khi tải mã giảm giá!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

const filteredCoupons = computed(() => {
    let list = coupons.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => c.code.toLowerCase().includes(q));
    }
    return list;
});

onMounted(fetchCoupons);

const openCreateModal = () => {
    isEditing.value = false;
    form.value = defaultForm();
    isModalOpen.value = true;
};

const openEditModal = (coupon) => {
    isEditing.value = true;
    form.value = {
        id: coupon.id,
        code: coupon.code,
        type: coupon.type,
        value: coupon.value,
        max_discount_value: coupon.max_discount_value || '',
        min_order_value: coupon.min_order_value || '',
        usage_limit: coupon.usage_limit || '',
        user_usage_limit: coupon.user_usage_limit !== undefined ? coupon.user_usage_limit : 1,
        is_public: coupon.is_public !== undefined ? coupon.is_public : 1,
        is_first_order: coupon.is_first_order || 0,
        // Xử lý cắt chuỗi ngày giờ để vừa với input datetime-local
        start_date: coupon.start_date ? new Date(coupon.start_date).toISOString().slice(0, 16) : '',
        end_date: coupon.end_date ? new Date(coupon.end_date).toISOString().slice(0, 16) : '',
        is_active: coupon.is_active,
    };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

const handleSubmit = async () => {
    if (!form.value.code.trim()) {
        showToast('Vui lòng nhập mã code!', 'danger');
        return;
    }

    isSubmitting.value = true;

    let payload = { ...form.value };
    
    // Ép kiểu các trường nullable nếu là chuỗi rỗng
    if (payload.max_discount_value === '') payload.max_discount_value = null;
    if (payload.min_order_value === '') payload.min_order_value = null;
    if (payload.usage_limit === '') payload.usage_limit = null;
    if (payload.start_date === '') payload.start_date = null;
    if (payload.end_date === '') payload.end_date = null;
    
    // Giảm tối đa chỉ có ý nghĩa nếu type = percent
    if (payload.type !== 'percent') {
        payload.max_discount_value = null;
    }

    try {
        if (isEditing.value) {
            const res = await api.put(`/admin/coupons/${payload.id}`, payload);
            showToast(res.data.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/admin/coupons', payload);
            showToast(res.data.message || 'Tạo mã mới thành công!', 'success');
        }
        await fetchCoupons();
        closeModal();
    } catch (error) {
        const msg = error.response?.data?.message || (isEditing.value ? 'Cập nhật thất bại!' : 'Tạo mã mới thất bại!');
        showToast(msg, 'danger');
    } finally {
        isSubmitting.value = false;
    }
};

const confirmDeleteCouponPrompt = (id) => {
    deletingCouponId.value = id;
    nextTick(() => {
        const el = document.getElementById('deleteCouponModal');
        if (el) {
            deleteModalInstance = Modal.getOrCreateInstance(el);
            deleteModalInstance.show();
        }
    });
};

const confirmDeleteCoupon = async () => {
    if (!deletingCouponId.value) return;
    try {
        const res = await api.delete(`/admin/coupons/${deletingCouponId.value}`);
        if (deleteModalInstance) deleteModalInstance.hide();
        showToast(res.data.message || 'Đã xóa mã giảm giá!', 'success');
        await fetchCoupons();
    } catch (error) {
        if (deleteModalInstance) deleteModalInstance.hide();
        showToast(error.response?.data?.message || 'Xóa thất bại!', 'danger');
    }
};

const formatValue = (coupon) => {
    if (coupon.type === 'percent') return `${coupon.value}%`;
    if (coupon.type === 'free_ship') return `Ship: ${formatCurrency(coupon.value)}`;
    return formatCurrency(coupon.value);
};

const formatCurrency = (val) => {
    if (!val) return '0đ';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const df = new Date(dateString);
    return df.toLocaleDateString('vi-VN') + ' ' + df.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const isExpired = (endDate) => {
    if (!endDate) return false;
    return new Date(endDate) < new Date();
};
</script>

<template>
    <div class="category-page">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ocean-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/>
                    </svg>
                    Quản lý Mã giảm giá
                </h1>
                <p class="page-subtitle">Quản lý các loại mã giảm giá, Freeship, Flash Sale</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-coupon-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tạo mã mới
            </button>
        </div>

        <!-- Filters & Search  -->
        <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Tìm kiếm theo mã code (VD: SALE50K).."
                    class="search-input"
                />
            </div>
            <div class="filter-stats">
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                    {{ coupons.length }} mã tổng
                </span>
                <span class="stat-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ coupons.filter(c => c.is_active && !isExpired(c.end_date)).length }} đang chạy
                </span>
            </div>
        </div>

        <!-- Coupon Table -->
        <div class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
            <div class="table-header">
                <span class="table-count">
                    <strong>{{ filteredCoupons.length }}</strong> mã giảm giá được tìm thấy
                </span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mã code / Loại</th>
                            <th>Mức giảm</th>
                            <th>Điều kiện / Giới hạn</th>
                            <th>Lượt xài</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="7" class="loading-state">
                                <div class="spinner"></div>
                                Đang tải dữ liệu...
                            </td>
                        </tr>
                        <tr v-else-if="filteredCoupons.length === 0">
                            <td colspan="7" class="empty-cell" style="text-align:center; padding: 40px; color:#9fb3c8;">Không có mã giảm giá nào.</td>
                        </tr>
                        <tr v-for="coupon in filteredCoupons" :key="coupon.id">
                            <td>
                                <span class="code-text" :class="!coupon.is_public ? 'private-code' : ''">
                                    {{ coupon.code }} 
                                    <svg v-if="!coupon.is_public" title="Ẩn nội bộ" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:top;margin-left:2px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <div class="type-badge">{{ coupon.type === 'percent' ? 'Phần trăm' : (coupon.type === 'free_ship' ? 'Freeship' : 'Cố định') }}</div>
                            </td>
                            <td>
                                <strong class="value-text">{{ formatValue(coupon) }}</strong>
                                <div v-if="coupon.type === 'percent' && coupon.max_discount_value" class="max-discount-text">
                                    Tối đa: {{ formatCurrency(coupon.max_discount_value) }}
                                </div>
                            </td>
                            <td>
                                <div class="condition-info">
                                    <div v-if="coupon.min_order_value">Từ: <b>{{ formatCurrency(coupon.min_order_value) }}</b></div>
                                    <div v-else>Mọi đơn hàng</div>
                                    <span v-if="coupon.is_first_order" class="badge-first-order" title="Chỉ áp dụng đơn đầu tiên">Chỉ mở bát</span>
                                    <span v-if="coupon.user_usage_limit" class="badge-user-limit" title="Số lượt dùng mỗi User">User: {{ coupon.user_usage_limit }} lần</span>
                                </div>
                            </td>
                            <td>
                                <div class="usage-info">
                                    <span>{{ coupon.used_count }}</span> / 
                                    <span v-if="coupon.usage_limit">{{ coupon.usage_limit }}</span>
                                    <span v-else>∞</span>
                                </div>
                            </td>
                            <td class="date-cell">
                                <div><small>Từ:</small> {{ formatDate(coupon.start_date) || '-' }}</div>
                                <div><small>Đến:</small> <span :class="{'expired': isExpired(coupon.end_date)}">{{ formatDate(coupon.end_date) || '-' }}</span></div>
                            </td>
                            <td>
                                <span :class="['status-badge', (coupon.is_active && !isExpired(coupon.end_date)) ? 'active' : 'inactive']">
                                    {{ isExpired(coupon.end_date) ? 'Hết hạn' : (coupon.is_active ? 'Kích hoạt' : 'Tạm khóa') }}
                                </span>
                            </td>
                            <td>
                                <button class="btn-action edit" title="Chỉnh sửa" @click="openEditModal(coupon)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="btn-action delete" title="Xóa" @click="confirmDeleteCouponPrompt(coupon.id)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && filteredCoupons.length === 0" class="empty-state">
                <span class="empty-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></span>
                <h3>Không tìm thấy mã giảm giá</h3>
                <p>{{ searchQuery ? 'Thử từ khóa khác.' : 'Bắt đầu bằng cách bấm Tạo mã mới.' }}</p>
            </div>
        </div>

        <!-- Vue Modal cho Tạo/Sửa Mã -->
        <Transition name="modal">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box ocean-card">
                    <div class="modal-head">
                        <h3>{{ isEditing ? 'Chỉnh sửa mã giảm giá' : 'Thêm mã giảm giá mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" class="modal-body">
                        
                        <!-- Cụm Code -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Mã Code <span class="required">*</span></label>
                                <input v-model="form.code" type="text" placeholder="VD: SALE50K" class="form-control" required style="text-transform: uppercase;" />
                            </div>
                        </div>

                        <!-- Cụm Loại & Giá trị -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Áp dụng loại giảm <span class="required">*</span></label>
                                <select v-model="form.type" class="form-control form-select">
                                    <option value="fixed">Giảm cố định (VNĐ)</option>
                                    <option value="percent">Giảm phần trăm (%)</option>
                                    <option value="free_ship">Miễn phí ship (VNĐ)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Mức giảm <span class="required">*</span></label>
                                <input v-model.number="form.value" type="number" min="0" class="form-control" placeholder="0" required />
                            </div>
                        </div>

                        <!-- Cặp giảm tối đa (nếu là percent) -->
                        <div class="form-row" v-if="form.type === 'percent'">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Mức GIẢM TỐI ĐA cho phép (VNĐ)</label>
                                <input v-model.number="form.max_discount_value" type="number" min="0" class="form-control" placeholder="Để trống nếu không giới hạn trần" />
                            </div>
                        </div>

                        <!-- Cụm Điều kiện giá & User -->
                        <div class="form-row" style="margin-top: 8px;">
                            <div class="form-group">
                                <label>Đơn tối thiểu áp dụng (VNĐ)</label>
                                <input v-model.number="form.min_order_value" type="number" min="0" class="form-control" placeholder="Không bắt buộc" />
                            </div>
                            <div class="form-group">
                                <label>Số lượt được Dùng / 1 User</label>
                                <input v-model.number="form.user_usage_limit" type="number" min="1" class="form-control" placeholder="Mặc định: 1" />
                            </div>
                        </div>

                        <!-- Cụm Public & Kho -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tổng số mã phát ra (Kho)</label>
                                <input v-model.number="form.usage_limit" type="number" min="1" class="form-control" placeholder="Để trống = vô hạn" />
                            </div>
                            <div class="form-group">
                                <label>Sự kiện: Chỉ áp dụng Đơn Đầu</label>
                                <div class="toggle-wrap" style="margin-top:6px;">
                                    <label class="toggle-switch-wrapper">
                                        <div class="toggle-switch">
                                            <input type="checkbox" v-model="form.is_first_order" :true-value="1" :false-value="0" class="toggle-input" />
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <span class="toggle-text">{{ form.is_first_order ? 'Có (Chỉ First Order)' : 'Không (Mọi đơn)' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Cụm Ngày -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ngày bắt đầu</label>
                                <input v-model="form.start_date" type="datetime-local" class="form-control" />
                            </div>
                            <div class="form-group">
                                <label>Ngày kết thúc</label>
                                <input v-model="form.end_date" type="datetime-local" class="form-control" />
                            </div>
                        </div>

                        <!-- Cụm Trạng thái -->
                        <div class="form-row" style="background:var(--hover-bg); padding:12px; border-radius:8px;">
                            <div class="form-group mb-0">
                                <label>Công khai mã?</label>
                                <div class="toggle-wrap">
                                    <label class="toggle-switch-wrapper">
                                        <div class="toggle-switch">
                                            <input type="checkbox" v-model="form.is_public" :true-value="1" :false-value="0" class="toggle-input" />
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <span class="toggle-text">{{ form.is_public ? 'Hiện trên Săn Voucher' : 'Mã ẩn / Nội bộ' }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label>Trạng thái Kích Hoạt</label>
                                <div class="toggle-wrap">
                                    <label class="toggle-switch-wrapper">
                                        <div class="toggle-switch">
                                            <input type="checkbox" v-model="form.is_active" :true-value="1" :false-value="0" class="toggle-input" />
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <span class="toggle-text">{{ form.is_active ? 'Đang hoạt động' : 'Đang tạm dừng' }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" @click="closeModal" class="btn-outline">Hủy bỏ</button>
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                <span v-if="isSubmitting">Đang lưu...</span>
                                <span v-else>{{ isEditing ? 'Lưu cập nhật' : 'Tạo mã mới' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>

        <!-- Bootstrap Modal: Xác nhận xóa -->
        <div class="modal fade" id="deleteCouponModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow" style="border-radius: 12px; overflow: hidden;">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title fw-bold" style="font-size:1.1rem">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Xóa mã giảm giá?
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <p class="mb-2" style="font-size:1.05rem; color:#333;">Bạn có chắc chắn muốn xóa (xóa mềm) mã giảm giá này không?</p>
                        <p class="text-danger mb-0 fw-semibold" style="font-size:0.9rem">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Lịch sử giao dịch cũ có chứa mã này sẽ không bị ảnh hưởng!
                        </p>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal" style="border-radius:8px">Hủy</button>
                        <button type="button" class="btn btn-danger px-4 fw-bold" @click="confirmDeleteCoupon" style="border-radius:8px">Đồng ý xóa</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap Toast -->
        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
            <div class="toast align-items-center border-0 border-start border-4" :class="toast.type === 'success' ? 'text-bg-white border-success' : 'text-bg-white border-danger'" id="couponToast" role="alert">
                <div class="d-flex align-items-center p-2">
                    <svg v-if="toast.type === 'success'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" stroke-width="2.5" stroke-linecap="round" class="mx-2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c62828" stroke-width="2.5" stroke-linecap="round" class="mx-2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    
                    <div class="toast-body fw-bold text-dark fs-6">{{ toast.message }}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.category-page { font-family: var(--font-inter); }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

/* Buttons */
.btn-primary {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 8px; border: none;
    background: var(--ocean-blue); color: white;
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(2, 136, 209, 0.2);
}
.btn-primary:hover {
    background: var(--ocean-bright); transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(3, 169, 244, 0.3);
}
.btn-primary:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }

.btn-outline {
    padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-main);
    font-family: var(--font-inter); font-size: 0.85rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.btn-outline:hover { border-color: var(--text-light); }

/* Filters */
.filters-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; margin-bottom: 24px; gap: 16px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 10px 16px; flex: 1; max-width: 400px;
    transition: all 0.2s;
}
.search-box:focus-within {
    border-color: var(--ocean-blue); background: white;
    box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
}
.search-box svg { color: var(--text-light); flex-shrink: 0; }
.search-input {
    background: none; border: none; outline: none;
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.9rem; width: 100%;
}
.search-input::placeholder { color: var(--text-light); }

.filter-stats { display: flex; gap: 8px; flex-shrink: 0; }
.stat-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    font-size: 0.8rem; font-weight: 600;
}
.stat-pill svg { color: var(--ocean-blue); }

/* Loading & Empty */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600; }
.spinner {
    width: 30px; height: 30px; border: 3px solid var(--border-color);
    border-top-color: var(--ocean-blue); border-radius: 50%;
    animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border-color); }
.table-count { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
.table-count strong { color: var(--text-main); font-weight: 800; }

.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color);
    background: var(--ocean-deepest);
}
.data-table :deep(td) {
    padding: 14px 24px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s; vertical-align: middle;
}
.data-table :deep(tbody tr:hover td) { background: var(--hover-bg); }

/* Elements Table Specific */
.code-text { display: block; font-weight: 800; color: #d84315; font-size: 0.95rem; font-family: monospace; letter-spacing: 0.5px; margin-bottom: 4px;}
.private-code { color: #546e7a; }
.type-badge { display: inline-block; font-size: 0.65rem; background: #eceff1; color: #546e7a; padding: 2px 6px; border-radius: 4px; font-weight: 600; }
.value-text { font-size: 1rem; color: var(--seafoam, #128176); }
.max-discount-text { font-size: 0.75rem; color: #d84315; font-weight: 600; margin-top: 2px; }

.condition-info { font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; }
.badge-first-order { display: inline-block; background: #fff3e0; color: #e65100; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-top: 4px; }
.badge-user-limit { display: inline-block; background: #e3f2fd; color: #0d47a1; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-top: 4px; margin-left: 4px;}

.usage-info { font-weight: 600; color: var(--text-main); }
.date-cell div { color: var(--text-muted); font-size: 0.8rem; white-space: nowrap; margin-bottom: 2px; }
.date-cell small { color: #9fb3c8; display: inline-block; width: 55px;}
.expired { color: #d32f2f; font-weight: 600;}

.status-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
.status-badge.active { background: #e8f5e9; color: #2e7d32; }
.status-badge.inactive { background: #ffebee; color: #c62828; }

.btn-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 6px; border: none;
    background: transparent; color: var(--text-muted);
    cursor: pointer; transition: all 0.2s; margin-right: 4px;
}
.btn-action.edit:hover { background: #e3f2fd; color: #1565c0; }
.btn-action.delete:hover { background: #ffebee; color: #c62828; }

.empty-state { text-align: center; padding: 60px 20px; }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 12px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px; }
.empty-state p { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; }

/* Vue Modal for Form */
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.modal-box {
    width: 100%; max-width: 650px; padding: 0;
    max-height: 90vh; overflow-y: auto;
    border-radius: 16px; background: white;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}
.modal-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px 24px; border-bottom: 1px solid var(--border-color);
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.btn-close {
    background: none; border: none; cursor: pointer; margin: 0;
    color: var(--text-muted); display: flex; align-items: center; justify-content: center;
    padding: 4px; border-radius: 6px; transition: all 0.2s;
}
.btn-close:hover { background: var(--hover-bg); color: var(--coral); }
.modal-body { padding: 24px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--border-color); }

/* Form Control */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }
.required { color: var(--coral); }
.form-control {
    width: 100%; padding: 10px 14px; border-radius: 8px;
    border: 1px solid var(--border-color); background: var(--ocean-deepest);
    color: var(--text-main); font-family: var(--font-inter);
    font-size: 0.85rem; transition: all 0.2s; box-sizing: border-box;
}
.form-control:focus { border-color: var(--ocean-blue); outline: none; box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1); }
.form-select {
    appearance: none; cursor: pointer;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23627d98' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 14px center;
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Toggle Slider */
.toggle-wrap { margin-top: 4px; }
.toggle-switch-wrapper { display: flex; align-items: center; gap: 12px; cursor: pointer; }
.toggle-switch { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.toggle-input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--text-light); transition: .3s; border-radius: 24px;
}
.toggle-slider:before {
    position: absolute; content: ""; height: 18px; width: 18px;
    left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%;
}
.toggle-input:checked + .toggle-slider { background-color: var(--ocean-blue); }
.toggle-input:checked + .toggle-slider:before { transform: translateX(20px); }
.toggle-text { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

/* Modal Transition */
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-box, .modal-leave-to .modal-box { transform: scale(0.95) translateY(10px); }

/* Custom Bootstap Toast style */
.text-bg-white { background-color: #fff !important; color: #333 !important; }
</style>
