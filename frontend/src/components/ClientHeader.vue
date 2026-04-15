<template>
  <header class="site-header">
    <div class="header-inner">
      <!-- Logo -->
      <router-link to="/" class="logo">
        <img :src="BASE_URL + '/storage/logo/logo_OceanShop.png'" alt="Logo" class="logo-img" width="70px">
      </router-link>
      <!-- Danh mục Mega Dropdown -->
      <div class="category-dropdown" @mouseenter="showCategoryMenu = true" @mouseleave="showCategoryMenu = false">
        <button class="category-btn" :class="{ 'is-open': showCategoryMenu }">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Danh mục
        </button>

        <div class="mega-menu" v-show="showCategoryMenu">
          <div class="mega-menu-inner">
            <!-- Cột trái: Sidebar danh mục -->
            <div class="mega-sidebar">
              <div class="mega-sidebar-scroll">
                <!-- Danh mục động -->
                <router-link
                  v-for="cat in categories"
                  :key="cat.category_id"
                  :to="'/product?category=' + cat.category_id"
                  class="mega-sidebar-item"
                  :class="{ active: hoveredCategory === cat.category_id }"
                  @mouseenter="hoveredCategory = cat.category_id"
                  @click="showCategoryMenu = false"
                >
                  <span class="mega-sidebar-label">{{ cat.name }}</span>
                  <svg v-if="cat.children && cat.children.length" class="mega-sidebar-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </router-link>

                <!-- Divider -->
                <div class="mega-sidebar-divider"></div>

                <!-- Links tĩnh -->
                <router-link to="/product" class="mega-sidebar-item mega-static-link" @click="showCategoryMenu = false">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                  <span class="mega-sidebar-label">Tất cả sản phẩm</span>
                </router-link>
                <router-link to="/contact" class="mega-sidebar-item mega-static-link" @click="showCategoryMenu = false">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                  <span class="mega-sidebar-label">Liên hệ</span>
                </router-link>
                <router-link to="/about" class="mega-sidebar-item mega-static-link" @click="showCategoryMenu = false">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                  <span class="mega-sidebar-label">Giới thiệu</span>
                </router-link>
              </div>
            </div>

            <!-- Cột phải: Danh mục con multi-column -->
            <div class="mega-content">
              <template v-for="cat in categories" :key="cat.category_id">
                <div v-if="hoveredCategory === cat.category_id" class="mega-content-body">
                  <div v-if="cat.children && cat.children.length" class="mega-columns">
                    <div v-for="child in cat.children" :key="child.category_id" class="mega-column">
                      <router-link :to="'/product?category=' + child.category_id" class="mega-column-title" @click="showCategoryMenu = false">
                        {{ child.name }}
                      </router-link>
                      <template v-if="child.children && child.children.length">
                        <router-link
                          v-for="sub in child.children"
                          :key="sub.category_id"
                          :to="'/product?category=' + sub.category_id"
                          class="mega-column-item"
                          @click="showCategoryMenu = false"
                        >
                          {{ sub.name }}
                        </router-link>
                      </template>
                      <router-link :to="'/product?category=' + child.category_id" class="mega-column-viewall" @click="showCategoryMenu = false">
                        Xem tất cả ›
                      </router-link>
                    </div>
                  </div>
                  <div v-else class="mega-empty">
                    <p>Chưa có danh mục con</p>
                    <router-link :to="'/product?category=' + cat.category_id" class="mega-column-viewall" @click="showCategoryMenu = false">
                      Xem tất cả sản phẩm ›
                    </router-link>
                  </div>
                </div>
              </template>
              <div v-if="hoveredCategory === null" class="mega-content-body">
                <div class="mega-welcome">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#c7d2fe" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                  <p>Di chuột vào danh mục để xem chi tiết</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Search Trigger — mở SearchModal (Vue InstantSearch + Meilisearch) -->
      <button id="headerSearchTrigger" class="search-trigger-btn" @click="showSearch = true" title="Tìm kiếm (Ctrl+K)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span class="search-trigger-placeholder">Tìm kiếm sản phẩm...</span>
        <kbd class="search-trigger-kbd">Ctrl K</kbd>
      </button>

      <!-- Search Modal -->
      <SearchModal v-model="showSearch" />

      <!-- Right icons -->
      <div class="header-actions">
        <!-- Flash Sale Link -->
        <router-link
          v-if="hasActiveFlashSale"
          to="/flash-sale"
          class="action-item flash-sale-link"
          title="Flash Sale đang diễn ra!"
        >
          <span class="flash-sale-icon">⚡</span>
          <span class="action-label flash-sale-label">FLASH SALE</span>
        </router-link>

        <!-- Săn Voucher -->
        <div class="account-dropdown" @mouseenter="showVoucherDropdown = true" @mouseleave="showVoucherDropdown = false">
          <router-link to="/coupon" class="action-item voucher-item">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 12V8H6a2 2 0 01-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12c0 1.1.9 2 2 2h14v-4"/><path d="M18 12a2 2 0 00-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>
            </svg>
            <span class="action-label" style="color: var(--coral);">Săn Voucher</span>
          </router-link>
          
          <div class="account-menu" v-show="showVoucherDropdown">
            <div class="account-menu-inner voucher-menu">
              <div class="dropdown-header">
                <span class="dropdown-title">Mã giảm giá mới nhất</span>
                <router-link to="/coupon" class="view-all">Tất cả</router-link>
              </div>
              <div class="dropdown-divider"></div>
              <div v-if="publicCoupons.length === 0" class="empty-voucher">
                Không có voucher hot nào
              </div>
              <div v-else class="voucher-list">
                <div v-for="cp in publicCoupons.slice(0, 4)" :key="cp.id" class="voucher-mini-card">
                  <div class="cp-code">{{ cp.code }}</div>
                  <div class="cp-info">
                    {{ cp.type === 'percent' ? `Giảm ${cp.value}%` : `Giảm ${formatCurrency(cp.value)}` }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Giỏ hàng -->
        <router-link to="/cart" class="action-item cart-action">
          <div class="cart-icon-wrapper">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <span v-if="cartCount > 0" class="cart-badge">{{ cartCount > 99 ? '99+' : cartCount }}</span>
          </div>
          <span class="action-label">Giỏ hàng</span>
        </router-link>

        <!-- Tài khoản -->
        <div class="account-dropdown" @mouseenter="showDropdown = true" @mouseleave="showDropdown = false">
          <button class="action-item">
            <template v-if="isLoggedIn">
              <img v-if="userAvatar" :src="userAvatar" class="header-user-avatar" />
              <div v-else class="header-user-avatar-fallback">{{ (userName || '?')[0].toUpperCase() }}</div>
              <span class="action-label logged-in-name">{{ userName }}</span>
            </template>
            <template v-else>
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span class="action-label">Tài khoản</span>
            </template>
          </button>
          <div class="account-menu" v-show="showDropdown">
            <div class="account-menu-inner">
            <template v-if="isLoggedIn">
              <div class="dropdown-user">
                <img v-if="userAvatar" :src="userAvatar" class="dropdown-avatar-img" />
                <div v-else class="dropdown-avatar">{{ (userName || '?')[0].toUpperCase() }}</div>
                <div class="dropdown-user-text">
                  <div class="dropdown-name">{{ userName }}</div>
                  <div class="dropdown-email">{{ userEmail }}</div>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <router-link to="/profile" class="account-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Tài khoản của tôi
              </router-link>
              <router-link v-if="isAdmin" to="/admin" class="account-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Quản trị
              </router-link>
              <button @click="handleLogout" class="account-menu-item account-logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Đăng xuất
              </button>
            </template>
            <template v-else>
              <router-link to="/client/login" class="account-menu-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Đăng nhập
              </router-link>
              <router-link to="/client/register" class="account-menu-item">
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
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../axios.js';
import { broadcastLogout } from '../sessionSync.js';
import SearchModal from './SearchModal.vue';

const BASE_URL = import.meta.env.VITE_BASE_URL;
const route = useRoute();
const router = useRouter();

const isLoggedIn = ref(false);
const userName = ref('');
const userEmail = ref('');
const userAvatar = ref(null);
const isAdmin = ref(false);
const showDropdown = ref(false);
const showVoucherDropdown = ref(false);
const showCategoryMenu = ref(false);
const categories = ref([]);
const publicCoupons = ref([]);
const hoveredCategory = ref(null);
const cartCount = ref(0);
const hasActiveFlashSale = ref(false);

// Search Modal state
const showSearch = ref(false);

// Phím tắt Ctrl+K / Cmd+K để mở modal tìm kiếm
const handleGlobalKeydown = (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    showSearch.value = true;
  }
};

