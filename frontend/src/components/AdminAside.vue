<template>
  <aside class="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-dot"></div>
      <div>
        <h2 class="brand-title">OCEAN</h2>
        <span class="brand-sub">Admin Panel</span>
      </div>
    </div>

    <!-- Nav -->
    <nav class="sidebar-nav">
      <span class="nav-label">MENU</span>

      <router-link to="/admin" class="nav-item" exact-active-class="nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Dashboard
      </router-link>

      <router-link to="/admin/product" class="nav-item" active-class="nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
          <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
        </svg>
        Products
      </router-link>

      <router-link to="/admin/order" class="nav-item" active-class="nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/>
        </svg>
        Orders
      </router-link>

      <router-link to="/admin/user" class="nav-item" active-class="nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
        Users
      </router-link>

      <span class="nav-label" style="margin-top: 20px;">SETTINGS</span>

      <router-link to="/admin/settings" class="nav-item" active-class="nav-item--active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
        </svg>
        Settings
      </router-link>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
      <router-link to="/" class="back-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
        </svg>
        Back to Store
      </router-link>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 250px;
  min-height: 100vh;
  background: var(--card-bg); /* White background */
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  border-right: 1px solid var(--border-color);
}

/* Brand */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 22px;
  border-bottom: 1px solid var(--border-color);
}

.brand-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--ocean-blue);
  flex-shrink: 0;
}

.brand-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--ocean-blue);
  letter-spacing: 2.5px;
  line-height: 1.2;
}

.brand-sub {
  font-size: 0.65rem;
  font-weight: 500;
  color: var(--text-muted);
  letter-spacing: 0.5px;
}

/* Nav */
.sidebar-nav {
  flex: 1;
  padding: 16px 14px;
  overflow-y: auto;
}

.nav-label {
  display: block;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 1.2px;
  color: var(--text-light);
  padding: 0 10px;
  margin-bottom: 8px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 8px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s ease;
  margin-bottom: 2px;
}

.nav-item:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}

.nav-item--active {
  background: rgba(2, 136, 209, 0.08) !important;
  color: var(--ocean-blue) !important;
  font-weight: 600;
}

/* Footer */
.sidebar-footer {
  padding: 14px;
  border-top: 1px solid var(--border-color);
}

.back-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px;
  border-radius: 8px;
  border: 1px solid var(--border-color);
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 600;
  transition: all 0.2s ease;
}

.back-link:hover {
  background: var(--hover-bg);
  color: var(--text-main);
}
</style>
