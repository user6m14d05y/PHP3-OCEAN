<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';
import { useRouter } from 'vue-router';

const orders = ref([]);
const loading = ref(true);
const actionLoading = ref(null);
const currentPage = ref(1);
const pagination = ref(null);
const currentFilter = ref('all');
const router = useRouter();

const filterTabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'pending', label: 'Chờ xác nhận' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' },
];

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

const getStatusText = (status) => {
  switch (status) {
    case 'pending': return 'Đơn hàng đang chờ xác nhận';
    case 'confirmed': return 'Đơn hàng đã được xác nhận';
    case 'packing': return 'Đang đóng gói sản phẩm';
    case 'shipping': return 'Shipper đang giao hàng đến bạn';
    case 'delivered': return 'Đã giao hàng thành công';
    case 'completed': return 'Đơn hàng đã hoàn thành';
    case 'cancelled': return 'Đơn hàng đã bị hủy';
    case 'returned': return 'Đơn hàng đã hoàn trả';
    default: return 'Đang xử lý';
  }
};

const getSummaryStatusText = (status) => {
  switch (status) {
    case 'pending': return 'Chờ xác nhận';
    case 'shipping': 
    case 'packing':
    case 'confirmed': return 'Đang giao hàng';
    case 'delivered':
    case 'completed': return 'Hoàn thành';
    case 'cancelled': return 'Đã hủy';
    case 'returned': return 'Hoàn trả';
    default: return 'Đang xử lý';
  }
};

const getStatusClass = (status) => {
  if (['pending', 'confirmed', 'packing'].includes(status)) return 'status-info';
  if (['shipping'].includes(status)) return 'status-warning';
  if (['delivered', 'completed'].includes(status)) return 'status-success';
  if (['cancelled', 'returned'].includes(status)) return 'status-danger';
  return 'status-default';
};

const getStatusIcon = (status) => {
  if (status === 'pending') return '📋';
  if (status === 'shipping') return '🚚';
  if (status === 'cancelled') return '❌';
  if (status === 'completed' || status === 'delivered') return '✅';
  return '📦';
};

const fetchOrders = async (page = 1) => {
  loading.value = true;
  try {
    const res = await api.get(`/profile/orders?page=${page}&status=${currentFilter.value}`);
    if (res.data.status === 'success') {
      orders.value = res.data.data.data;
      pagination.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total
      };
      currentPage.value = page;
    }
  } catch (err) {
    console.error('Lỗi lấy danh sách đơn hàng: ', err);
  } finally {
    loading.value = false;
  }
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    fetchOrders(page);
  }
};

const setFilter = (status) => {
  if (currentFilter.value !== status) {
    currentFilter.value = status;
    fetchOrders(1);
  }
};

import Swal from 'sweetalert2';

// Lý do hủy đơn phổ biến (chuẩn ecommerce)
const cancelReasons = [
  'Tôi muốn thay đổi sản phẩm (kích thước, màu sắc, số lượng)',
  'Tôi muốn thay đổi địa chỉ giao hàng',
  'Tôi tìm thấy giá rẻ hơn ở nơi khác',
  'Tôi không còn nhu cầu mua nữa',
  'Thời gian giao hàng quá lâu',
  'Đặt nhầm sản phẩm / đặt trùng đơn',
  'Muốn thay đổi phương thức thanh toán',
  'Lý do khác',
];