const fetchCategories = async () => {
  try {
    const response = await api.get('/categories');
    categories.value = response.data.data;
  } catch (error) {
    console.error('Error fetching categories:', error);
  }
};

const fetchPublicCoupons = async () => {
  try {
    const response = await api.get('/coupons/public');
    publicCoupons.value = response.data.data;
  } catch (error) {
    console.error('Error fetching vouchers:', error);
  }
};

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};


// Đóng mega menu khi chuyển trang
watch(() => route.path, () => {
  showCategoryMenu.value = false;
});

const checkAuth = () => {
  const userData = sessionStorage.getItem('user');
  if (userData) {
    try {
      const user = JSON.parse(userData);
      isLoggedIn.value = true;
      userName.value = user.full_name || user.name || user.email;
      userEmail.value = user.email || '';
      isAdmin.value = ['admin', 'staff', 'seller'].includes(user.role);
      
      const path = user.avatar_url;
      if (path) {
        const BASE_URL = (import.meta.env.VITE_API_URL || 'http://localhost:8383/api').replace('/api', '');
        userAvatar.value = path.startsWith('http') ? path : `${BASE_URL}${path}`;
      } else {
        userAvatar.value = null;
      }
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

const fetchCartCount = async () => {
  const token = sessionStorage.getItem('auth_token');
  if (!token) { cartCount.value = 0; return; }
  try {
    const response = await api.get('/cart/count');
    cartCount.value = response.data.count || 0;
  } catch (e) { cartCount.value = 0; }
};

const handleLogout = async () => {
  try { await api.post('/logout'); } catch (e) { /* ignore */ }
  // Broadcast logout sang tất cả các tab khác đang mở
  broadcastLogout();
  // Xóa session tab hiện tại
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  localStorage.removeItem('ocean_live_chat_token');
  sessionStorage.removeItem('auth_token');
  sessionStorage.removeItem('user');
  sessionStorage.removeItem('ocean_chatbot_messages');
  sessionStorage.removeItem('ocean_chatbot_history');
  isLoggedIn.value = false;
  showDropdown.value = false;

  window.location.reload();
};

const fetchFlashSaleStatus = async () => {
  try {
    const { data } = await api.get('/flash-sale');
    hasActiveFlashSale.value = (data.data ?? []).some(s => s.status === 'active');
  } catch { hasActiveFlashSale.value = false; }
};

onMounted(() => {
  checkAuth();
  fetchCategories();
  fetchPublicCoupons();
  fetchCartCount();
  fetchFlashSaleStatus();
  window.addEventListener('user-updated', checkAuth);
  window.addEventListener('cart-updated', fetchCartCount);
  document.addEventListener('keydown', handleGlobalKeydown);
});
onUnmounted(() => {
  document.removeEventListener('keydown', handleGlobalKeydown);
});
watch(() => route.path, () => { checkAuth(); fetchCartCount(); });
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
  max-width: 1400px;
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
  color: var(--ocean-blue);
  letter-spacing: -0.3px;
}

/* ====== Category Mega Dropdown ====== */
.category-dropdown { position: relative; }

.category-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 20px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-main);
  cursor: pointer;
  font-family: inherit;
  flex-shrink: 0;
  transition: all 0.2s;
}

