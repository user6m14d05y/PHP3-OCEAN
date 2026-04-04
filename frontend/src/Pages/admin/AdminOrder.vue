<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import api from '@/axios';
import Swal from 'sweetalert2';

const toast = {
  success: (msg) => Swal.fire({ icon: 'success', title: 'Thành công', text: msg, timer: 2500, showConfirmButton: false }),
  error: (msg) => Swal.fire({ icon: 'error', title: 'Lỗi', text: msg, timer: 3000, showConfirmButton: false }),
  info: (msg) => Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg, timer: 5000, showConfirmButton: false })
};

const orders = ref([]);
const loading = ref(true);
const currentStatus = ref('');
const searchQuery = ref('');
const dateFrom = ref('');
const dateTo = ref('');

const pagination = ref({
    current_page: 1,
    last_page: 1,
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
        status: currentStatus.value || 'all',
        search: searchQuery.value || null,
        date_from: dateFrom.value || null,
        date_to: dateTo.value || null
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
      };
    }
  } catch (error) {
    console.error('Fetch orders failed', error);
    toast.error('Không thể tải danh sách đơn hàng');
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
    fetchOrders(1);
};

const handleClearFilters = () => {
    searchQuery.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    currentStatus.value = '';
    fetchOrders(1);
};

const handleFilterStatus = (status) => {
    currentStatus.value = currentStatus.value === status ? '' : status;
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
  
  if (order.fulfillment_status === 'cancelled') {
    const cancelReason = await showCancelReasonModal();
    if (!cancelReason) {
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

const formatDate = (dateString, includeTime=true) => {
  const date = new Date(dateString);
  if (includeTime) {
      return date.toLocaleString('vi-VN', { hour: '2-digit', minute:'2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const getStatusLabel = (status) => statuses.find(s => s.value === status)?.label || status;

onMounted(() => {
  fetchOrders();

  if (window.Echo) {
    window.Echo.private('admin-notifications')
      .listen('.OrderCreatedAdmin', (event) => {
        toast.info(`🛒 Có đơn hàng mới: ${event.order_code}`);
        if (pagination.value.current_page === 1 && (!currentStatus.value || currentStatus.value === 'pending')) {
            orders.value.unshift({ 
                ...event, 
                order_id: event.order_id,
                is_new: true 
            });
            if (orders.value.length > 15) orders.value.pop();
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
  <div class="orders-page">
    <!-- Page Header -->
    <div class="page-header animate-in">
        <div class="header-info">
            <h1 class="page-title">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--ocean-blue)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="16" y1="4" x2="16" y2="20" />
                    <line x1="8" y1="4" x2="8" y2="20" />
                    <line x1="3" y1="8" x2="21" y2="8" />
                    <line x1="3" y1="16" x2="21" y2="16" />
                </svg>
                Quản Lý Đơn Hàng
            </h1>
            <p class="page-subtitle">Theo dõi và cập nhật trạng thái các đơn đặt hàng</p>
        </div>
        <div class="header-btns">
            <!-- Tương lai có thể thêm nút Export CSV ở đây -->
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="filters-bar ocean-card animate-in" style="animation-delay: 0.1s">
        <div class="search-date-wrap">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input 
                    type="text" 
                    v-model="searchQuery"
                    @keyup.enter="handleSearch"
                    placeholder="Tìm mã đơn, tên khách, SĐT..." 
                    class="search-input"
                />
            </div>
            
            <div class="date-picker-box">
                <span class="date-lbl">Từ</span>
                <input type="date" v-model="dateFrom" class="date-input" @change="handleSearch" />
                <span class="date-lbl">Đến</span>
                <input type="date" v-model="dateTo" class="date-input" @change="handleSearch" />
            </div>

            <button v-if="searchQuery || dateFrom || dateTo || currentStatus" class="btn-clear-filters" @click="handleClearFilters" title="Xóa bộ lọc">
                ❌
            </button>
        </div>

        <div class="filter-actions mt-2">
            <button class="filter-btn" :class="{ active: !currentStatus }" @click="handleFilterStatus('')">Tất cả</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'pending' }" @click="handleFilterStatus('pending')">Chờ duyệt</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'confirmed' }" @click="handleFilterStatus('confirmed')">Đã duyệt</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'packing' }" @click="handleFilterStatus('packing')">Đóng gói</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'shipping' }" @click="handleFilterStatus('shipping')">Đang giao</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'delivered' }" @click="handleFilterStatus('delivered')">Đã giao</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'completed' }" @click="handleFilterStatus('completed')">Hoàn thành</button>
            <button class="filter-btn" :class="{ active: currentStatus === 'cancelled' }" @click="handleFilterStatus('cancelled')">Đã hủy</button>
        </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Đang tải đơn hàng...</p>
    </div>

    <!-- Table -->
    <div v-else class="table-container ocean-card animate-in" style="animation-delay: 0.2s">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã đơn & Ngày đặt</th>
                        <th>Khách hàng</th>
                        <th>Tổng tiền</th>
                        <th>Trạng thái (Fulfillment)</th>
                        <th>Thanh toán</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="orders.length === 0">
                        <td colspan="6">
                            <div class="empty-state">
                                <span class="empty-emoji">📦</span>
                                <h3>Không tìm thấy đơn hàng</h3>
                                <p>Thử tìm kiếm với từ khóa hoặc ngày khác.</p>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="order in orders" :key="order.order_id" :class="{'is-new-order': order.is_new}">
                        <td>
                            <div class="order-code-cell">
                                <span class="badge-id">#{{ order.order_code }}</span>
                                <span class="order-date">{{ formatDate(order.created_at) }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="customer-cell">
                                <span class="cus-name">{{ order.recipient_name }}</span>
                                <span class="cus-phone">{{ order.recipient_phone }}</span>
                            </div>
                        </td>
                        <td>
                            <span class="val-price">{{ formatPrice(order.grand_total) }}</span>
                        </td>
                        <td>
                            <!-- Chỉnh CSS cho đẹp như badge, dùng thẻ select nhưng style sang chảnh -->
                            <div class="status-select-wrap" :class="'f-'+order.fulfillment_status">
                                <select class="status-select" v-model="order.fulfillment_status" @change="updateOrderFulfillment(order)" :disabled="order.fulfillment_status === 'completed' || order.fulfillment_status === 'cancelled'">
                                    <option v-for="s in getAllowedFulfillmentOptions(order._prevFulfillmentStatus || order.fulfillment_status)" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <div class="status-select-wrap" :class="'p-'+order.payment_status">
                                <select class="status-select" v-model="order.payment_status" @change="updateOrderPayment(order)">
                                    <option value="unpaid">Chưa TT</option>
                                    <option value="paid">Đã TT</option>
                                    <option value="failed">Thất bại</option>
                                    <option value="refunded">Hoàn tiền</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <router-link :to="{ name: 'admin-order-detail', params: { id: order.order_id } }" class="btn-icon view" title="Chi tiết">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </router-link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination">
            <button class="page-btn" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">‹</button>
            <button
                v-for="page in pagination.last_page"
                :key="page"
                class="page-btn"
                :class="{ active: page === pagination.current_page }"
                @click="changePage(page)"
            >{{ page }}</button>
            <button class="page-btn" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">›</button>
        </div>
    </div>
  </div>
</template>

<style scoped>
.orders-page { font-family: var(--font-inter); }

/* Header */
.page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
}
.page-title {
    font-size: 1.5rem; font-weight: 800; color: var(--text-main);
    display: flex; align-items: center; gap: 12px; margin: 0;
}
.page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; font-weight: 500; margin-bottom: 0;}

/* Filters */
.filters-bar {
    padding: 18px 20px; margin-bottom: 24px;
}
.search-date-wrap {
    display: flex; align-items: center; justify-content: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 14px;
}
.search-box {
    display: flex; align-items: center; gap: 10px;
    background: var(--ocean-deepest); border: 1px solid var(--border-color);
    border-radius: 8px; padding: 10px 16px; min-width: 320px;
    transition: border-color 0.2s;
}
.search-box:focus-within { border-color: var(--ocean-blue); background: white; box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1); }
.search-box svg { color: var(--text-light); }
.search-input { background: none; border: none; outline: none; color: var(--text-main); font-family: var(--font-inter); font-size: 0.9rem; width: 100%; }
.search-input::placeholder { color: var(--text-light); }

.date-picker-box {
    display: flex; align-items: center; gap: 8px; background: white; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-color);
}
.date-lbl { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
.date-input {
    border: none; outline: none; background: transparent; font-family: var(--font-inter); font-size: 0.85rem; color: var(--text-main); cursor: pointer;
}
.btn-clear-filters {
    background: none; border: none; font-size: 1.1rem; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 4px; transition: background 0.2s;
}
.btn-clear-filters:hover { background: #fef2f2; }

.filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.filter-btn {
    padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    font-family: var(--font-inter); font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
}
.filter-btn:hover { border-color: var(--ocean-blue); color: var(--ocean-blue); }
.filter-btn.active { background: rgba(2, 136, 209, 0.1); border-color: rgba(2, 136, 209, 0.3); color: var(--ocean-blue); }

/* Loading */
.loading-state { text-align: center; padding: 60px 20px; color: var(--text-muted); font-weight: 600; }
.spinner { width: 30px; height: 30px; border: 3px solid var(--border-color); border-top-color: var(--ocean-blue); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Table */
.table-wrapper { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; text-align: left; }
.data-table th {
    padding: 14px 24px; font-size: 0.72rem; font-weight: 700;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid var(--border-color); background: var(--ocean-deepest);
}
.data-table td { padding: 16px 24px; border-bottom: 1px solid var(--border-color); transition: background 0.15s; vertical-align: middle; }
.data-table tbody tr:hover td { background: var(--hover-bg); }
.is-new-order td { background: rgba(251, 191, 36, 0.05); }

.order-code-cell { display: flex; flex-direction: column; gap: 4px; align-items: flex-start;}
.badge-id { padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; background: rgba(2, 136, 209, 0.1); color: var(--ocean-blue); }
.order-date { font-size: 0.75rem; color: var(--text-muted); }

.customer-cell { display: flex; flex-direction: column; gap: 2px; }
.cus-name { font-size: 0.95rem; font-weight: 700; color: var(--text-main); }
.cus-phone { font-size: 0.8rem; color: var(--text-light); }

.val-price { font-size: 0.95rem; font-weight: 800; color: var(--coral); }

/* Select Badges */
.status-select-wrap {
    display: inline-block; border-radius: 6px; padding: 2px;
}
.status-select {
    border: none; background: transparent; font-family: var(--font-inter); font-size: 0.8rem; font-weight: 700; padding: 4px 24px 4px 8px; cursor: pointer; outline: none; appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat; background-position: right 6px center; background-size: 14px;
}
.status-select-wrap:focus-within { box-shadow: 0 0 0 2px rgba(0,0,0,0.1); }

/* Colors for Fulfillment */
.f-pending { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.f-confirmed { background: rgba(3, 169, 244, 0.15); color: #0288d1; }
.f-packing, .f-shipping { background: rgba(0, 188, 212, 0.15); color: #0097a7; }
.f-delivered, .f-completed { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.f-cancelled { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.status-select-wrap select { color: inherit; }

/* Colors for Payment */
.p-unpaid { background: rgba(255, 167, 38, 0.15); color: #e65100; }
.p-paid { background: rgba(38, 166, 154, 0.15); color: #167a70; }
.p-failed { background: rgba(239, 83, 80, 0.15); color: #c62828; }
.p-refunded { background: rgba(158, 158, 158, 0.15); color: #616161; }

.actions-cell { display: flex; gap: 6px; }
.btn-icon {
    width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--ocean-deepest); color: var(--text-muted);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; text-decoration: none;
}
.btn-icon:hover { border-color: currentColor; background: white;}
.view:hover { color: #8e24aa; border-color: #8e24aa; background: rgba(142, 36, 170, 0.05); }

/* Empty state */
.empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
.empty-emoji { font-size: 3rem; display: block; margin-bottom: 16px; }
.empty-state h3 { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; }

/* Pagination */
.pagination {
    display: flex; justify-content: center; align-items: center; gap: 8px; padding: 20px;
    border-top: 1px solid var(--border-color);
}
.page-btn {
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; border: 1px solid var(--border-color); background: white;
    font-weight: 600; color: var(--text-muted); cursor: pointer; transition: all 0.2s; font-family: var(--font-inter);
}
.page-btn:hover:not(:disabled) { border-color: var(--ocean-blue); color: var(--ocean-blue); }
.page-btn.active { background: var(--ocean-blue); color: white; border-color: var(--ocean-blue); }
.page-btn:disabled { opacity: 0.5; cursor: not-allowed; background: var(--ocean-deepest); }
</style>