const cancelOrder = async (orderId) => {
  // Bước 1: Hiện Modal chọn lý do
  const { value: selectedReason } = await Swal.fire({
    title: 'Hủy đơn hàng',
    html: `
      <p style="color:#64748b; font-size:0.9rem; margin-bottom:16px;">Vui lòng cho chúng tôi biết lý do bạn muốn hủy đơn hàng:</p>
      <div id="cancel-reasons-list" style="text-align:left; max-height:260px; overflow-y:auto;">
        ${cancelReasons.map((r, i) => `
          <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; margin-bottom:6px; border:1.5px solid #e2e8f0; border-radius:10px; cursor:pointer; transition:all 0.2s; background:white;" 
                 onmouseover="this.style.borderColor='#0288d1'; this.style.background='#f0f9ff'" 
                 onmouseout="if(!this.querySelector('input').checked){this.style.borderColor='#e2e8f0'; this.style.background='white'}">
            <input type="radio" name="cancel_reason" value="${r}" style="accent-color:#0288d1; width:16px; height:16px; flex-shrink:0;">
            <span style="font-size:0.9rem; color:#334155; line-height:1.4;">${r}</span>
          </label>
        `).join('')}
      </div>
      <textarea id="custom-cancel-reason" placeholder="Nhập lý do cụ thể của bạn..." 
        style="display:none; width:100%; margin-top:12px; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.9rem; min-height:80px; resize:vertical; outline:none; font-family:inherit;"
        onfocus="this.style.borderColor='#0288d1';" onblur="this.style.borderColor='#e2e8f0';"></textarea>
    `,
    showCancelButton: true,
    confirmButtonText: 'Xác nhận hủy đơn',
    cancelButtonText: 'Quay lại',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    width: 520,
    customClass: { popup: 'cancel-modal-popup' },
    didOpen: () => {
      const radios = Swal.getPopup().querySelectorAll('input[name="cancel_reason"]');
      const customArea = Swal.getPopup().querySelector('#custom-cancel-reason');
      radios.forEach(radio => {
        radio.addEventListener('change', () => {
          // Reset all label styles
          radios.forEach(r => {
            const lbl = r.closest('label');
            if (lbl) { lbl.style.borderColor = '#e2e8f0'; lbl.style.background = 'white'; }
          });
          // Highlight selected
          const selectedLabel = radio.closest('label');
          if (selectedLabel) { selectedLabel.style.borderColor = '#0288d1'; selectedLabel.style.background = '#f0f9ff'; }
          // Show/hide custom textarea
          customArea.style.display = radio.value === 'Lý do khác' ? 'block' : 'none';
        });
      });
    },
    preConfirm: () => {
      const selected = Swal.getPopup().querySelector('input[name="cancel_reason"]:checked');
      if (!selected) {
        Swal.showValidationMessage('Vui lòng chọn một lý do hủy đơn');
        return false;
      }
      if (selected.value === 'Lý do khác') {
        const custom = Swal.getPopup().querySelector('#custom-cancel-reason').value.trim();
        if (!custom) {
          Swal.showValidationMessage('Vui lòng nhập lý do cụ thể');
          return false;
        }
        return custom;
      }
      return selected.value;
    }
  });

  if (!selectedReason) return; // User cancelled

  // Bước 2: Gọi API hủy đơn
  actionLoading.value = orderId;
  try {
    const res = await api.put(`/profile/orders/${orderId}/cancel`, {
      cancel_reason: selectedReason
    });
    if (res.data.status === 'success') {
      Swal.fire({
        icon: 'success',
        title: 'Đã hủy đơn hàng',
        text: 'Đơn hàng của bạn đã được hủy thành công.',
        timer: 2500,
        showConfirmButton: false,
      });
      await fetchOrders(currentPage.value);
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi hủy đơn hàng';
    Swal.fire({ icon: 'error', title: 'Không thể hủy', text: msg });
  } finally {
    actionLoading.value = null;
  }
};
const buyAgain = async (orderId) => {
  actionLoading.value = orderId;
  try {
    const res = await api.post(`/cart/buy-again/${orderId}`);
    if (res.data.status === 'success') {
      router.push('/cart');
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi thêm vào giỏ hàng';
    Swal.fire({ icon: 'error', title: 'Không thể thêm', text: msg });
  } finally {
    actionLoading.value = null;
  }
};

onMounted(() => {
  fetchOrders();
});
</script>

<template>
  <div class="profile-orders-page animate-in">
    <div class="page-header">
      <h2 class="page-title">Tất cả đơn hàng</h2>
    </div>

    <!-- Thanh lọc trạng thái đơn hàng -->
    <div class="order-status-tabs">
      <button 
        v-for="tab in filterTabs" 
        :key="tab.value" 
        class="status-tab" 
        :class="{ active: currentFilter === tab.value }"
        @click="setFilter(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Đang tải danh sách đơn hàng...</p>
    </div>

    <div v-else-if="orders.length === 0" class="empty-state">
      <div class="empty-icon">📦</div>
      <h3>Chưa có đơn hàng nào</h3>
      <p>Bạn chưa đặt bất kỳ đơn hàng nào. Hãy mua sắm ngay nhé!</p>
      <router-link to="/product" class="btn-primary mt-4">Tiếp tục mua sắm</router-link>
    </div>

    <div v-else class="orders-list">
      <div v-for="order in orders" :key="order.order_id" class="order-card">
        <div class="order-header">
          <div class="order-header-left">
            <span class="order-code">#{{ order.order_code }}</span>
            <div class="order-meta">
              <span>{{ formatDate(order.created_at) }}</span>
              <span class="dot">•</span>
              <span>{{ order.items ? order.items.length : 0 }} sản phẩm</span>
            </div>
          </div>
          <div class="order-header-center">
            <div class="status-badge" :class="getStatusClass(order.fulfillment_status)">
              <span class="status-icon" v-html="getStatusIcon(order.fulfillment_status)"></span>
              {{ getStatusText(order.fulfillment_status) }}
            </div>
          </div>
          <div class="order-header-right">
            <span class="order-total">{{ formatPrice(order.grand_total) }}</span>
            <div class="payment-status-badge" :class="order.fulfillment_status">
              {{ getSummaryStatusText(order.fulfillment_status) }}
            </div>
            
            <button 
              v-if="order.fulfillment_status === 'pending'" 
              class="btn-action btn-cancel-order"
              @click="cancelOrder(order.order_id)"
              :disabled="actionLoading === order.order_id"
            >
              <span v-if="actionLoading === order.order_id" class="spinner-small"></span>
              <span v-else>⊗ Yêu cầu hủy</span>
            </button>
            <button 
              v-else-if="['completed', 'cancelled', 'returned'].includes(order.fulfillment_status)" 
              class="btn-action btn-buy-again" 
              @click="buyAgain(order.order_id)"
            >
              ↻ Mua lại
            </button>
            <router-link :to="{ name: 'profile-order-detail', params: { id: order.order_id } }" class="btn-action btn-detail mt-2">
              Xem chi tiết
            </router-link>
          </div>
        </div>
        
        <!-- Hiển thị sản phẩm tóm tắt (tuỳ chọn) -->
        <div class="order-items-preview" v-if="order.items && order.items.length > 0">
           <div class="preview-item">
              <span class="item-name">{{ order.items[0].product_name }}</span>
              <span class="item-variant" v-if="order.items[0].variant_name">({{ order.items[0].variant_name }})</span>
              <span class="item-qty">x{{ order.items[0].quantity }}</span>
           </div>
           <div class="preview-more" v-if="order.items.length > 1">
              và {{ order.items.length - 1 }} sản phẩm khác...
           </div>
        </div>
      </div>
      
      <!-- Phân trang -->
      <div v-if="pagination && pagination.last_page > 1" class="pagination">
        <button 
          class="page-btn" 
          :disabled="currentPage === 1" 
          @click="changePage(currentPage - 1)">«</button>
        <span class="page-info">Trang {{ currentPage }} / {{ pagination.last_page }}</span>
        <button 
          class="page-btn" 
          :disabled="currentPage === pagination.last_page" 
          @click="changePage(currentPage + 1)">»</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-orders-page {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.04);
  padding: 24px;
  min-height: 500px;
}

.page-header {
  margin-bottom: 24px;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 16px;
}

.page-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

/* Order Status Tabs */
.order-status-tabs {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding-bottom: 20px;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 24px;
}
.order-status-tabs::-webkit-scrollbar {
  height: 4px;
}
.order-status-tabs::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.status-tab {
  background: white;
  border: 1px solid #cbd5e1;
  padding: 8px 16px;
  border-radius: 8px;
  white-space: nowrap;
  font-weight: 600;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}
.status-tab:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}
.status-tab.active {
  background: #0288d1;
  color: white;
  border-color: #0288d1;
}

