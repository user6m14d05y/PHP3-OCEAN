<template>
  <div class="home-page">
    <h1 class="home-title">Blank Ocean Project</h1>
    <p class="home-desc">Chào các con vợ nhá!!!</p>
    
    <div class="home-actions">
      <router-link to="/admin" class="btn-action btn-blue">Vào trang Admin</router-link>
      <router-link to="/client/login" class="btn-action btn-green">Vào trang Login</router-link>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
.home-page {
  min-height: 100vh;
  background: #f9fafb;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.home-title { font-size: 2.25rem; font-weight: 700; color: #1f2937; margin-bottom: 16px; }
.home-desc { font-size: 1.125rem; color: #4b5563; margin-bottom: 24px; }
.home-actions { display: flex; gap: 16px; margin-top: 16px; }
.btn-action {
  padding: 8px 16px;
  color: #fff;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
  text-decoration: none;
  font-weight: 500;
  transition: background 0.2s;
}
.btn-blue { background: #3b82f6; }
.btn-blue:hover { background: #2563eb; }
.btn-green { background: #22c55e; }
.btn-green:hover { background: #16a34a; }
</style>
