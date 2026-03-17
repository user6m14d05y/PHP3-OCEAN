<template>
  <div class="min-h-screen bg-gray-50 flex flex-col justify-center items-center">
    <h1 class="text-4xl font-bold text-gray-800 mb-4">Blank Ocean Project</h1>
    <p class="text-lg text-gray-600 mb-6">Chào các con vợ nhá!!!</p>
    
    <div class="flex gap-4 mt-4">
      <router-link to="/admin" class="px-4 py-2 bg-blue-500 text-white rounded shadow hover:bg-blue-600 transition">Vào trang Admin</router-link>
      <router-link to="/client/login" class="px-4 py-2 bg-green-500 text-white rounded shadow hover:bg-green-600 transition">Vào trang Login</router-link>
    </div>
  </div>
</template>

<script setup>
</script>
