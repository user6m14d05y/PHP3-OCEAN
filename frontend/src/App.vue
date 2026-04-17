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

<style>
/* Ngăn chặn thanh cuộn ngang gây ra bởi các thẻ full width (100vw) trên Windows/Linux */
html, body {
  overflow-x: hidden;
  max-width: 100%;
}
</style>
