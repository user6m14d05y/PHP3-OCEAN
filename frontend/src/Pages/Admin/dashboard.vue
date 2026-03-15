<script setup>
</script>

<template>
    <div class="p-8">
        <h1 class="text-3xl font-bold mb-4">Dashboard Admin</h1>
        <p class="mb-4">Welcome to the admin dashboard!</p>
        
        <div class="flex gap-4">
            <router-link to="/" class="px-4 py-2 bg-gray-500 text-white rounded shadow hover:bg-gray-600 transition">Về Trang chủ (Home)</router-link>
            <router-link to="/client" class="px-4 py-2 bg-green-500 text-white rounded shadow hover:bg-green-600 transition">Sang Trang Client</router-link>
        </div>
    </div>
</template>