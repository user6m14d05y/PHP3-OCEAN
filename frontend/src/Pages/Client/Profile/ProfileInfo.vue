<template>
  <div class="profile-info">
    <div class="section-header">
      <h1 class="section-title">Thông tin tài khoản</h1>
      <p class="section-desc">Quản lý thông tin cá nhân của bạn</p>
    </div>

    <div class="info-card">
      <div class="info-row">
        <div class="info-label">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Họ và tên
        </div>
        <div class="info-value">{{ user.full_name || '—' }}</div>
      </div>

      <div class="info-divider"></div>

      <div class="info-row">
        <div class="info-label">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Email
        </div>
        <div class="info-value">{{ user.email || '—' }}</div>
      </div>

      <div class="info-divider"></div>

      <div class="info-row">
        <div class="info-label">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
          Số điện thoại
        </div>
        <div class="info-value">{{ user.phone || 'Chưa cập nhật' }}</div>
      </div>

      <div class="info-divider"></div>

      <div class="info-row">
        <div class="info-label">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Ngày tham gia
        </div>
        <div class="info-value">{{ formatDate(user.created_at) }}</div>
      </div>

      <div class="info-divider"></div>

      <div class="info-row">
        <div class="info-label">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Trạng thái
        </div>
        <div class="info-value">
          <span class="status-badge" :class="'status-badge--' + (user.status || 'active')">
            {{ user.status === 'active' ? 'Đang hoạt động' : user.status === 'inactive' ? 'Tạm khóa' : 'Bị cấm' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-icon stat-icon--blue">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">0</span>
          <span class="stat-label">Đơn hàng</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--pink">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">0</span>
          <span class="stat-label">Yêu thích</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div class="stat-info">
          <span class="stat-number">{{ addressCount }}</span>
          <span class="stat-label">Địa chỉ</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/axios';

const user = ref({});
const addressCount = ref(0);

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

onMounted(async () => {
  // Lấy thông tin từ localStorage trước
  const userData = localStorage.getItem('user');
  if (userData) {
    try {
      user.value = JSON.parse(userData);
    } catch (e) { /* ignore */ }
  }

  // Lấy thông tin mới nhất từ API
  try {
    const res = await api.get('/me');
    if (res.data) {
      user.value = res.data;
    }
  } catch (e) {
    console.error('Lỗi khi tải thông tin user:', e);
  }

  // Lấy số lượng địa chỉ
  try {
    const res = await api.get('/profile/addresses');
    addressCount.value = res.data?.data?.length || 0;
  } catch (e) { /* ignore */ }
});
</script>

<style scoped>
.profile-info {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header {
  margin-bottom: 4px;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.section-desc {
  font-size: 0.9rem;
  color: #6b7280;
  margin: 4px 0 0;
}

/* Info Card */
.info-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 8px 0;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
}

.info-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
  font-weight: 500;
  color: #6b7280;
}

.info-label svg {
  color: #9ca3af;
}

.info-value {
  font-size: 0.925rem;
  font-weight: 600;
  color: #111827;
}

.info-divider {
  height: 1px;
  background: #f3f4f6;
  margin: 0 24px;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
}

.status-badge--active {
  background: #ecfdf5;
  color: #059669;
}

.status-badge--inactive {
  background: #fefce8;
  color: #ca8a04;
}

.status-badge--banned {
  background: #fef2f2;
  color: #dc2626;
}

/* Quick Stats */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}

.stat-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s;
}

.stat-card:hover {
  border-color: #c7d2fe;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon--blue {
  background: #eff6ff;
  color: #2563eb;
}

.stat-icon--pink {
  background: #fdf2f8;
  color: #ec4899;
}

.stat-icon--green {
  background: #ecfdf5;
  color: #059669;
}

.stat-info {
  display: flex;
  flex-direction: column;
}

.stat-number {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  line-height: 1;
}

.stat-label {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 4px;
}

@media (max-width: 640px) {
  .quick-stats {
    grid-template-columns: 1fr;
  }

  .info-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }
}
</style>
