<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/axios';
import Swal from 'sweetalert2';

const route = useRoute();
const router = useRouter();

const toast = {
  success: (msg) => Swal.fire({ icon: 'success', title: 'Thành công', text: msg, timer: 2500, showConfirmButton: false }),
  error: (msg) => Swal.fire({ icon: 'error', title: 'Lỗi', text: msg, timer: 3000, showConfirmButton: false }),
};

const order = ref(null);
const loading = ref(true);

const statuses = [
  { value: 'pending', label: 'Chờ duyệt' },
  { value: 'confirmed', label: 'Đã duyệt' },
  { value: 'packing', label: 'Đóng gói' },
  { value: 'shipping', label: 'Đang giao' },
  { value: 'delivered', label: 'Đã giao' },
  { value: 'completed', label: 'Hoàn thành' },
  { value: 'cancelled', label: 'Đã hủy' }
];

// Luồng trạng thái tuần tự
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
  return statuses.filter(s => allowed.includes(s.value));
};

const getStatusLabel = (value) => statuses.find(s => s.value === value)?.label || value;

const paymentLabels = {
  unpaid: 'Chưa thanh toán',
  paid: 'Đã thanh toán',
  failed: 'Thất bại',
  refunded: 'Hoàn tiền',
  partially_refunded: 'Hoàn một phần',
};

const paymentMethodLabels = {
  cod: 'Thanh toán khi nhận hàng (COD)',
  vnpay: 'VNPay',
  momo: 'Ví MoMo',
  bank_transfer: 'Chuyển khoản ngân hàng',
};

const fetchOrder = async () => {
  loading.value = true;
  try {
    const res = await api.get(`/admin/orders/${route.params.id}`);
    if (res.data.status === 'success') {
      order.value = { ...res.data.data, _prevFulfillmentStatus: res.data.data.fulfillment_status };
    }
  } catch (error) {
    console.error('Fetch order detail failed', error);
    toast.error('Không thể tải thông tin đơn hàng');
    router.push({ name: 'admin-order' });
  } finally {
    loading.value = false;
  }
};

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
            <input type="radio" name="detail_cancel_reason" value="${r}" style="accent-color:#dc2626; width:16px; height:16px; flex-shrink:0;">
            <span style="font-size:0.88rem; color:#334155;">${r}</span>
          </label>
        `).join('')}
      </div>
      <textarea id="detail-custom-reason" placeholder="Nhập lý do cụ thể..."
        style="display:none; width:100%; margin-top:12px; padding:12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:0.9rem; min-height:70px; resize:vertical; outline:none; font-family:inherit;"></textarea>
    `,
    showCancelButton: true,
    confirmButtonText: 'Xác nhận hủy',
    cancelButtonText: 'Quay lại',
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#64748b',
    width: 500,
    didOpen: () => {
      const radios = Swal.getPopup().querySelectorAll('input[name="detail_cancel_reason"]');
      const customArea = Swal.getPopup().querySelector('#detail-custom-reason');
      radios.forEach(radio => {
        radio.addEventListener('change', () => {
          radios.forEach(r => { const l = r.closest('label'); if (l) { l.style.borderColor = '#e2e8f0'; l.style.background = 'white'; } });
          const lbl = radio.closest('label'); if (lbl) { lbl.style.borderColor = '#dc2626'; lbl.style.background = '#fef2f2'; }
          customArea.style.display = radio.value === 'Lý do khác' ? 'block' : 'none';
        });
      });
    },
    preConfirm: () => {
      const selected = Swal.getPopup().querySelector('input[name="detail_cancel_reason"]:checked');
      if (!selected) { Swal.showValidationMessage('Vui lòng chọn lý do hủy đơn'); return false; }
      if (selected.value === 'Lý do khác') {
        const custom = Swal.getPopup().querySelector('#detail-custom-reason').value.trim();
        if (!custom) { Swal.showValidationMessage('Vui lòng nhập lý do cụ thể'); return false; }
        return custom;
      }
      return selected.value;
    }
  });
  return reason || null;
};

