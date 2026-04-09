<template>
  <div class="attendance-list-container p-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0 fw-bold text-gray-800">Quản lý lịch sử Chấm Công</h2>
    </div>

    <!-- Table Card -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">ID</th>
                <th>Nhân sự</th>
                <th>Phân quyền</th>
                <th>Thời gian Check-in</th>
                <th>Thời gian Check-out</th>
                <th>Ghi chú</th>
                <th>IP / Vị trí</th>
              </tr>
            </thead>
            <tbody>
                <tr v-if="loading">
                  <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="attendances.length === 0">
                    <td colspan="7" class="text-center py-4 text-muted">Chưa có dữ liệu lịch sử chấm công.</td>
                </tr>
              <tr v-else v-for="item in attendances" :key="item.id">
                <td class="ps-4 fw-medium text-muted">#{{ item.id }}</td>
                <td>
                  <div class="fw-bold">{{ item.user_name || 'Không xác định' }}</div>
                </td>
                <td>
                   <span class="badge text-bg-info text-capitalize">{{ item.role || 'user' }}</span>
                </td>
                <td>
                  <span class="text-success fw-medium">{{ formatTime(item.check_in_at) }}</span>
                </td>
                <td>
                  <span v-if="item.check_out_at" class="text-danger fw-medium">{{ formatTime(item.check_out_at) }}</span>
                  <span v-else class="badge bg-warning text-dark">Chưa checkout</span>
                </td>
                <td>
                    <span class="text-muted small" style="max-width: 200px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="item.note">
                        {{ item.note || '-' }}
                    </span>
                </td>
                <td>
                    <div class="small fw-medium">{{ item.ip_address || 'N/A' }}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">GPS: {{ Number(item.latitude).toFixed(4) }}, {{ Number(item.longitude).toFixed(4) }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Pagination (nếu có) -->
      <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-top-0" v-if="pagination.last_page > 1">
        <span class="text-muted small">
            Hiển thị {{ pagination.from }} - {{ pagination.to }} trên {{ pagination.total }} nhóm
        </span>
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item" :class="{ disabled: !pagination.prev_page_url }">
            <button class="page-link shadow-none" @click="fetchAttendances(pagination.current_page - 1)">Trước</button>
          </li>
           <li class="page-item" v-for="page in pagination.last_page" :key="page" :class="{ active: pagination.current_page === page }">
              <button class="page-link shadow-none" @click="fetchAttendances(page)">{{ page }}</button>
          </li>
          <li class="page-item" :class="{ disabled: !pagination.next_page_url }">
            <button class="page-link shadow-none" @click="fetchAttendances(pagination.current_page + 1)">Sau</button>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../axios';

const attendances = ref([]);
const loading = ref(true);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
    prev_page_url: null,
    next_page_url: null
});

const formatTime = (dateString) => {
    if(!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleString('vi-VN');
};

const fetchAttendances = async (page = 1) => {
    loading.value = true;
    try {
        const response = await api.get(`/admin/attendance?page=${page}`);
        if(response.data.status === 'success') {
            attendances.value = response.data.data.data; // data.data vì paginate() trả về .data
            pagination.value = {
                current_page: response.data.data.current_page,
                last_page: response.data.data.last_page,
                total: response.data.data.total,
                from: response.data.data.from,
                to: response.data.data.to,
                prev_page_url: response.data.data.prev_page_url,
                next_page_url: response.data.data.next_page_url
            };
        }
    } catch (e) {
        console.error("Lỗi khi tải lịch sử chấm công", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchAttendances();
});
</script>

<style scoped>
.attendance-list-container {
    max-width: 1400px;
    margin: 0 auto;
}
</style>