.category-btn:hover,
.category-btn.is-open {
  background: var(--ocean-blue);
  color: #fff;
  border-color: var(--ocean-blue);
}
.category-btn:hover svg,
.category-btn.is-open svg { stroke: #fff; }

.mega-menu {
  position: absolute;
  top: 100%;
  left: 0;
  padding-top: 10px;
  z-index: 300;
}

.mega-menu-inner {
  display: flex;
  background: #fff;
  border: 1px solid #e2e5ea;
  border-radius: 16px;
  box-shadow:
    0 16px 40px rgba(2, 136, 209, 0.08),
    0 4px 12px rgba(2, 136, 209, 0.04);
  overflow: hidden;
  min-height: 360px;
}

.mega-sidebar {
  width: 230px;
  background: #fff;
  border-right: 1px solid #f0f0f0;
  flex-shrink: 0;
}

.mega-sidebar-scroll { padding: 8px 0; }

.mega-sidebar-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 18px;
  margin: 2px 8px;
  border-radius: 10px;
  font-size: 0.93rem;
  font-weight: 500;
  color: #4b5563;
  cursor: pointer;
  transition: all 0.15s ease;
  text-decoration: none;
  gap: 8px;
}

.mega-sidebar-item:hover { background: var(--hover-bg); color: var(--ocean-blue); }
.mega-sidebar-item.active { background: var(--ocean-blue); color: #fff; }
.mega-sidebar-item.active .mega-sidebar-arrow { stroke: #fff; }

.mega-sidebar-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mega-sidebar-arrow { flex-shrink: 0; transition: stroke 0.15s; }
.mega-sidebar-divider { height: 1px; background: #f0f0f0; margin: 6px 16px; }

.mega-static-link { gap: 10px; justify-content: flex-start; }
.mega-static-link svg { flex-shrink: 0; color: #9ca3af; }
.mega-static-link:hover svg { color: var(--ocean-blue); }

.mega-content { flex: 1; min-width: 460px; background: #fafbfc; }
.mega-content-body { padding: 28px 32px; }

.mega-columns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px 36px; }
.mega-column { display: flex; flex-direction: column; gap: 6px; }

.mega-column-title {
  font-size: 1rem; font-weight: 700; color: var(--text-main);
  text-decoration: none; margin-bottom: 4px; transition: color 0.15s;
}
.mega-column-title:hover { color: var(--ocean-blue); }

.mega-column-item {
  font-size: 0.88rem; color: #6b7280; text-decoration: none;
  padding: 3px 0; transition: color 0.15s; font-weight: 450;
}
.mega-column-item:hover { color: var(--ocean-blue); }

.mega-column-viewall {
  font-size: 0.85rem; font-weight: 600; color: var(--ocean-blue);
  text-decoration: none; margin-top: 4px; transition: color 0.15s;
}
.mega-column-viewall:hover { color: rgba(2, 136, 209, 0.8); text-decoration: underline; }

.mega-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 100%; gap: 12px; color: #9ca3af; font-size: 0.95rem;
}

.mega-welcome {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; height: 280px; gap: 16px; color: #b0b8c9; font-size: 0.95rem;
}

/* ── Search Trigger Button ─────────────────────────────────── */
.search-trigger-btn {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s ease;
  color: #9ca3af;
  text-align: left;
}
.search-trigger-btn:hover {
  border-color: var(--ocean-blue);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
  color: #627d98;
}
.search-trigger-btn svg { flex-shrink: 0; color: #9ca3af; }
.search-trigger-btn:hover svg { color: var(--ocean-blue); }
.search-trigger-placeholder {
  flex: 1;
  font-size: 0.93rem;
  color: inherit;
}
.search-trigger-kbd {
  background: #f1f5f9;
  border: 1px solid #d1dce8;
  border-radius: 5px;
  padding: 2px 7px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #94a3b8;
  font-family: inherit;
  white-space: nowrap;
  flex-shrink: 0;
}

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
  color: var(--text-main);
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  font-family: inherit;
  transition: color 0.2s;
  padding: 4px 2px;
}

.action-item:hover { color: var(--ocean-blue); }

.action-label {
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
}

.voucher-item svg { color: var(--coral); }
.voucher-item:hover svg { color: #b91c1c; }

/* Dropdown */
.account-dropdown { position: relative; }

.account-menu {
  position: absolute;
  top: 100%;
  right: 0;
  padding-top: 8px;
  min-width: 220px;
  z-index: 200;
}

.account-menu-inner {
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
  background: var(--ocean-blue); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
}
.dropdown-avatar-img {
  width: 44px; height: 44px; border-radius: 50%;
  object-fit: cover; border: 2px solid #e5e7eb; flex-shrink: 0;
}
.dropdown-user-text { overflow: hidden; }

.header-user-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  object-fit: cover; border: 1.5px solid var(--ocean-blue);
}
.header-user-avatar-fallback {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--ocean-blue); color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem; font-weight: 700;
}
.logged-in-name {
  color: var(--ocean-blue) !important;
  font-weight: 700 !important;
  max-width: 80px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dropdown-name { font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
.dropdown-email { font-size: 0.75rem; color: #888; }
.dropdown-divider { height: 1px; background: #e5e7eb; margin: 4px 0; }

.account-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-main);
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  width: 100%;
  font-family: inherit;
  transition: background 0.15s;
}

.account-menu-item:hover { background: #f3f4f6; }
.account-logout { color: var(--coral); }
.account-logout:hover { background: #fff0f0; }

/* Voucher Dropdown Style */
.voucher-menu { min-width: 280px; }
.dropdown-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; }
.dropdown-title { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
.view-all { font-size: 0.75rem; color: var(--ocean-blue); text-decoration: none; font-weight: 600; }
.view-all:hover { text-decoration: underline; }
.empty-voucher { padding: 20px; text-align: center; font-size: 0.85rem; color: #888; }
.voucher-list { padding: 4px; display: flex; flex-direction: column; gap: 4px; }
.voucher-mini-card { padding: 10px 12px; border-radius: 8px; background: #fff5f5; border: 1px dashed #fecaca; }
.cp-code { font-size: 0.85rem; font-weight: 700; color: var(--coral); margin-bottom: 2px; }
.cp-info { font-size: 0.75rem; color: #666; font-weight: 500; }

/* Cart Badge */
.cart-icon-wrapper {
  position: relative;
  display: inline-flex;
}
.cart-badge {
  position: absolute;
  top: -6px;
  right: -10px;
  background: var(--coral);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  line-height: 1;
  border: 2px solid #fff;
  box-shadow: 0 2px 6px rgba(239, 83, 80, 0.4);
}

@media (max-width: 768px) {
  .search-trigger-btn { display: none; }
  .category-btn { display: none; }
}

/* Flash Sale Header Link */
.flash-sale-link {
  position: relative;
  text-decoration: none !important;
}

.flash-sale-icon {
  font-size: 20px;
  line-height: 1;
  display: block;
  /* Không dùng animation loop */
}

.flash-sale-label {
  font-size: 0.72rem !important;
  font-weight: 900 !important;
  letter-spacing: 0.8px;
  background: linear-gradient(90deg, #ff4500, #ff8c00);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  white-space: nowrap;
}
</style>
