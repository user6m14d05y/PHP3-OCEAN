<template>
  <div class="admin-layout">
    <AdminAside />

    <div class="admin-main">
      <!-- Header -->
      <header class="admin-header">
        <div class="header-left">
          <h1 class="page-title">Dashboard</h1>
        </div>

        <div class="header-right">
          <router-link to="/" class="back-home-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span>Trang chủ</span>
          </router-link>
        </div>
      </header>

      <!-- Content -->
      <main class="admin-content">
        <div class="content-inner">
          <router-view></router-view>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import AdminAside from '../components/AdminAside.vue';
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--ocean-deepest);
  font-family: var(--font-inter);
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* Header */
.admin-header {
  height: 70px;
  background: #f8f9fa;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
}

.page-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1a1a1a;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-left {
  display: flex;
  align-items: center;
}

.back-home-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 6px;
  color: #555;
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s;
}

.back-home-btn:hover {
  background: #f0f7ff;
  border-color: #1d4ed8;
  color: #1d4ed8;
}

.back-home-btn span {
  font-weight: 600;
}

/* Icon button */
.icon-btn {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--ocean-deepest);
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  cursor: pointer;
  transition: all 0.2s;
}



/* Content */
.admin-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
  background: var(--ocean-deepest);
}

.content-inner {
  max-width: 1400px;
  margin: 0 auto;
}
</style>
