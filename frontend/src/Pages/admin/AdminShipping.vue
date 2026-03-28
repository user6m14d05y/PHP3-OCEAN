<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../axios.js';

// === State ===
const zones = ref([]);
const totalZones = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const perPage = ref(10);
const searchQuery = ref('');
const isLoading = ref(true);

// Modal
const isModalOpen = ref(false);
const isEditing = ref(false);
const isSubmitting = ref(false);
const errors = ref({});
const formError = ref('');

const defaultForm = {
    name: '',
    provinces: [],
    shipping_fee: 0,
    free_ship_threshold: null,
    delivery_time: '',
    priority: 50,
    is_active: true,
};
const form = ref({ ...defaultForm });
const provinceInput = ref('');

// Delete
const deletingId = ref(null);
const isDeleteModalOpen = ref(false);

// Toast
const toastMessage = ref('');
const toastType = ref('success');
const showToastFlag = ref(false);

const showToast = (msg, type = 'success') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToastFlag.value = true;
    setTimeout(() => { showToastFlag.value = false; }, 3000);
};

// === Fetch ===
const fetchZones = async (page = 1) => {
    isLoading.value = true;
    try {
        const params = { page, per_page: perPage.value };
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        const res = await api.get('/admin/shipping-zones', { params });
        if (res.data.status === 'success') {
            zones.value = res.data.data.data;
            totalZones.value = res.data.data.total;
            currentPage.value = res.data.data.current_page;
            lastPage.value = res.data.data.last_page;
        }
    } catch (e) {
        showToast('Lỗi khi tải dữ liệu!', 'danger');
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => fetchZones());

// === Search ===
let searchTimeout = null;
const handleSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchZones(1), 400);
};

// === Modal ===
const openCreateModal = () => {
    form.value = { ...defaultForm, provinces: [] };
    provinceInput.value = '';
    isEditing.value = false;
    errors.value = {};
    formError.value = '';
    isModalOpen.value = true;
};

const openEditModal = (zone) => {
    let provs = zone.provinces;
    if (typeof provs === 'string') {
        try { provs = JSON.parse(provs); } catch (e) { provs = []; }
    }
    form.value = {
        id: zone.id,
        name: zone.name,
        provinces: Array.isArray(provs) ? [...provs] : [],
        shipping_fee: zone.shipping_fee,
        free_ship_threshold: zone.free_ship_threshold,
        delivery_time: zone.delivery_time || '',
        priority: zone.priority,
        is_active: zone.is_active,
    };
    provinceInput.value = '';
    isEditing.value = true;
    errors.value = {};
    formError.value = '';
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
};

// === Province tags ===
const addProvince = () => {
    const val = provinceInput.value.trim();
    if (val && !form.value.provinces.includes(val)) {
        form.value.provinces.push(val);
    }
    provinceInput.value = '';
};

const removeProvince = (index) => {
    form.value.provinces.splice(index, 1);
};

const handleProvinceKeydown = (e) => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addProvince();
    }
};

// === Submit ===
const handleSubmit = async () => {
    errors.value = {};
    formError.value = '';
    let hasError = false;

    if (!form.value.name.trim()) { errors.value.name = 'Vui lòng nhập tên khu vực.'; hasError = true; }
    if (form.value.shipping_fee === '' || form.value.shipping_fee === null || form.value.shipping_fee < 0) {
        errors.value.shipping_fee = 'Phí ship phải >= 0.'; hasError = true;
    }
    if (!form.value.delivery_time.trim()) { errors.value.delivery_time = 'Vui lòng nhập thời gian giao hàng.'; hasError = true; }
    if (form.value.priority === '' || form.value.priority === null) {
        errors.value.priority = 'Vui lòng nhập độ ưu tiên.'; hasError = true;
    }

    if (hasError) return;

    isSubmitting.value = true;
    try {
        const payload = { ...form.value };
        if (payload.free_ship_threshold === '' || payload.free_ship_threshold === 0) payload.free_ship_threshold = null;

        if (isEditing.value) {
            const res = await api.put(`/admin/shipping-zones/${payload.id}`, payload);
            showToast(res.data.message || 'Cập nhật thành công!', 'success');
        } else {
            const res = await api.post('/admin/shipping-zones', payload);
            showToast(res.data.message || 'Tạo khu vực thành công!', 'success');
        }
        await fetchZones(currentPage.value);
        closeModal();
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.errors) {
            const be = error.response.data.errors;
            for (const key in be) errors.value[key] = be[key][0];
        } else {
            formError.value = error.response?.data?.message || 'Có lỗi xảy ra!';
        }
    } finally {
        isSubmitting.value = false;
    }
};

