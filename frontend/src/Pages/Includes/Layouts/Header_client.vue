<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const isLoggedIn = ref(false);
const email = ref('');

onMounted(() => {
    const userSession = sessionStorage.getItem('user');
    if (userSession) {
        const userData = JSON.parse(userSession);
        isLoggedIn.value = userData.isLoggedIn;
        email.value = userData.email;
    }
});

const logout = () => {
    // remove session
    sessionStorage.removeItem('user');
    isLoggedIn.value = false;
    userName.value = '';
    
    // push to login
    router.push('/client/login');
};
</script>    

<template>
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-gray-100">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
          <div class="flex items-center space-x-8">
            <router-link to="/" class="font-serif text-2xl font-bold tracking-wider text-black">
              OCEAN
            </router-link>
            <nav class="hidden md:flex space-x-8">
              <a href="#" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition">Thời trang</a>
            </nav>
          </div>
          <div class="flex items-center space-x-6">
            <button class="text-gray-500 hover:text-gray-900 transition">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>
            <button class="text-gray-500 hover:text-gray-900 transition relative">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
              <span class="absolute -top-1.5 -right-1.5 bg-black text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center font-bold">2</span>
            </button>
            <router-link v-if="!isLoggedIn" to="/client/login" class="text-gray-500 hover:text-gray-900 transition relative">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </router-link>
            
            <div v-else class="hidden md:flex items-center space-x-4 ml-4 pl-4 border-l border-gray-200">
                <span class="text-sm font-semibold text-gray-800">Hi, {{ email }}</span>
                <button @click="logout" class="text-sm font-medium text-gray-500 hover:text-red-600 transition">Đăng xuất</button>
            </div>
          </div>
        </div>
      </div>
    </header>
    </template>