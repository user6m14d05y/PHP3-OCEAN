<template>
  <div class="admin-users animate-in">
    <div class="page-header">
      <h2 class="section-title">👥 Quản lý nhân sự</h2>
      <p class="section-desc">Quản lý tài khoản, phân quyền và trạng thái người dùng.</p>
    </div>

    <!-- Search -->
    <div class="search-bar ocean-card">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input v-model="searchQuery" @input="fetchUsers" type="text" placeholder="Tìm kiếm theo tên hoặc email..." class="search-input" />
      <span class="user-count">{{ users.length }} người dùng</span>
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
          <tr v-if="loading">
            <td colspan="7" class="loading-cell">
              <div class="loading-spinner"></div>
              Đang tải...
            </td>
          </tr>
          <tr v-else-if="users.length === 0">
            <td colspan="7" class="empty-cell">Không tìm thấy người dùng nào.</td>
          </tr>
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
                <option value="admin">Admin</option>
                <option value="staff">Staff</option>
                <option value="customer">Customer</option>
                <option value="seller">Seller</option>
              </select>
            </td>
            <td>
              <select 
                :value="user.status" 
                @change="updateStatus(user.user_id, $event.target.value)"
                class="status-select"
                :class="'status-' + user.status"
              >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="banned">Banned</option>
              </select>
            </td>
            <td class="date-cell">{{ formatDate(user.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../axios.js';
import Swal from 'sweetalert2';

const users = ref([]);
const loading = ref(true);
const searchQuery = ref('');

const fetchUsers = async () => {
  try {
    loading.value = true;
    const response = await api.get('/admin/users', { params: { search: searchQuery.value } });
    users.value = response.data.data;
  } catch (error) {
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Lỗi tải danh sách users!', showConfirmButton: false, timer: 2000 });
  } finally {
    loading.value = false;
  }
};

const updateRole = async (userId, newRole) => {
  try {
    const result = await api.put(`/admin/users/${userId}/role`, { role: newRole });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: result.data.message, showConfirmButton: false, timer: 2000, timerProgressBar: true });
    fetchUsers();
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi cập nhật role!';
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
    fetchUsers(); // Reset dropdown
  }
};

const updateStatus = async (userId, newStatus) => {
  try {
    const result = await api.put(`/admin/users/${userId}/status`, { status: newStatus });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: result.data.message, showConfirmButton: false, timer: 2000, timerProgressBar: true });
    fetchUsers();
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi cập nhật status!';
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
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
.section-title { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
.section-desc { font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; }

.search-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; margin-bottom: 16px;
}
.search-bar svg { color: var(--text-light); flex-shrink: 0; }
.search-input {
  flex: 1; background: transparent; border: none; outline: none;
  font-size: 0.9rem; color: var(--text-main); font-family: var(--font-inter);
}
.search-input::placeholder { color: var(--text-light); }
.user-count { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; }

.table-wrapper { overflow-x: auto; }
.user-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.user-table th {
  text-align: left; padding: 12px 16px; font-weight: 600; font-size: 0.75rem;
  text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--text-muted); border-bottom: 1px solid var(--border-color);
  background: var(--ocean-deepest);
}
.user-table td { padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.user-row:hover { background: var(--hover-bg); }
.id-cell { color: var(--text-light); font-weight: 600; }
.email-cell { color: var(--ocean-blue); }
.date-cell { color: var(--text-muted); font-size: 0.8rem; }

.user-info-cell { display: flex; align-items: center; gap: 10px; }
.avatar-circle {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--ocean-blue); color: white;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
}

.role-select, .status-select {
  padding: 4px 8px; border-radius: 6px; border: 1px solid var(--border-color);
  font-size: 0.8rem; font-weight: 500; cursor: pointer;
  background: white; outline: none; font-family: var(--font-inter);
}
.role-select:focus, .status-select:focus { border-color: var(--ocean-blue); }
.role-admin { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }
.role-staff { color: #1565c0; border-color: #bbdefb; background: #e3f2fd; }
.role-customer { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }
.status-active { color: #2e7d32; border-color: #c8e6c9; background: #e8f5e9; }
.status-inactive { color: #757575; border-color: #e0e0e0; background: #f5f5f5; }
.status-banned { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }

.loading-cell, .empty-cell {
  text-align: center; padding: 40px 16px !important;
  color: var(--text-muted); font-size: 0.9rem;
}
.loading-spinner {
  display: inline-block; width: 20px; height: 20px;
  border: 2px solid var(--border-color); border-top-color: var(--ocean-blue);
  border-radius: 50%; animation: spin 0.8s linear infinite;
  margin-right: 8px; vertical-align: middle;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