// === Delete ===
const confirmDeletePrompt = (id) => {
    deletingId.value = id;
    isDeleteModalOpen.value = true;
};

const confirmDelete = async () => {
    if (!deletingId.value) return;
    try {
        const res = await api.delete(`/admin/shipping-zones/${deletingId.value}`);
        showToast(res.data.message || 'Đã xóa!', 'success');
        isDeleteModalOpen.value = false;
        await fetchZones(currentPage.value);
    } catch (e) {
        isDeleteModalOpen.value = false;
        showToast(e.response?.data?.message || 'Xóa thất bại!', 'danger');
    }
};

// === Helpers ===
const formatCurrency = (val) => {
    if (!val && val !== 0) return '—';
    return new Intl.NumberFormat('vi-VN').format(val) + ' đ';
};

const provincesPreview = (arr) => {
    if (!arr) return '—';
    let list = arr;
    if (typeof arr === 'string') {
        try { list = JSON.parse(arr); } catch (e) { return arr.length > 35 ? arr.substring(0, 35) + '...' : arr; }
    }
    if (!Array.isArray(list) || !list.length) return '—';
    const text = list.join(', ');
    return text.length > 35 ? text.substring(0, 35) + '...' : text;
};

const fromIndex = computed(() => (currentPage.value - 1) * perPage.value + 1);
const toIndex = computed(() => Math.min(currentPage.value * perPage.value, totalZones.value));
</script>

