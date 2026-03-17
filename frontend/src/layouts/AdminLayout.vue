<template>
  <div class="admin-layout">
    <AdminAside />

    <div class="admin-main">
      <!-- Header -->
      <header class="admin-header">
        <h1 class="page-title">🌊 Dashboard</h1>

        <div class="header-right">
          <!-- Search -->
          <div class="header-search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" placeholder="Search..." class="search-input" />
          </div>

          <!-- Notification -->
          <button class="icon-btn" title="Notifications">
             <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="notif-dot"></span>
          </button>

          <!-- User -->
          <div class="header-user">
            <div class="user-avatar">A</div>
            <div class="user-info">
              <span class="user-name">Admin</span>
              <span class="user-role">Manager</span>
            </div>
          </div>
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
  color: var(--text-main);
  -webkit-font-smoothing: antialiased;
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

/* Header */
.admin-header {
  height: 64px;
  background: var(--card-bg); /* White header */
  border-bottom: 1px solid var(--border-color);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  flex-shrink: 0;
  box-shadow: 0 1px 4px rgba(2, 136, 209, 0.05);
}

.page-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-main);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

/* Search */
.header-search {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--ocean-deepest);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 8px 14px;
  transition: all 0.2s;
}

.header-search:focus-within {
  border-color: var(--ocean-blue);
  background: white;
  box-shadow: 0 0 0 3px rgba(2, 136, 209, 0.1);
}

.header-search svg {
  color: var(--text-light);
}

.search-input {
  background: none;
  border: none;
  outline: none;
  color: var(--text-main);
  font-family: var(--font-inter);
  font-size: 0.85rem;
  width: 180px;
}

.search-input::placeholder {
  color: var(--text-light);
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

.icon-btn:hover {
  background: var(--hover-bg);
  color: var(--ocean-blue);
  border-color: rgba(2, 136, 209, 0.2);
}

.notif-dot {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--coral);
  box-shadow: 0 0 0 2px white;
}

/* User */
.header-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.header-user:hover {
  background: var(--hover-bg);
}

.user-avatar {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: var(--ocean-blue);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: 700;
  font-size: 0.85rem;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-main);
  line-height: 1.2;
}

.user-role {
  font-size: 0.65rem;
  font-weight: 500;
  color: var(--text-muted);
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