/* Loading & Empty */
.loading-state {
  text-align: center;
  padding: 60px 0;
  color: #64748b;
}
.spinner {
  width: 40px; height: 40px;
  border: 3px solid #f1f5f9;
  border-top-color: #0288d1;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 16px;
}
.spinner-small {
  display: inline-block; width: 14px; height: 14px;
  border: 2px solid rgba(220, 38, 38, 0.3);
  border-radius: 50%; border-top-color: #dc2626;
  animation: spin 1s ease infinite;
}
@keyframes spin { 100% { transform: rotate(360deg); } }

.empty-state {
  text-align: center;
  padding: 60px 20px;
}
.empty-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.5; }
.empty-state h3 { font-size: 1.1rem; color: #334155; margin-bottom: 8px; font-weight: 700; }
.empty-state p { color: #64748b; font-size: 0.95rem; }
.btn-primary { display: inline-block; background: #0288d1; color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; text-align: center; transition: background 0.2s; }
.btn-primary:hover { background: #039be5; }
.mt-4 { margin-top: 16px; }

/* Orders List */
.orders-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.order-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: box-shadow 0.2s;
  background: #fafafb;
}
.order-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.03); border-color: #cbd5e1; }

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.order-header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.order-code {
  font-weight: 800;
  color: #1e293b;
  font-size: 1.05rem;
}
.order-meta {
  color: #64748b;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 6px;
}
.dot { font-size: 0.8rem; opacity: 0.5; }

.order-header-center {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px;
  border-radius: 30px;
  font-size: 0.85rem;
  font-weight: 600;
  background: white;
  border: 1px solid #e2e8f0;
}
.status-badge.status-info { color: #475569; background: #f8fafc; border-color: #e2e8f0; }
.status-badge.status-warning { color: #d97706; background: #fef3c7; border-color: #fde68a; }
.status-badge.status-success { color: #16a34a; background: #dcfce3; border-color: #bbf7d0; }
.status-badge.status-danger { color: #dc2626; background: #fee2e2; border-color: #fecaca; }

.order-header-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  min-width: 140px;
}

.order-total {
  font-weight: 800;
  color: #0288d1;
  font-size: 1.1rem;
}

.payment-status-badge {
  font-size: 0.75rem;
  padding: 4px 12px;
  border-radius: 12px;
  font-weight: 600;
}
.payment-status-badge.pending { background: #f1f5f9; color: #64748b; }
.payment-status-badge.shipping { background: #e0f2fe; color: #0288d1; }
.payment-status-badge.completed { background: #fee2e2; color: #ef4444; }
.payment-status-badge.cancelled { background: #fee2e2; color: #dc2626; }

.btn-action {
  background: white;
  border: 1.5px solid;
  border-radius: 20px;
  padding: 6px 14px;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 4px;
}
.btn-cancel-order {
  border-color: #fca5a5;
  color: #ef4444;
}
.btn-cancel-order:hover {
  background: #fef2f2;
  border-color: #ef4444;
}
.btn-cancel-order:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-buy-again {
  border-color: #0288d1;
  color: #0288d1;
}
.btn-buy-again:hover {
  background: #f0f9ff;
  border-color: #0288d1;
}

.btn-detail {
  border-color: #cbd5e1;
  color: #475569;
  text-decoration: none;
}
.btn-detail:hover {
  background: #f8fafc;
  border-color: #475569;
  color: #0f172a;
}

/* Order items preview */
.order-items-preview {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px dashed #e2e8f0;
  font-size: 0.85rem;
}
.preview-item {
  color: #334155;
}
.item-name { font-weight: 600; }
.item-variant { color: #64748b; margin-left: 4px; }
.item-qty { font-weight: 700; color: #0288d1; margin-left: 6px; }
.preview-more { color: #94a3b8; margin-top: 4px; font-style: italic; }

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  margin-top: 20px;
}
.page-btn {
  background: white;
  border: 1px solid #cbd5e1;
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; font-weight: 700; color: #334155;
}
.page-btn:hover:not(:disabled) { background: #f1f5f9; color: #0288d1; border-color: #0288d1; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.9rem; font-weight: 600; color: #64748b; }

.animate-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .order-header { flex-direction: column; gap: 12px; }
  .order-header-center { justify-content: flex-start; }
  .order-header-right { align-items: flex-start; }
}
</style>