<template>
    <div class="shipping-page">
        <!-- Toast -->
        <transition name="toast-slide">
            <div v-if="showToastFlag" class="toast-floating" :class="'toast-' + toastType">
                <span>{{ toastMessage }}</span>
                <button class="toast-close" @click="showToastFlag = false">×</button>
            </div>
        </transition>

        <!-- Page Header -->
        <div class="page-header animate-in">
            <div class="header-info">
                <h1 class="page-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ocean-blue, #1d4ed8)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"></rect>
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                        <circle cx="5.5" cy="18.5" r="2.5"></circle>
                        <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    Quản lý phí vận chuyển
                </h1>
                <p class="page-subtitle">Cấu hình phí ship theo khu vực</p>
            </div>
            <button @click="openCreateModal" class="btn-primary" id="add-shipping-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Thêm khu vực
            </button>
        </div>

        <!-- Search -->
        <div class="search-bar animate-in">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="searchQuery" @input="handleSearch" type="text" placeholder="Tìm tên khu vực..." class="search-input" />
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrapper animate-in">
            <div v-if="isLoading" class="loading-state">
                <div class="spinner"></div>
                <span>Đang tải dữ liệu...</span>
            </div>
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Khu vực</th>
                        <th>Tỉnh/Thành</th>
                        <th>Phí ship</th>
                        <th>Miễn phí khi ≥</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="zones.length === 0">
                        <td colspan="7" class="empty-cell">Không có khu vực nào.</td>
                    </tr>
                    <tr v-for="zone in zones" :key="zone.id">
                        <td>
                            <div class="zone-name">{{ zone.name }}</div>
                            <div class="zone-priority">Ưu tiên: {{ zone.priority }}</div>
                        </td>
                        <td class="province-cell">{{ provincesPreview(zone.provinces) }}</td>
                        <td class="fee-cell">{{ formatCurrency(zone.shipping_fee) }}</td>
                        <td class="fee-cell">{{ zone.free_ship_threshold ? formatCurrency(zone.free_ship_threshold) : '—' }}</td>
                        <td>{{ zone.delivery_time || '—' }}</td>
                        <td>
                            <span class="status-badge" :class="zone.is_active ? 'status-active' : 'status-inactive'">
                                {{ zone.is_active ? 'Hoạt động' : 'Tắt' }}
                            </span>
                        </td>
                        <td class="actions-cell">
                            <button class="btn-icon-edit" @click="openEditModal(zone)" title="Sửa">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-icon-delete" @click="confirmDeletePrompt(zone.id)" title="Xóa">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalZones > 0" class="pagination-bar">
            <span class="pagination-info">Hiển thị {{ fromIndex }}-{{ toIndex }} trong tổng số {{ totalZones }} khu vực</span>
            <div class="pagination-btns">
                <button class="page-btn" :disabled="currentPage <= 1" @click="fetchZones(currentPage - 1)">Trước</button>
                <button v-for="p in lastPage" :key="p" class="page-btn" :class="{ 'page-btn--active': p === currentPage }" @click="fetchZones(p)">{{ p }}</button>
                <button class="page-btn" :disabled="currentPage >= lastPage" @click="fetchZones(currentPage + 1)">Sau</button>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <transition name="modal-fade">
            <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>{{ isEditing ? 'Chỉnh sửa khu vực' : 'Thêm khu vực mới' }}</h3>
                        <button class="btn-close" @click="closeModal">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="handleSubmit" novalidate class="modal-body">
                        <div v-if="formError" class="form-error-box">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            {{ formError }}
                        </div>

                        <div class="form-group">
                            <label>Tên khu vực <span class="required">*</span></label>
                            <input v-model="form.name" type="text" class="form-control" :class="{'is-invalid': errors.name}" placeholder="VD: Nội thành Buôn Ma Thuột" />
                            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
                        </div>

                        <div class="form-group">
                            <label>Tỉnh/Thành áp dụng</label>
                            <div class="tags-input-wrapper">
                                <span v-for="(prov, i) in form.provinces" :key="i" class="tag-chip">
                                    {{ prov }}
                                    <svg @click="removeProvince(i)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="tag-remove"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </span>
                                <input v-model="provinceInput" @keydown="handleProvinceKeydown" @blur="addProvince" type="text" class="tag-input" placeholder="Nhập tỉnh/thành, nhấn Enter..." />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Phí ship (VNĐ) <span class="required">*</span></label>
                                <input v-model.number="form.shipping_fee" type="number" min="0" class="form-control" :class="{'is-invalid': errors.shipping_fee}" placeholder="0" />
                                <span v-if="errors.shipping_fee" class="field-error">{{ errors.shipping_fee }}</span>
                            </div>
                            <div class="form-group">
                                <label>Miễn phí khi đơn ≥ (VNĐ)</label>
                                <input v-model.number="form.free_ship_threshold" type="number" min="0" class="form-control" placeholder="Để trống = không miễn phí" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Thời gian giao hàng <span class="required">*</span></label>
                                <input v-model="form.delivery_time" type="text" class="form-control" :class="{'is-invalid': errors.delivery_time}" placeholder="VD: 1-2 ngày" />
                                <span v-if="errors.delivery_time" class="field-error">{{ errors.delivery_time }}</span>
                            </div>
                            <div class="form-group">
                                <label>Độ ưu tiên <span class="required">*</span></label>
                                <input v-model.number="form.priority" type="number" min="0" max="999" class="form-control" :class="{'is-invalid': errors.priority}" placeholder="50" />
                                <span v-if="errors.priority" class="field-error">{{ errors.priority }}</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" v-model="form.is_active" />
                                <span>Kích hoạt khu vực này</span>
                            </label>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" class="btn-primary" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Đang lưu...' : (isEditing ? 'Cập nhật' : 'Tạo mới') }}
                            </button>
                            <button type="button" class="btn-secondary" @click="closeModal">Hủy</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Delete Modal -->
        <transition name="modal-fade">
            <div v-if="isDeleteModalOpen" class="modal-overlay" @click.self="isDeleteModalOpen = false">
                <div class="modal-content modal-sm">
                    <div class="modal-header">
                        <h3 style="color: #c62828;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c62828" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            Xóa khu vực?
                        </h3>
                        <button class="btn-close" @click="isDeleteModalOpen = false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p style="font-size:1rem; color:#333; margin-bottom:20px;">Bạn có chắc chắn muốn xóa khu vực vận chuyển này?</p>
                        <div class="modal-actions">
                            <button class="btn-danger" @click="confirmDelete">Xóa</button>
                            <button class="btn-secondary" @click="isDeleteModalOpen = false">Hủy</button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Validation */
