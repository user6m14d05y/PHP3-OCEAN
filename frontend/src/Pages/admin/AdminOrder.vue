
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const toast = {
  success: (msg) => Swal.fire({ icon: 'success', title: 'Thành công', text: msg, timer: 2500, showConfirmButton: false }),
  error: (msg) => Swal.fire({ icon: 'error', title: 'Lỗi', text: msg, timer: 3000, showConfirmButton: false }),
  info: (msg) => Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg, timer: 5000, showConfirmButton: false })
};

const orders = ref([]);
const loading = ref(true);
const currentStatus = ref('all');
const searchQuery = ref('');

const pagination = ref({
    current_page: 1,
    last_page: 1,
    next_page_url: null,
    prev_page_url: null
});

const statuses = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'confirmed', label: 'Đã duyệt' },
  { value: 'packing', label: 'Đóng gói' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'delivered', label: 'Đã giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' }
];

const fulfillmentOptions = statuses.filter(s => s.value !== 'all');

// Luồng trạng thái tuần tự: không cho nhảy cóc
const statusTransitions = {
  'pending':   ['pending', 'confirmed', 'cancelled'],
  'confirmed': ['confirmed', 'packing', 'cancelled'],
  'packing':   ['packing', 'shipping', 'cancelled'],
  'shipping':  ['shipping', 'delivered'],
  'delivered': ['delivered', 'completed'],
  'completed': ['completed'],
  'cancelled': ['cancelled'],
};

const getAllowedFulfillmentOptions = (currentStatus) => {
  const allowed = statusTransitions[currentStatus] || [currentStatus];
  return fulfillmentOptions.filter(s => allowed.includes(s.value));
};

const fetchOrders = async (page = 1) => {
  loading.value = true;
  try {
    const res = await api.get('/admin/orders', {
      params: {
        page: page,
        status: currentStatus.value,
        search: searchQuery.value || null
      },
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        'Expires': '0',
      }
    });
    if (res.data.status === 'success') {
      orders.value = res.data.data.data.map(o => ({ ...o, _prevFulfillmentStatus: o.fulfillment_status }));
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        next_page_url: res.data.data.next_page_url,
        prev_page_url: res.data.data.prev_page_url
      };
    }
  } catch (error) {
    console.error('Fetch orders failed', error);
    toast.error('Không thể tải danh sách đơn hàng');
  } finally {
    loading.value = false;
  }
};

const changeFilter = (status) => {
  currentStatus.value = status;
  fetchOrders(1);
};

const changePage = (page) => {
  if(page >= 1 && page <= pagination.value.last_page) {
      fetchOrders(page);
  }
}

// Lý do hủy đơn Admin (chuẩn ecommerce)
const adminCancelReasons = [
  'Khách hàng yêu cầu hủy',
  'Hết hàng / Không đủ tồn kho',
  'Sản phẩm bị lỗi / hư hỏng',
  'Thông tin đơn hàng không hợp lệ',
  'Không liên lạc được với khách hàng',
  'Đơn hàng trùng lặp',
  'Vi phạm chính sách đặt hàng',
  'Lý do khác',
];