const updateFulfillment = async () => {
  const oldStatus = order.value._prevFulfillmentStatus;

  // Nếu chọn hủy → bắt buộc nhập lý do
  if (order.value.fulfillment_status === 'cancelled') {
    const cancelReason = await showCancelReasonModal();
    if (!cancelReason) {
      order.value.fulfillment_status = oldStatus;
      return;
    }
    try {
      const res = await api.put(`/admin/orders/${order.value.order_id}/status`, {
        fulfillment_status: 'cancelled',
        note: cancelReason
      });
      if (res.data.status === 'success') {
        order.value._prevFulfillmentStatus = 'cancelled';
        toast.success('Đã hủy đơn hàng thành công!');
        fetchOrder();
      }
    } catch (error) {
      order.value.fulfillment_status = oldStatus;
      toast.error(error.response?.data?.message || 'Lỗi hủy đơn hàng');
    }
    return;
  }

  // Các trạng thái khác
  try {
    const res = await api.put(`/admin/orders/${order.value.order_id}/status`, {
      fulfillment_status: order.value.fulfillment_status
    });
    if (res.data.status === 'success') {
      order.value._prevFulfillmentStatus = order.value.fulfillment_status;
      toast.success('Cập nhật trạng thái xử lý thành công!');
      fetchOrder();
    }
  } catch (error) {
    order.value.fulfillment_status = oldStatus;
    toast.error(error.response?.data?.message || 'Lỗi cập nhật trạng thái');
  }
};

