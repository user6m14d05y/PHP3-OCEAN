<template>
  <div class="profile-orders-page animate-in">
    <div class="page-header">
      <h2 class="page-title">Tất cả đơn hàng</h2>
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
            >
              ↻ Mua lại
            </button>
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

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';

const orders = ref([]);
const loading = ref(true);
const actionLoading = ref(null);
const currentPage = ref(1);
const pagination = ref(null);

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
    const res = await api.get(`/profile/orders?page=${page}`);
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

const cancelOrder = async (orderId) => {
  if (!confirm('Bạn có chắc chắn muốn hủy đơn hàng này không?')) return;
  
  actionLoading.value = orderId;
  try {
    const res = await api.put(`/profile/orders/${orderId}/cancel`);
    if (res.data.status === 'success') {
      alert('Hủy đơn hàng thành công!');
      await fetchOrders(currentPage.value);
    }
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi khi hủy đơn hàng';
    alert(msg);
  } finally {
    actionLoading.value = null;
  }
};

onMounted(() => {
  fetchOrders();
});
</script>

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
