<template>
  <div class="min-h-screen flex bg-gray-100 font-sans text-gray-900">
    <!-- Component Aside Sidebar -->
    <AdminAside />

    <!-- Khu vực Content chính -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Navbar Admin -->
      <header class="h-16 bg-white shadow flex items-center justify-between px-6 shrink-0 z-10 transition-shadow">
        <h1 class="text-2xl font-semibold text-gray-800 tracking-tight">Dashboard Overview</h1>
        
        <div class="flex items-center gap-4">
          <button class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-full transition">
             <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
             </svg>
          </button>
          <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold cursor-pointer">
            A
          </div>
        </div>
      </header>
      
      <!-- Main view chứa nội dung trang con -->
      <main class="flex-1 p-6 overflow-auto bg-gray-50/50">
        <div class="max-w-7xl mx-auto">
          <router-view></router-view>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import AdminAside from '../components/AdminAside.vue';
</script>