const updatePayment = async () => {
  try {
    const res = await api.put(`/admin/orders/${order.value.order_id}/status`, {
      payment_status: order.value.payment_status
    });
    if (res.data.status === 'success') {
      toast.success('Cập nhật thanh toán thành công!');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Lỗi cập nhật');
  }
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(price || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  return date.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getStatusBadgeClass = (status) => {
  const map = {
    pending: 'badge-warning',
    confirmed: 'badge-primary',
    packing: 'badge-info',
    shipping: 'badge-info',
    delivered: 'badge-success',
    completed: 'badge-success',
    cancelled: 'badge-danger',
  };
  return map[status] || 'badge-secondary';
};

const getPaymentBadgeClass = (status) => {
  const map = { unpaid: 'badge-warning', paid: 'badge-success', failed: 'badge-danger', refunded: 'badge-secondary' };
  return map[status] || 'badge-secondary';
};

const getProductImage = (item) => {
  if (item.variant?.image_url) return `http://localhost:8383/storage/${item.variant.image_url}`;
  if (item.product?.main_image) return `http://localhost:8383/storage/${item.product.main_image}`;
  if (item.product?.thumbnail_url && item.product.thumbnail_url !== '0') return `http://localhost:8383/storage/${item.product.thumbnail_url}`;
  return 'https://placehold.co/80x80?text=No+Img';
};

// Timeline steps
const timelineSteps = [
  { key: 'pending', label: 'Đặt hàng', icon: '📝', field: 'created_at' },
  { key: 'confirmed', label: 'Xác nhận', icon: '✅', field: 'confirmed_at' },
  { key: 'packing', label: 'Đóng gói', icon: '📦', field: null },
  { key: 'shipping', label: 'Vận chuyển', icon: '🚚', field: 'shipped_at' },
  { key: 'delivered', label: 'Đã giao', icon: '📬', field: 'delivered_at' },
  { key: 'completed', label: 'Hoàn thành', icon: '🎉', field: 'completed_at' },
];

const getStepStatus = (stepKey) => {
  if (!order.value) return 'inactive';
  if (order.value.fulfillment_status === 'cancelled') {
    const cancelledIdx = stepOrder.indexOf(order.value.fulfillment_status);
    return 'cancelled';
  }
  const stepOrder = ['pending', 'confirmed', 'packing', 'shipping', 'delivered', 'completed'];
  const currentIdx = stepOrder.indexOf(order.value.fulfillment_status);
  const stepIdx = stepOrder.indexOf(stepKey);
  if (stepIdx < currentIdx) return 'done';
  if (stepIdx === currentIdx) return 'active';
  return 'inactive';
};

onMounted(() => fetchOrder());
</script>

<template>
  <div class="order-detail-page">
    <!-- Loading -->
    <div v-if="loading" class="loading-box">
      <div class="spinner-border text-primary" role="status"></div>
    </div>

    <template v-if="order && !loading">
      <!-- Header -->
      <div class="detail-header">
        <div class="header-left">
          <button class="btn-back" @click="router.push({ name: 'admin-order' })">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Quay lại
          </button>
          <div>
            <h1 class="page-title">Đơn hàng <span class="order-code">#{{ order.order_code }}</span></h1>
            <p class="page-sub">Ngày đặt: {{ formatDate(order.created_at) }}</p>
          </div>
        </div>
        <div class="header-badges">
          <span class="status-badge" :class="getStatusBadgeClass(order.fulfillment_status)">{{ getStatusLabel(order.fulfillment_status) }}</span>
          <span class="status-badge" :class="getPaymentBadgeClass(order.payment_status)">{{ paymentLabels[order.payment_status] || order.payment_status }}</span>
        </div>
      </div>

      <!-- Timeline -->
      <div class="timeline-card" v-if="order.fulfillment_status !== 'cancelled'">
        <h3 class="card-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Tiến trình đơn hàng
        </h3>
        <div class="timeline">
          <div v-for="(step, idx) in timelineSteps" :key="step.key" class="timeline-step" :class="getStepStatus(step.key)">
            <div class="step-connector" v-if="idx > 0"></div>
            <div class="step-dot">
              <span class="step-icon">{{ step.icon }}</span>
            </div>
            <div class="step-info">
              <span class="step-label">{{ step.label }}</span>
              <span class="step-time" v-if="step.field && order[step.field]">{{ formatDate(order[step.field]) }}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="timeline-card cancelled-banner" v-else>
        <div class="cancelled-content">
          <span class="cancelled-icon">❌</span>
          <div>
            <h3>Đơn hàng đã bị hủy</h3>
            <p v-if="order.cancel_reason">Lý do: {{ order.cancel_reason }}</p>
            <p v-if="order.cancelled_at">Thời gian: {{ formatDate(order.cancelled_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="detail-grid">
        <!-- LEFT -->
        <div class="detail-main">
          <!-- Sản phẩm -->
          <div class="info-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              Sản phẩm ({{ order.items?.length || 0 }})
            </h3>
            <div class="items-list">
              <div v-for="item in order.items" :key="item.order_item_id" class="order-item">
                <img :src="getProductImage(item)" :alt="item.product_name" class="item-img" />
                <div class="item-info">
                  <h4 class="item-name">{{ item.product_name }}</h4>
                  <div class="item-variant" v-if="item.color || item.size">
                    <span v-if="item.color" class="variant-tag">{{ item.color }}</span>
                    <span v-if="item.size" class="variant-tag">{{ item.size }}</span>
                  </div>
                  <div class="item-sku" v-if="item.sku">SKU: {{ item.sku }}</div>
                </div>
                <div class="item-qty">x{{ item.quantity }}</div>
                <div class="item-price">{{ formatPrice(item.line_total) }}</div>
              </div>
            </div>

            <!-- Tổng kết -->
            <div class="order-summary">
              <div class="summary-row"><span>Tạm tính</span><span>{{ formatPrice(order.subtotal) }}</span></div>
              <div class="summary-row"><span>Phí vận chuyển</span><span>{{ order.shipping_fee == 0 ? 'Miễn phí' : formatPrice(order.shipping_fee) }}</span></div>
              <div class="summary-row discount" v-if="order.discount_amount > 0"><span>Giảm giá</span><span>-{{ formatPrice(order.discount_amount) }}</span></div>
              <div class="summary-row total"><span>Tổng cộng</span><span>{{ formatPrice(order.grand_total) }}</span></div>
            </div>
          </div>

          <!-- Lịch sử trạng thái -->
          <div class="info-card" v-if="order.status_histories && order.status_histories.length > 0">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Lịch sử trạng thái
            </h3>
            <div class="history-list">
              <div v-for="h in [...order.status_histories].reverse()" :key="h.history_id" class="history-item">
                <div class="history-dot" :class="getStatusBadgeClass(h.new_status)"></div>
                <div class="history-content">
                  <div class="history-transition">
                    <span class="status-badge sm" :class="getStatusBadgeClass(h.old_status)" v-if="h.old_status">{{ getStatusLabel(h.old_status) }}</span>
                    <svg v-if="h.old_status" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    <span class="status-badge sm" :class="getStatusBadgeClass(h.new_status)">{{ getStatusLabel(h.new_status) }}</span>
                  </div>
                  <p class="history-note" v-if="h.note">{{ h.note }}</p>
                  <span class="history-time">{{ formatDate(h.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT SIDEBAR -->
        <div class="detail-sidebar">
          <!-- Cập nhật trạng thái -->
          <div class="info-card action-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Cập nhật trạng thái
            </h3>
            <div class="action-group">
              <label class="action-label">Xử lý đơn hàng</label>
              <select class="form-select" v-model="order.fulfillment_status" @change="updateFulfillment" :disabled="order.fulfillment_status === 'completed' || order.fulfillment_status === 'cancelled'">
                <option v-for="s in getAllowedFulfillmentOptions(order._prevFulfillmentStatus)" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div class="action-group">
              <label class="action-label">Thanh toán</label>
              <select class="form-select" v-model="order.payment_status" @change="updatePayment">
                <option value="unpaid">Chưa thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="failed">Thất bại</option>
                <option value="refunded">Hoàn tiền</option>
              </select>
            </div>
          </div>

          <!-- Thông tin khách hàng -->
          <div class="info-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Khách hàng
            </h3>
            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Tên</span>
                <span class="info-value">{{ order.user?.name || order.recipient_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ order.user?.email || '—' }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Điện thoại</span>
                <span class="info-value">{{ order.user?.phone || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Thông tin giao hàng -->
          <div class="info-card">
            <h3 class="card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Giao hàng
            </h3>
            <div class="info-rows">
              <div class="info-row">
                <span class="info-label">Người nhận</span>
                <span class="info-value fw-bold">{{ order.recipient_name }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">SĐT</span>
                <span class="info-value">{{ order.recipient_phone }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">Địa chỉ</span>
                <span class="info-value">{{ order.shipping_address }}</span>
              </div>
              <div class="info-row">
                <span class="info-label">PT Thanh toán</span>
                <span class="info-value fw-bold">{{ paymentMethodLabels[order.payment_method] || order.payment_method }}</span>
              </div>
              <div class="info-row" v-if="order.note">
                <span class="info-label">Ghi chú</span>
                <span class="info-value note-text">{{ order.note }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.order-detail-page {
  padding: 24px;
  background-color: #f8f9fa;
  min-height: calc(100vh - 60px);
  font-family: 'Inter', sans-serif;
}

/* Loading */
.loading-box { text-align: center; padding: 80px 0; }

/* Header */
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}
.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}
.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border: 1.5px solid #e2e8f0;
  background: white;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-back:hover { background: #0d6efd; color: white; border-color: #0d6efd; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; color: #0f172a; }
.order-code { color: #0d6efd; }
.page-sub { margin: 4px 0 0; font-size: 0.9rem; color: #64748b; }
.header-badges { display: flex; gap: 8px; align-items: center; }

/* Badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.3px;
}
.status-badge.sm { padding: 3px 10px; font-size: 0.75rem; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-primary { background: #dbeafe; color: #1e40af; }
.badge-info { background: #cffafe; color: #155e75; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #e2e8f0; color: #475569; }

/* Timeline Card */
.timeline-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 24px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.timeline-card .card-title { margin-top: 0; margin-bottom: 20px; }
.timeline {
  display: flex;
  align-items: flex-start;
  gap: 0;
  overflow-x: auto;
  padding-bottom: 8px;
}
.timeline-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  min-width: 100px;
  position: relative;
}
.step-connector {
  position: absolute;
  top: 20px;
  right: 50%;
  width: 100%;
  height: 3px;
  background: #e2e8f0;
  z-index: 0;
}
.timeline-step.done .step-connector { background: #22c55e; }
.timeline-step.active .step-connector { background: linear-gradient(90deg, #22c55e 50%, #e2e8f0 50%); }
.step-dot {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f1f5f9;
  border: 3px solid #e2e8f0;
  z-index: 1;
  transition: all 0.3s;
  font-size: 1.1rem;
}
.timeline-step.done .step-dot { background: #d1fae5; border-color: #22c55e; }
.timeline-step.active .step-dot { background: #dbeafe; border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); }
.step-info { text-align: center; margin-top: 10px; }
.step-label { font-weight: 600; font-size: 0.82rem; color: #64748b; display: block; }
.timeline-step.done .step-label { color: #065f46; }
.timeline-step.active .step-label { color: #1e40af; }
.step-time { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; display: block; }

/* Cancelled Banner */
.cancelled-banner { background: #fef2f2; border-color: #fecaca; }
.cancelled-content { display: flex; align-items: center; gap: 16px; }
.cancelled-icon { font-size: 2rem; }
.cancelled-content h3 { margin: 0; color: #991b1b; font-size: 1.1rem; }
.cancelled-content p { margin: 4px 0 0; color: #b91c1c; font-size: 0.9rem; }

/* Grid Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 7fr 5fr;
  gap: 24px;
}

/* Cards */
.info-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  margin-bottom: 20px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.card-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 20px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.card-title svg { color: #0d6efd; }

/* Action Card */
.action-card { border-left: 4px solid #0d6efd; }
.action-group { margin-bottom: 16px; }
.action-group:last-child { margin-bottom: 0; }
.action-label { font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
.form-select {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  outline: none;
  transition: border-color 0.2s;
  background-color: white;
  cursor: pointer;
}
.form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,0.1); }
.form-select:disabled { background-color: #f1f5f9; cursor: not-allowed; opacity: 0.7; }

/* Info Rows */
.info-rows { display: flex; flex-direction: column; gap: 14px; }
.info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.info-label { font-size: 0.85rem; color: #64748b; font-weight: 500; min-width: 90px; flex-shrink: 0; }
.info-value { font-size: 0.9rem; color: #0f172a; text-align: right; word-break: break-word; }
.fw-bold { font-weight: 700 !important; }
.note-text { background: #fffbeb; padding: 8px 12px; border-radius: 6px; font-style: italic; font-size: 0.85rem; border: 1px dashed #fbbf24; text-align: left; }

/* Items List */
.items-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
.order-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  transition: all 0.2s;
}
.order-item:hover { border-color: #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.item-img {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  flex-shrink: 0;
}
.item-info { flex: 1; min-width: 0; }
.item-name { margin: 0; font-size: 0.95rem; font-weight: 600; color: #0f172a; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.item-variant { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.variant-tag { padding: 2px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: #e0f2fe; color: #0369a1; }
.item-sku { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }
.item-qty { font-weight: 700; color: #64748b; font-size: 0.95rem; padding: 0 8px; }
.item-price { font-weight: 700; color: #0f172a; font-size: 1rem; min-width: 110px; text-align: right; }

/* Order Summary */
.order-summary {
  border-top: 2px dashed #e2e8f0;
  padding-top: 20px;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  font-size: 0.92rem;
  color: #475569;
}
.summary-row.discount span:last-child { color: #16a34a; font-weight: 600; }
.summary-row.total {
  border-top: 2px solid #e2e8f0;
  margin-top: 8px;
  padding-top: 14px;
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
}
.summary-row.total span:last-child { color: #dc2626; font-size: 1.2rem; }

/* History */
.history-list { display: flex; flex-direction: column; gap: 16px; }
.history-item { display: flex; gap: 14px; align-items: flex-start; }
.history-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
}
.history-content { flex: 1; }
.history-transition { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.history-note { margin: 6px 0 2px; font-size: 0.85rem; color: #64748b; font-style: italic; }
.history-time { font-size: 0.75rem; color: #94a3b8; }

/* Responsive */
@media (max-width: 992px) {
  .detail-grid { grid-template-columns: 1fr; }
  .detail-header { flex-direction: column; }
  .timeline { flex-wrap: wrap; gap: 12px; }
  .timeline-step { min-width: 80px; }
  .step-connector { display: none; }
}
</style>
