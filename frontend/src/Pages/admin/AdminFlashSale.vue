<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios.js';
import Swal from 'sweetalert2';

// -- States --
const flashSales = ref([]);
const isModalOpen = ref(false);
const isEditing = ref(false);
const errors = ref({});
const isLoading = ref(false);

const STATUS_LABELS = {
    'draft': { text: 'Nháp', class: 'bg-secondary' },
    'active': { text: 'Đang chạy', class: 'bg-success' },
    'ended': { text: 'Đã kết thúc', class: 'bg-danger' },
};

// Form data
const defaultForm = () => ({
    id: null,
    name: '',
    start_time: '',
    end_time: '',
    status: 'draft',
    items: [],
});
const form = ref(defaultForm());

// Product Search Feature
const productSearchTerm = ref('');
const searchedProducts = ref([]);
let searchTimeout = null;

const searchProducts = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (productSearchTerm.value.length < 2) {
            searchedProducts.value = [];
            return;
        }
        try {
            // Sử dụng endpoint search dành riêng cho FlashSale
            const res = await api.get('/admin/flash-sale/search-products', { params: { query: productSearchTerm.value } });
            // Đảm bảo ép kiểu về Array để .length luôn lấy được, bất kể API trả về object hay mảng
            searchedProducts.value = Array.isArray(res.data.data) ? res.data.data : Object.values(res.data.data || {});
        } catch (e) {
            console.error('Lỗi tìm sản phẩm', e);
        }
    }, 400);
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
};

const resolveThumbnail = (url) => {
    if (!url) return '/images/no-image.png';
    if (url.startsWith('http')) return url;
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8383/api';
    return `${apiUrl.replace('/api', '')}/storage/${url}`;
};

const addProductToItems = (product) => {
    // Tránh thêm trùng lặp
    if (form.value.items.some(item => item.product_id === product.product_id)) {
        Swal.fire('Lưu ý', 'Sản phẩm đã có trong danh sách!', 'warning');
        return;
    }
    
    form.value.items.push({
        product_id: product.product_id,
        product: product, // Giữ metadata để hiển thị trong table
        campaign_price: product.base_price,
        campaign_stock: 1, // Default
        sold: 0
    });
    
    searchedProducts.value = [];
    productSearchTerm.value = '';
};

const removeItem = (index) => {
    form.value.items.splice(index, 1);
};

// -- CRUD Logic --
const fetchFlashSales = async () => {
    isLoading.value = true;
    try {
        const res = await api.get('/admin/flash-sale');
        flashSales.value = res.data.data;
    } finally {
        isLoading.value = false;
    }
};

const openCreate = () => {
    isEditing.value = false;
    form.value = defaultForm();
    errors.value = {};
    isModalOpen.value = true;
};

const openEdit = (fs) => {
    isEditing.value = true;
    form.value = {
        id: fs.id,
        name: fs.name,
        // Ép kiểu Date-Time Input phù hợp với HTML5
        start_time: fs.start_time.slice(0, 16),
        end_time: fs.end_time.slice(0, 16),
        status: fs.status,
        items: fs.items.map(i => ({ ...i })) // clone list
    };
    errors.value = {};
    isModalOpen.value = true;
};

const handleSubmit = async () => {
    errors.value = {};
    try {
        if (isEditing.value) {
            await api.put(`/admin/flash-sale/${form.value.id}`, form.value);
        } else {
            await api.post('/admin/flash-sale', form.value);
        }
        Swal.fire('Thành công', 'Lưu thành công!', 'success');
        isModalOpen.value = false;
        fetchFlashSales();
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors;
        } else {
            Swal.fire('Lỗi', 'Lỗi máy chủ!', 'error');
        }
    }
};

const handleDelete = async (id) => {
    const result = await Swal.fire({
         title: 'Khu vực nguy hiểm',
         text: 'Bạn có chắc chắn muốn xóa chiến dịch Flash Sale này không?',
         icon: 'warning',
         showCancelButton: true,
         confirmButtonColor: '#d33',
         confirmButtonText: 'Xóa',
         cancelButtonText: 'Hủy'
    });
    
    if (result.isConfirmed) {
        try {
            await api.delete(`/admin/flash-sale/${id}`);
            Swal.fire('Thành công', 'Đã xóa thành công!', 'success');
            fetchFlashSales();
        } catch (e) {
            Swal.fire('Lỗi', 'Lỗi xóa chiến dịch!', 'error');
        }
    }
};

onMounted(fetchFlashSales);
</script>

