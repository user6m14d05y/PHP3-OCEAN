<template>
  <header class="site-header">
    <div class="header-inner">
      <!-- Logo -->
      <router-link to="/" class="logo">
        <span class="logo-text">Ocean Store</span>
      </router-link>

      <!-- Danh mục -->
      <button class="category-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Danh mục
      </button>

      <!-- Search -->
      <div class="search-box">
        <input type="text" placeholder="Tìm kiếm sản phẩm, thương hiệu..." class="search-input" />
        <button class="search-submit">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </div>

      <!-- Right icons -->
      <div class="header-actions">
        <!-- Săn Voucher -->
        <a href="#" class="action-item voucher-item">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 00-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>
          </svg>
          <span class="action-label" style="color: #dc2626;">Săn Voucher</span>
        </a>

        <!-- Giỏ hàng -->
        <router-link to="#" class="action-item">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
          <span class="action-label">Giỏ hàng</span>
        </router-link>

        <!-- Tài khoản -->
        <div class="account-dropdown" @mouseenter="showDropdown = true" @mouseleave="showDropdown = false">
          <button class="action-item">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="action-label">Tài khoản</span>
          </button>
          <div class="dropdown-menu" v-show="showDropdown">
            <div class="dropdown-menu-inner">
            <template v-if="isLoggedIn">
              <div class="dropdown-user">
                <div class="dropdown-avatar">{{ (userName || '?')[0].toUpperCase() }}</div>
                <div>
                  <div class="dropdown-name">{{ userName }}</div>
                  <div class="dropdown-email">{{ userEmail }}</div>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <router-link v-if="isAdmin" to="/admin" class="dropdown-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Quản trị
              </router-link>
              <button @click="handleLogout" class="dropdown-item dropdown-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Đăng xuất
              </button>
            </template>
            <template v-else>
              <router-link to="/client/login" class="dropdown-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Đăng nhập
              </router-link>
              <router-link to="/client/register" class="dropdown-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Đăng ký
              </router-link>
            </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../axios.js';

const router = useRouter();
const route = useRoute();

const isLoggedIn = ref(false);
const userName = ref('');
const userEmail = ref('');
const isAdmin = ref(false);
const showDropdown = ref(false);

const checkAuth = () => {
  const userData = localStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      isLoggedIn.value = true;
      userName.value = user.name || user.email;
      userEmail.value = user.email || '';
      isAdmin.value = user.role === 'admin' || user.role === 'staff';
    } catch (e) {
      isLoggedIn.value = false;
    }
  } else {
    isLoggedIn.value = false;
    userName.value = '';
    userEmail.value = '';
    isAdmin.value = false;
  }
};

const handleLogout = async () => {
  try { await api.post('/logout'); } catch (e) { /* ignore */ }
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  isLoggedIn.value = false;
  showDropdown.value = false;
  
  // Ở lại trang hiện tại nếu không phải trang cần auth, 
  // nhưng thực tế trang hiện tại có thể yêu cầu auth nên router guard sẽ tự xử lý.
  // Người dùng yêu cầu: "khi ấn đăng xuất thì vẫn ở lại trang đó chứ ko trả về trang login"
  window.location.reload(); 
};

onMounted(checkAuth);
watch(() => route.path, checkAuth);
</script>

<style scoped>
.site-header {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-inner {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px;
  height: 80px;
  display: flex;
  align-items: center;
  gap: 20px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  flex-shrink: 0;
}

.logo-text {
  font-size: 1.45rem;
  font-weight: 800;
  color: #1a56db;
  letter-spacing: -0.3px;
}

.category-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font-size: 0.95rem;
  font-weight: 500;
  color: #333;
  cursor: pointer;
  font-family: inherit;
  flex-shrink: 0;
  transition: background 0.15s;
}

.category-btn:hover { background: #f9fafb; }

.search-box {
  flex: 1;
  display: flex;
  border: 1.5px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  transition: border-color 0.2s;
}

.search-box:focus-within { border-color: #1a56db; }

.search-input {
  flex: 1;
  padding: 10px 16px;
  border: none;
  outline: none;
  font-size: 0.95rem;
  font-family: inherit;
  color: #333;
  background: transparent;
}

.search-input::placeholder { color: #9ca3af; }

.search-submit {
  padding: 8px 16px;
  background: #1a56db;
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: background 0.2s;
}

.search-submit:hover { background: #1648b8; }

.header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
}

.action-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  color: #4b5563;
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  font-family: inherit;
  transition: color 0.2s;
  padding: 4px 2px;
}

.action-item:hover { color: #1a56db; }

.action-label {
  font-size: 0.75rem;
  font-weight: 500;
  white-space: nowrap;
}

.voucher-item svg { color: #dc2626; }
.voucher-item:hover svg { color: #b91c1c; }

/* Dropdown */
.account-dropdown { position: relative; }

.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  padding-top: 8px;
  min-width: 220px;
  z-index: 200;
}

.dropdown-menu-inner {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 8px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

.dropdown-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
}

.dropdown-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: #1a56db; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}

.dropdown-name { font-size: 0.85rem; font-weight: 600; color: #111; }
.dropdown-email { font-size: 0.75rem; color: #888; }
.dropdown-divider { height: 1px; background: #e5e7eb; margin: 4px 0; }

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  color: #333;
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  width: 100%;
  font-family: inherit;
  transition: background 0.15s;
}

.dropdown-item:hover { background: #f3f4f6; }
.dropdown-logout { color: #dc2626; }
.dropdown-logout:hover { background: #fff0f0; }

@media (max-width: 768px) {
  .search-box { display: none; }
  .category-btn { display: none; }
}
</style>