.field-error { color: #e53935; font-size: 0.8rem; margin-top: 4px; display: block; }
.is-invalid { border-color: #e53935 !important; background-color: #fff2f2 !important; }
.form-error-box { background: #fff2f2; border: 1px solid #e53935; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
.required { color: #e53935; }

/* Page */
.shipping-page { font-family: 'Inter', system-ui, -apple-system, sans-serif; }

.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.page-title { font-size: 1.5rem; font-weight: 800; color: #1a1a2e; display: flex; align-items: center; gap: 12px; }
.page-subtitle { font-size: 0.9rem; color: #64748b; margin-top: 4px; }

.btn-primary { display: flex; align-items: center; gap: 8px; padding: 12px 22px; background: #1d4ed8; color: #fff; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-primary:hover { background: #1e40af; }
.btn-primary:disabled { opacity: .6; cursor: not-allowed; }

.btn-secondary { padding: 12px 22px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
.btn-secondary:hover { background: #e2e8f0; }

.btn-danger { padding: 12px 22px; background: #dc2626; color: #fff; border: none; border-radius: 10px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
.btn-danger:hover { background: #b91c1c; }

/* Search */
.search-bar { margin-bottom: 20px; }
.search-input-wrapper { position: relative; max-width: 360px; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.search-input { width: 100%; padding: 12px 14px 12px 42px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; background: #fff; transition: border-color 0.2s; }
.search-input:focus { border-color: #1d4ed8; outline: none; box-shadow: 0 0 0 3px rgba(29,78,216,.1); }

/* Loading */
.loading-state { text-align: center; padding: 60px 20px; color: #64748b; }
.spinner { width: 28px; height: 28px; border: 3px solid #e2e8f0; border-top-color: #1d4ed8; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 12px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-wrapper { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px 18px; text-align: left; font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.data-table td { padding: 16px 18px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; color: #1e293b; vertical-align: middle; }
.data-table tbody tr:hover { background: #f8fafc; }
.empty-cell { text-align: center; padding: 40px !important; color: #94a3b8; }

.zone-name { font-weight: 700; color: #1e293b; }
.zone-priority { font-size: 0.75rem; color: #22c55e; font-weight: 600; margin-top: 2px; }
.province-cell { color: #64748b; font-size: 0.85rem; max-width: 200px; }
.fee-cell { color: #1d4ed8; font-weight: 700; white-space: nowrap; }

.status-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-inactive { background: #fee2e2; color: #dc2626; }

.actions-cell { display: flex; gap: 8px; }
.btn-icon-edit, .btn-icon-delete { width: 34px; height: 34px; border: none; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
.btn-icon-edit { background: #eff6ff; color: #1d4ed8; }
.btn-icon-edit:hover { background: #dbeafe; }
.btn-icon-delete { background: #fef2f2; color: #dc2626; }
.btn-icon-delete:hover { background: #fee2e2; }

/* Pagination */
.pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
.pagination-info { font-size: 0.85rem; color: #64748b; }
.pagination-btns { display: flex; gap: 4px; }
.page-btn { padding: 8px 14px; border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; font-size: 0.85rem; cursor: pointer; color: #475569; transition: all 0.2s; }
.page-btn:hover:not(:disabled) { background: #f1f5f9; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-btn--active { background: #1d4ed8 !important; color: #fff !important; border-color: #1d4ed8 !important; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal-content { background: #fff; border-radius: 16px; width: 580px; max-width: 95vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
.modal-sm { width: 420px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #f1f5f9; }
.modal-header h3 { font-size: 1.1rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.btn-close { background: none; border: none; cursor: pointer; color: #94a3b8; padding: 4px; border-radius: 6px; }
.btn-close:hover { background: #f1f5f9; color: #1e293b; }
.modal-body { padding: 24px; }
.modal-actions { display: flex; gap: 12px; margin-top: 24px; justify-content: flex-end; }

/* Forms */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #334155; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.02em; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; background: #fff; color: #1e293b; transition: border-color 0.2s; }
.form-control:focus { border-color: #1d4ed8; outline: none; box-shadow: 0 0 0 3px rgba(29,78,216,.1); }
.form-control::placeholder { color: #94a3b8; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Tags input */
.tags-input-wrapper { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 10px; border: 1px solid #e2e8f0; border-radius: 8px; min-height: 42px; align-items: center; background: #fff; transition: border-color 0.2s; }
.tags-input-wrapper:focus-within { border-color: #1d4ed8; box-shadow: 0 0 0 3px rgba(29,78,216,.1); }
.tag-chip { display: flex; align-items: center; gap: 4px; padding: 4px 10px; background: #eff6ff; color: #1d4ed8; border-radius: 6px; font-size: 0.8rem; font-weight: 600; }
.tag-remove { cursor: pointer; opacity: 0.6; }
.tag-remove:hover { opacity: 1; }
.tag-input { border: none; outline: none; flex: 1; min-width: 120px; font-size: 0.85rem; padding: 4px 0; background: transparent; }

/* Checkbox */
.checkbox-label { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; cursor: pointer; color: #334155; font-weight: 500; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: #1d4ed8; }

/* Toast */
.toast-floating { position: fixed; top: 24px; right: 24px; padding: 14px 20px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; z-index: 2000; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.15); }
.toast-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
.toast-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.toast-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; opacity: 0.6; }
.toast-close:hover { opacity: 1; }

/* Transitions */
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.25s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
.toast-slide-enter-active { transition: all 0.3s ease; }
.toast-slide-leave-active { transition: all 0.2s ease; }
.toast-slide-enter-from { transform: translateX(100px); opacity: 0; }
.toast-slide-leave-to { opacity: 0; }

.animate-in { animation: fadeInUp 0.4s ease; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
</style>