<template>
    <div class="admin-fs-container container-fluid py-4">
        <div class="d-flex justify-content-between mb-4">
            <h4>⚡ Quản lý Flash Sale Campaign</h4>
            <button class="btn btn-primary" @click="openCreate">+ Tạo Campaign</button>
        </div>

        <!-- LIST VIEW -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tên Campaign</th>
                            <th>Thời gian bắt đầu</th>
                            <th>Thời gian kết thúc</th>
                            <th>Số lượng SP</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading"><td colspan="6" class="text-center">Đang tải...</td></tr>
                        <tr v-else-if="flashSales.length === 0"><td colspan="6" class="text-center">Chưa có chiến dịch Flash Sale nào.</td></tr>
                        <tr v-for="fs in flashSales" :key="fs.id">
                            <td class="fw-bold">{{ fs.name }}</td>
                            <td>{{ new Date(fs.start_time).toLocaleString('vi-VN') }}</td>
                            <td>{{ new Date(fs.end_time).toLocaleString('vi-VN') }}</td>
                            <td>{{ fs.items?.length || 0 }} Sản phẩm</td>
                            <td>
                                <span :class="['badge', STATUS_LABELS[fs.status]?.class || 'bg-secondary']">
                                    {{ STATUS_LABELS[fs.status]?.text || fs.status }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info me-1" @click="openEdit(fs)">Sửa</button>
                                <button class="btn btn-sm btn-outline-danger" @click="handleDelete(fs.id)">Xóa</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FORM MODAL (Full Width/Overlay) -->
        <div v-if="isModalOpen" class="fs-modal-overlay">
            <div class="card fs-modal-content">
                <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                    <h5 class="m-0">{{ isEditing ? 'Sửa Flash Sale' : 'Tạo mới Flash Sale' }}</h5>
                    <button class="btn-close" @click="isModalOpen = false"></button>
                </div>
                
                <div class="card-body overflow-auto">
                    <!-- General Settings -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Tên Campaign <span class="text-danger">*</span></label>
                            <input v-model="form.name" type="text" class="form-control" />
                            <small class="text-danger">{{ errors.name?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trạng thái</label>
                            <select v-model="form.status" class="form-select">
                                <option value="draft">Bản nháp</option>
                                <option value="active">Active (Set lên Redis)</option>
                                <option value="ended">Ended (Thu hồi Redis)</option>
                            </select>
                            <small class="text-danger">{{ errors.status?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian bắt đầu <span class="text-danger">*</span></label>
                            <input v-model="form.start_time" type="datetime-local" class="form-control" />
                            <small class="text-danger">{{ errors.start_time?.[0] }}</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thời gian kết thúc <span class="text-danger">*</span></label>
                            <input v-model="form.end_time" type="datetime-local" class="form-control" />
                            <small class="text-danger">{{ errors.end_time?.[0] }}</small>
                        </div>
                    </div>

                    <!-- Dynamic Items Settings -->
                    <hr/>
                    <h6 class="fw-bold mb-3">📦 Danh sách sản phẩm Sale</h6>
                    <div class="position-relative mb-3">
                        <input v-model="productSearchTerm" @input="searchProducts" type="text" 
                               class="form-control shadow-sm" placeholder="🔍 Gõ tên để thêm sản phẩm vào sự kiện..." />
                        
                        <ul v-if="searchedProducts && searchedProducts.length > 0" class="list-group position-absolute w-100 mt-1 shadow-lg" style="z-index: 9999; max-height: 250px; overflow-y: auto;">
                            <li v-for="prod in searchedProducts" :key="prod.product_id" 
                                class="list-group-item list-group-item-action cursor-pointer d-flex align-items-center"
                                @click="addProductToItems(prod)">
                                <img :src="resolveThumbnail(prod.thumbnail)" alt="" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />
                                <div>
                                    <div class="fw-bold">{{ prod.name }}</div>
                                    <small>Giá gốc: <span class="text-decoration-line-through text-muted">{{ formatCurrency(prod.base_price) }}</span> | Kho hiện tại: {{ prod.stock }}</small>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tên sản phẩm</th>
                                <th width="200">Giá Sale (VNĐ)</th>
                                <th width="150">Số lượng cấp FB</th>
                                <th width="100">Đã bán</th>
                                <th width="80">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.items" :key="index">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img v-if="item.product && item.product.thumbnail" :src="resolveThumbnail(item.product.thumbnail)" alt="" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;" />
                                        <span>{{ item.product?.name || `Product ID: ${item.product_id}` }}</span>
                                    </div>
                                </td>
                                <td>
                                    <input v-model="item.campaign_price" type="number" class="form-control form-control-sm" />
                                    <small class="text-danger" v-if="errors[`items.${index}.campaign_price`]">{{ errors[`items.${index}.campaign_price`][0] }}</small>
                                </td>
                                <td>
                                    <input v-model="item.campaign_stock" type="number" class="form-control form-control-sm" />
                                    <small class="text-danger" v-if="errors[`items.${index}.campaign_stock`]">{{ errors[`items.${index}.campaign_stock`][0] }}</small>
                                </td>
                                <td>{{ item.sold }}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger" @click="removeItem(index)">✖</button>
                                </td>
                            </tr>
                            <tr v-if="form.items.length === 0">
                                <td colspan="5" class="text-center text-muted">Chưa có sản phẩm nào. Hãy tìm kiếm và chọn sản phẩm ở trên.</td>
                            </tr>
                        </tbody>
                    </table>
                    <small class="text-danger">{{ errors.items?.[0] }}</small>

                </div>
                <!-- Action Bottom -->
                <div class="card-footer bg-white text-end">
                    <button class="btn btn-secondary me-2" @click="isModalOpen = false">Hủy</button>
                    <button class="btn btn-primary" @click="handleSubmit">Lưu Thay Đổi</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fs-modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.5);
    z-index: 1050;
    display: flex;
    justify-content: center;
    align-items: center;
}
.fs-modal-content {
    width: 900px;
    max-width: 95vw;
    max-height: 90vh;
}
.cursor-pointer { cursor: pointer; }
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #f1f1f1; }
::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #888; }
</style>
