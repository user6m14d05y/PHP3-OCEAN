<template>
  <aside class="w-64 bg-gray-800 text-white flex flex-col shrink-0">
    <div class="h-16 flex items-center justify-center border-b border-gray-700">
      <h2 class="text-xl font-bold">Admin Panel</h2>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-2">
      <router-link to="/admin" exact class="block px-4 py-2 rounded hover:bg-gray-700 transition" active-class="bg-blue-600">
        Dashboard
      </router-link>
      <!-- Bạn có thể thêm các link khác vào đây sau này -->
      <a href="#" class="block px-4 py-2 rounded hover:bg-gray-700 transition">Products</a>
      <a href="#" class="block px-4 py-2 rounded hover:bg-gray-700 transition">Orders</a>
      <a href="#" class="block px-4 py-2 rounded hover:bg-gray-700 transition">Users</a>
    </nav>
    <div class="p-4 border-t border-gray-700">
      <router-link to="/" class="block px-4 py-2 rounded text-center bg-gray-700 hover:bg-gray-600 transition text-sm">
        ← Back to Store
      </router-link>
    </div>
  </aside>
</template>

<script setup>
</script>
