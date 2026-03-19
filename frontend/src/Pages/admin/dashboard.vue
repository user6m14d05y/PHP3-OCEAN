<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const isLoggedIn = ref(false);

const logout = () => {
    sessionStorage.removeItem('user');
    isLoggedIn.value = false;
    router.push('/');
};
</script>

<template>
    <div class="dash-container">
        <h1 class="dash-title">Dashboard Admin</h1>
        <p class="dash-desc">Welcome to the admin dashboard!</p>
        
        <div class="dash-actions">
            <router-link to="/" class="btn-action btn-gray">Về Trang chủ (Home)</router-link>
            <router-link to="/client" class="btn-action btn-green">Sang Trang Client</router-link>
            <button @click="logout" class="btn-action btn-green">Đăng xuất</button>
        </div>
    </div>
</template>

<style scoped>
.dash-container { padding: 32px; }
.dash-title { font-size: 1.875rem; font-weight: 700; margin-bottom: 16px; }
.dash-desc { margin-bottom: 16px; }
.dash-actions { display: flex; gap: 16px; }
.btn-action {
  padding: 8px 16px;
  color: #fff;
  border-radius: 6px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.12);
  text-decoration: none;
  font-weight: 500;
  border: none;
  cursor: pointer;
  font-size: 0.9rem;
  transition: background 0.2s;
}
.btn-gray { background: #6b7280; }
.btn-gray:hover { background: #4b5563; }
.btn-green { background: #22c55e; }
.btn-green:hover { background: #16a34a; }
</style>