const showCancelReasonModal = async () => {
  const { value: reason } = await Swal.fire({
    title: '⚠️ Hủy đơn hàng',
    html: `
      <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px;">Chọn lý do hủy đơn:</p>
      <div style="text-align:left; max-height:240px; overflow-y:auto;">
        ${adminCancelReasons.map(r => `
          <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; margin-bottom:6px; border:1.5px solid #e2e8f0; border-radius:10px; cursor:pointer; background:white;"
                 onmouseover="this.style.borderColor='#dc2626'; this.style.background='#fef2f2'"
                 onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e2e8f0'; this.style.background='white'}">
            <input type="radio" name="admin_cancel_reason" value="${r}" style="accent-color:#dc2626; width:16px; height:16px; flex-shrink:0;">
            <span style="font-size:0.88rem; color:#334155;">${r}</span>
          </label>
        `).join('')}
      </div>
      <textarea id="admin-custom-reason" placeholder="Nhập lý do cụ thể..."
        style="display:none; width:100%; margin-top:12px; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.9rem; min-height:70px; resize:vertical; outline:none; font-family:inherit;"></textarea>
    `,
    showCancelButton: true,
    confirmButtonText: 'Xác nhận hủy',
    cancelButtonText: 'Quay lại',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    width: 500,
    didOpen: () => {
      const radios = Swal.getPopup().querySelectorAll('input[name="admin_cancel_reason"]');
      const customArea = Swal.getPopup().querySelector('#admin-custom-reason');
      radios.forEach(radio => {
        radio.addEventListener('change', () => {
          radios.forEach(r => { const l = r.closest('label'); if (l) { l.style.borderColor = '#e2e8f0'; l.style.background = 'white'; } });
          const lbl = radio.closest('label'); if (lbl) { lbl.style.borderColor = '#dc2626'; lbl.style.background = '#fef2f2'; }
          customArea.style.display = radio.value === 'Lý do khác' ? 'block' : 'none';
        });
      });
    },
    preConfirm: () => {
      const selected = Swal.getPopup().querySelector('input[name="admin_cancel_reason"]:checked');
      if (!selected) { Swal.showValidationMessage('Vui lòng chọn lý do hủy đơn'); return false; }
      if (selected.value === 'Lý do khác') {
        const custom = Swal.getPopup().querySelector('#admin-custom-reason').value.trim();
        if (!custom) { Swal.showValidationMessage('Vui lòng nhập lý do cụ thể'); return false; }
        return custom;
      }
      return selected.value;
    }
  });
  return reason || null;
};

const updateOrderFulfillment = async (order) => {
  const oldStatus = order._prevFulfillmentStatus || 'pending';
  
  // Nếu chọn hủy → bắt buộc nhập lý do
  if (order.fulfillment_status === 'cancelled') {
    const cancelReason = await showCancelReasonModal();
    if (!cancelReason) {
      // User đóng modal → rollback
      order.fulfillment_status = oldStatus;
      return;
    }
    try {
      const res = await api.put(`/admin/orders/${order.order_id}/status`, {
        fulfillment_status: 'cancelled',
        note: cancelReason
      });
      if (res.data.status === 'success') {
        order._prevFulfillmentStatus = 'cancelled';
        toast.success('Đã hủy đơn hàng thành công!');
      }
    } catch (error) {
      order.fulfillment_status = oldStatus;
      toast.error(error.response?.data?.message || 'Lỗi hủy đơn hàng');
    }
    return;
  }

  // Các trạng thái khác → cập nhật bình thường
  try {
    const res = await api.put(`/admin/orders/${order.order_id}/status`, {
      fulfillment_status: order.fulfillment_status
    });

    if (res.data.status === 'success') {
      order._prevFulfillmentStatus = order.fulfillment_status;
      toast.success('Cập nhật trạng thái thành công!');
    }
  } catch (error) {
    order.fulfillment_status = oldStatus;
    toast.error(error.response?.data?.message || 'Lỗi cập nhật trạng thái');
  }
};

