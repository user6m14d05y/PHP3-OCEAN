<template>
  <router-view></router-view>
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
  // Dọn dẹp token/user cũ ở localStorage (di sản từ phiên bản cũ dùng localStorage)
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
});
</script>
