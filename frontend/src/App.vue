<template>
  <router-view></router-view>
</template>

<script setup>
import { onMounted } from 'vue';

onMounted(() => {
  // Dọn dẹp data cũ ở localStorage để ẩn toàn bộ thông tin user theo yêu cầu
  if (localStorage.getItem('user')) {
    localStorage.removeItem('user');
  }
});
</script>