const updateOrderPayment = async (order) => {
  try {
    const res = await api.put(`/admin/orders/${order.order_id}/status`, {
      payment_status: order.payment_status
    });

    if (res.data.status === 'success') {
      toast.success('Cập nhật thanh toán thành công!');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi cập nhật');
  }
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price);
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getStatusColor = (status) => {
  switch (status) {
    case 'pending': return 'text-warning';
    case 'confirmed': return 'text-primary';
    case 'shipping': return 'text-info';
    case 'completed': return 'text-success';
    case 'cancelled': return 'text-danger';
    default: return '';
  }
};

onMounted(() => {
  fetchOrders();

  // Setup Real-time Echo Connection
  if (window.Echo) {
    // Sử dụng custom authorizer được cấu hình sẵn trong echo.js
    window.Echo.private('admin-notifications')
      .listen('.OrderCreatedAdmin', (event) => {
        console.log('Real-time order received:', event);
        toast.info(`🛒 Có đơn hàng mới: ${event.order_code}`);
        
        // Add order to top of list if we're on page 1
        if (pagination.value.current_page === 1 && (currentStatus.value === 'all' || currentStatus.value === 'pending')) {
            orders.value.unshift({ 
                ...event, 
                order_id: event.order_id,
                is_new: true 
            });
            // remove last item if array gets too big for the page
            if (orders.value.length > 10) orders.value.pop();
        }
      });
  }
});

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leave('admin-notifications');
  }
});
</script>
<template>
  <div class="admin-orders">
    <div class="admin-header">
      <div>
        <h1 class="admin-title">Quản lý Đơn hàng</h1>
        <p class="admin-subtitle">Theo dõi và cập nhật trạng thái các đơn đặt hàng</p>
      </div>
      <div>
        <input type="text" v-model="searchQuery" @keyup.enter="fetchOrders" placeholder="Tìm mã đơn, sđt..." class="form-control" style="width: 250px">
      </div>
    </div>

    <!-- Filters -->
    <div class="status-filters mb-4">
      <button v-for="st in statuses" :key="st.value" 
          class="btn filter-btn btn-sm" 
          :class="{ 'btn-primary': currentStatus === st.value, 'btn-outline-primary': currentStatus !== st.value }"
          @click="changeFilter(st.value)">
        {{ st.label }}
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <!-- Table -->
    <div v-else class="table-responsive bg-white rounded shadow-sm">
      <table class="table table-hover align-middle mb-0">
        <thead class="bg-light">
          <tr>
            <th>Mã đơn</th>
            <th>Khách hàng</th>
            <th>Ngày đặt</th>
            <th>Tổng tiền</th>
            <th>Xử lý đơn (Fulfillment)</th>
            <th>Thanh toán</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orders.length === 0">
            <td colspan="7" class="text-center py-4 text-muted">Không có đơn hàng nào</td>
          </tr>
          <tr v-for="order in orders" :key="order.order_id" :class="{'bg-warning bg-opacity-10 fw-bold': order.is_new}">
            <td class="text-primary">{{ order.order_code }}</td>
            <td>
              <div>{{ order.recipient_name }}</div>
              <small class="text-muted">{{ order.recipient_phone }}</small>
            </td>
            <td>{{ formatDate(order.created_at) }}</td>
            <td class="text-danger fw-bold">{{ formatPrice(order.grand_total) }}</td>
            <td>
              <select class="form-select form-select-sm" :class="getStatusColor(order.fulfillment_status)" v-model="order.fulfillment_status" @change="updateOrderFulfillment(order)" :disabled="order.fulfillment_status === 'completed' || order.fulfillment_status === 'cancelled'">
                <option v-for="s in getAllowedFulfillmentOptions(order._prevFulfillmentStatus || order.fulfillment_status)" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </td>
            <td>
              <select class="form-select form-select-sm" v-model="order.payment_status" @change="updateOrderPayment(order)">
                <option value="unpaid">Chưa TT</option>
                <option value="paid">Đã TT</option>
                <option value="failed">Thất bại</option>
                <option value="refunded">Hoàn tiền</option>
              </select>
            </td>
            <td>
              <router-link :to="{ name: 'admin-order-detail', params: { id: order.order_id } }" class="btn btn-sm btn-outline-secondary">Chi tiết</router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Paginator -->
    <div class="d-flex justify-content-between align-items-center mt-4" v-if="pagination.last_page > 1">
        <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.prev_page_url" @click="changePage(pagination.current_page - 1)">Trang trước</button>
        <span class="text-muted">Trang {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url" @click="changePage(pagination.current_page + 1)">Trang tiếp</button>
    </div>

  </div>
</template>


<style scoped>
.admin-orders {
  padding: 24px;
  background-color: #f8f9fa;
  min-height: calc(100vh - 60px);
}
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}
.admin-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0;
}
.admin-subtitle {
  color: #6c757d;
  margin: 0;
  font-size: 0.95rem;
}
.filter-btn {
  margin-right: 8px;
  margin-bottom: 8px;
  border-radius: 6px;
  font-weight: 500;
}
select.form-select-sm {
  font-size: 0.85rem;
  padding: 0.25rem 2rem 0.25rem 0.5rem;
}
</style>
