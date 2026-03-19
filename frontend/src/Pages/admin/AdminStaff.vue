<template>
  <div class="admin-staff animate-in">
    <div class="page-header">
      <div class="header-left">
        <h2 class="section-title">👤 Quản lý Nhân sự</h2>
        <p class="section-desc">Quản lý tài khoản và phân quyền cho đội ngũ quản trị viên.</p>
      </div>
      <button @click="openCreateModal" class="btn-create">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Thêm nhân sự
      </button>
    </div>

    <!-- Search -->
    <div class="search-bar ocean-card">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input v-model="searchQuery" @input="debouncedFetch" type="text" placeholder="Tìm kiếm theo tên hoặc email..." class="search-input" />
      <span class="user-count">{{ staff.length }} nhân sự</span>
    </div>

    <!-- Table -->
    <div class="ocean-card table-wrapper">
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Họ tên</th>
            <th>Email</th>
            <th>Vai trò</th>
            <th>Ngày tạo</th>
            <th class="actions-th">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in staff" :key="member.admin_id" class="user-row">
            <td class="id-cell">#{{ member.admin_id }}</td>
            <td>
              <div class="user-info-cell">
                <div class="avatar-circle avatar-admin">{{ (member.full_name || '?')[0].toUpperCase() }}</div>
                <span>{{ member.full_name || '—' }}</span>
              </div>
            </td>
            <td class="email-cell">{{ member.email }}</td>
            <td>
              <select 
                :value="member.role" 
                @change="updateRole(member.admin_id, $event.target.value)"
                class="role-select"
                :class="'role-' + member.role"
              >
                <option value="admin">Quản trị viên</option>
                <option value="staff">Nhân viên</option>
                <option value="seller">Người bán</option>
              </select>
            </td>
            <td class="date-cell">{{ formatDate(member.created_at) }}</td>
            <td class="actions-cell">
              <button @click="confirmDelete(member)" class="btn-delete" title="Xóa nhân sự">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>
              </button>
            </td>
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

const staff = ref([]);
const loading = ref(true);
const searchQuery = ref('');
let searchTimer = null;

const debouncedFetch = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchStaff();
    }, 500);
};

const fetchStaff = async () => {
  try {
    loading.value = true;
    const response = await api.get('/admin/staff', { params: { search: searchQuery.value } });
    staff.value = response.data.data;
  } catch (error) {
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Lỗi tải danh sách nhân sự!', showConfirmButton: false, timer: 2000 });
  } finally {
    loading.value = false;
  }
};

const updateRole = async (adminId, newRole) => {
  try {
    const result = await api.put(`/admin/staff/${adminId}/role`, { role: newRole });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: result.data.message, showConfirmButton: false, timer: 2000, timerProgressBar: true });
    fetchStaff();
  } catch (error) {
    const msg = error.response?.data?.message || 'Lỗi cập nhật role!';
    Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 2000 });
    fetchStaff();
  }
};

const openCreateModal = async () => {
  const { value: formValues } = await Swal.fire({
    title: 'Thêm nhân sự mới',
    html:
      '<input id="swal-input1" class="swal2-input" placeholder="Họ tên">' +
      '<input id="swal-input2" type="email" class="swal2-input" placeholder="Email">' +
      '<input id="swal-input3" type="password" class="swal2-input" placeholder="Mật khẩu">' +
      '<select id="swal-input4" class="swal2-input">' +
        '<option value="staff">Nhân viên (Staff)</option>' +
        '<option value="admin">Quản trị viên (Admin)</option>' +
        '<option value="seller">Người bán (Seller)</option>' +
      '</select>',
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Tạo tài khoản',
    cancelButtonText: 'Hủy',
    preConfirm: () => {
      const name = document.getElementById('swal-input1').value;
      const email = document.getElementById('swal-input2').value;
      const pass = document.getElementById('swal-input3').value;
      const role = document.getElementById('swal-input4').value;
      if (!name || !email || !pass) {
        Swal.showValidationMessage('Vui lòng nhập đầy đủ Họ tên, Email và Mật khẩu');
        return false;
      }
      return [name, email, pass, role];
    }
  });

  if (formValues) {
    const [full_name, email, password, role] = formValues;
    try {
      await api.post('/admin/staff', { full_name, email, password, role });
      Swal.fire('Thành công', 'Đã tạo nhân sự mới!', 'success');
      fetchStaff();
    } catch (error) {
      Swal.fire('Lỗi', error.response?.data?.message || 'Không thể tạo nhân sự', 'error');
    }
  }
};

const confirmDelete = async (member) => {
  const result = await Swal.fire({
    title: 'Xóa nhân sự?',
    text: `Bạn có chắc chắn muốn xóa tài khoản ${member.full_name}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Đồng ý xóa',
    cancelButtonText: 'Hủy'
  });

  if (result.isConfirmed) {
    try {
      await api.delete(`/admin/staff/${member.admin_id}`);
      Swal.fire('Đã xóa!', 'Tài khoản đã được gỡ khỏi hệ thống.', 'success');
      fetchStaff();
    } catch (error) {
      Swal.fire('Lỗi', error.response?.data?.message || 'Không thể xóa nhân sự', 'error');
    }
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  return new Date(dateStr).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

onMounted(fetchStaff);
</script>

<style scoped>
.page-header { 
  display: flex; 
  justify-content: space-between; 
  align-items: flex-start;
  margin-bottom: 24px; 
}
.btn-create {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #1d4ed8;
  color: white;
  border: none;
  padding: 10px 18px;
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 12px rgba(29, 78, 216, 0.2);
}
.btn-create:hover {
  background: #1e40af;
  transform: translateY(-2px);
}
.section-title { font-size: 1.4rem; font-weight: 700; color: #1a1a1a; }
.section-desc { font-size: 0.85rem; color: #64748b; margin-top: 4px; }

.search-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; margin-bottom: 16px;
  background: #fff; border-radius: 12px; border: 1px solid #eee;
}
.search-bar svg { color: #94a3b8; flex-shrink: 0; }
.search-input {
  flex: 1; background: transparent; border: none; outline: none;
  font-size: 0.9rem; color: #1e293b;
}
.user-count { font-size: 0.75rem; color: #64748b; white-space: nowrap; }

.table-wrapper { 
  background: #fff; border-radius: 12px; border: 1px solid #eee;
  overflow-x: auto; 
}
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
  color: white;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
}
.avatar-admin { background: #d32f2f; }

.role-select {
  padding: 4px 8px; border-radius: 6px; border: 1px solid #eee;
  font-size: 0.8rem; font-weight: 500; cursor: pointer;
  background: white; outline: none;
}
.role-admin { color: #d32f2f; border-color: #ffcdd2; background: #ffebee; }
.role-staff { color: #1565c0; border-color: #bbdefb; background: #e3f2fd; }
.role-seller { color: #ef6c00; border-color: #ffe0b2; background: #fff3e0; }

.actions-th { text-align: center !important; }
.actions-cell { text-align: center; }
.btn-delete {
  background: #fee2e2; color: #dc2626; border: none;
  width: 32px; height: 32px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s;
}
.btn-delete:hover { background: #fecaca; color: #b91c1c; }

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
