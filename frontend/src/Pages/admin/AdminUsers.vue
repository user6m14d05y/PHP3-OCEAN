<template>
  <div class="admin-users animate-in">
    <div class="page-header">
      <h2 class="section-title">👥 Quản lý Khách hàng</h2>
      <p class="section-desc">Quản lý tài khoản khách hàng và người bán hàng trên hệ thống.</p>
    </div>

    <!-- Search -->
    <div class="search-bar ocean-card">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input v-model="searchQuery" @input="debouncedFetch" type="text" placeholder="Tìm kiếm theo tên hoặc email..." class="search-input" />
      <span class="user-count">{{ users.length }} khách hàng</span>
    </div>

    <!-- Table -->
    <div class="ocean-card table-wrapper">
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Họ tên</th>
            <th>Email</th>
            <th>SĐT</th>
            <th>Vai trò</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.user_id" class="user-row">
            <td class="id-cell">#{{ user.user_id }}</td>
            <td>
              <div class="user-info-cell">
                <div class="avatar-circle">{{ (user.full_name || '?')[0].toUpperCase() }}</div>
                <span>{{ user.full_name || '—' }}</span>
              </div>
            </td>
            <td class="email-cell">{{ user.email }}</td>
            <td>{{ user.phone || '—' }}</td>
            <td>
              <select 
                :value="user.role" 
                @change="updateRole(user.user_id, $event.target.value)"
                class="role-select"
                :class="'role-' + user.role"
              >
                <option value="customer">Khách hàng</option>
                <option value="seller">Người bán</option>
              </select>
            </td>
            <td>
              <select 
                :value="user.status" 
                @change="updateStatus(user.user_id, $event.target.value)"
                class="status-select"
                :class="'status-' + user.status"
              >
                <option value="active">Hoạt động</option>
                <option value="inactive">Không hoạt động</option>
                <option value="banned">Bị cấm</option>
              </select>
            </td>
            <td class="date-cell">{{ formatDate(user.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Bootstrap Toast -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080">
      <div class="toast align-items-center border-0" :class="toast.type === 'success' ? 'text-bg-success' : 'text-bg-danger'" id="usersToast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">{{ toast.message }}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Đóng"></button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '../../axios.js';
import { Toast } from 'bootstrap';

const users = ref([]);
const loading = ref(true);
const searchQuery = ref('');
let searchTimer = null;

const toast = ref({ message: '', type: 'success' });

const showToast = (message, type = 'success') => {
  toast.value = { message, type };
  nextTick(() => {
    const el = document.getElementById('usersToast');
    if (el) Toast.getOrCreateInstance(el, { delay: 2500 }).show();
  });
};

const debouncedFetch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchUsers();
    }, 500);
};

const fetchUsers = async () => {
  try {
    loading.value = true;
    const response = await api.get('/admin/users', { params: { search: searchQuery.value } });
    users.value = response.data.data;
  } catch (error) {
    showToast('Lỗi tải danh sách khách hàng!', 'danger');
  } finally {
    loading.value = false;
  }
};

const updateRole = async (userId, newRole) => {
  try {
    const result = await api.put(`/admin/users/${userId}/role`, { role: newRole });
    showToast(result.data.message, 'success');
    fetchUsers();
  } catch (error) {
    showToast(error.response?.data?.message || 'Lỗi cập nhật role!', 'danger');
    fetchUsers();
  }
};

const updateStatus = async (userId, newStatus) => {
  try {
    const result = await api.put(`/admin/users/${userId}/status`, { status: newStatus });
    showToast(result.data.message, 'success');
    fetchUsers();
  } catch (error) {
    showToast(error.response?.data?.message || 'Lỗi cập nhật status!', 'danger');
    fetchUsers();
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

onMounted(fetchUsers);
</script>

<style scoped>
.page-header { margin-bottom: 24px; }
.section-title { font-size: 1.4rem; font-weight: 700; color: #1a1a1a; }
.section-desc { font-size: 0.85rem; color: #64748b; margin-top: 4px; }

.search-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; margin-bottom: 16px;
}
.search-bar svg { color: #94a3b8; flex-shrink: 0; }
.search-input {
  flex: 1; background: transparent; border: none; outline: none;
  font-size: 0.9rem; color: #1e293b;
}
.user-count { font-size: 0.75rem; color: #64748b; white-space: nowrap; }

.table-wrapper { overflow-x: auto; background: #fff; border-radius: 12px; border: 1px solid #eee; }
.user-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.user-table th {
  text-align: left; padding: 12px 16px; font-weight: 600; font-size: 0.75rem;
  text-transform: uppercase; letter-spacing: 0.05em;
  color: #64748b; border-bottom: 1px solid #eee;
  background: #f8fafc;
}
.user-table td { padding: 12px 16px; border-bottom: 1px solid #eee; }
.user-row:hover { background: #f8fafc; }
.id-cell { color: #94a3b8; font-weight: 600; }
.email-cell { color: #1d4ed8; }
.date-cell { color: #64748b; font-size: 0.8rem; }

.user-info-cell { display: flex; align-items: center; gap: 10px; }
.avatar-circle {
  width: 32px; height: 32px; border-radius: 50%;
  background: #1d4ed8; color: white;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
}

.role-select, .status-select {
  padding: 4px 8px; border-radius: 6px; border: 1px solid #eee;
  font-size: 0.8rem; font-weight: 500; cursor: pointer;
  background: white; outline: none;
}
.role-customer { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }
.status-active { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.status-inactive { color: #757575; border-color: #e0e0e0; background: #f5f5f5; }
.status-banned { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }

.loading-cell, .empty-cell {
  text-align: center; padding: 40px 16px !important;
  color: #64748b; font-size: 0.9rem;
}
.loading-spinner {
  display: inline-block; width: 20px; height: 20px;
  border: 2px solid #eee; border-top-color: #1d4ed8;
  border-radius: 50%; animation: spin 0.8s linear infinite;
  margin-right: 8px; vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
