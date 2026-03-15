<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const email = ref('');
const password = ref('');

// show alert 2
import Swal from 'sweetalert2';

const router = useRouter();

const login = async () => {
  if (email.value === ""){
    Swal.fire({
      icon: 'error',
      title: "Vui lòng nhập email của bạn",
      showConfirmButton: false,
      timer: 1500,
      timerProgressBar: true,
    })
    return;
  }
  if (password.value === ""){
     Swal.fire({
      icon: 'error',
      title: "Vui lòng nhập mật khẩu của bạn",
      showConfirmButton: false,
      timer: 1500,
      timerProgressBar: true,
    })
    return;
  }

  try {
    const response = await axios.post('http://localhost:8000/api/Login', {
        email: email.value,
        password: password.value
    });
    
    if (response.data.status === "success"){
      Swal.fire({
        icon: 'success',
        title: "Đăng nhập thành công!",
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
      })
      router.push('/client/home');
    }
  } catch (error) {
    let errorMessage = "Đăng nhập thất bại!";
    
    // Read error massage from php
    if (error.response && error.response.data && error.response.data.message) {
        errorMessage = error.response.data.message;
    }
    
    Swal.fire({
      icon: 'error',
      title: errorMessage,
      showConfirmButton: false,
      timer: 1500,
      timerProgressBar: true,
    })
  }

}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-4xl w-full flex bg-white rounded-2xl shadow-2xl overflow-hidden min-h-[600px]">
      
      <!-- Left side: Image banner (Hidden on small screens) -->
      <div class="hidden md:block md:w-1/2 relative">
        <img 
          class="absolute inset-0 h-full w-full object-cover" 
          src="https://cdn.vectorstock.com/i/1000v/95/69/fashion-pattern-background-vector-639569.jpg" 
          alt="Fashion Model" 
        />
        <div class="absolute inset-0 bg-black bg-opacity-20 transition-opacity duration-300 hover:bg-opacity-30"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
        
        <div class="absolute bottom-0 left-0 p-10 text-white">
          <h2 class="text-4xl font-serif font-bold mb-3 tracking-wide">Ocean Fashion</h2>
          <p class="text-sm opacity-90 font-light tracking-wider leading-relaxed">
            Khám phá xu hướng mới nhất và thể hiện phong cách độc đáo của bạn cùng bst mùa này.
          </p>
        </div>
      </div>

      <!-- Right side: Login Form -->
      <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center">
        <!-- Logo / Title -->
        <div class="mb-10 text-center md:text-left">
          <h2 class="text-3xl font-serif font-bold text-gray-900 tracking-tight mb-2">Đăng nhập</h2>
          <p class="text-sm text-gray-500">Vui lòng nhập thông tin để tiếp tục.</p>
        </div>

        <form @submit.prevent="login" class="space-y-6">
          
          <!-- Email Input -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Địa chỉ Email</label>
            <div class="mt-1">
              <input 
                id="email" 
                name="email" 
                type="email" 
                autocomplete="email" 
                required 
                v-model="email"
                class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="you@example.com"
              >
            </div>
          </div>

          <!-- Password Input -->
          <div>
            <div class="mt-4">
              <label for="password" class="block text-sm font-medium text-gray-700">Mật khẩu</label>
            </div>
            <div class="mt-1 relative">
              <input 
                id="password" 
                name="password" 
                type="password" 
                autocomplete="current-password" 
                required 
                v-model="password"
                class="appearance-none block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="••••••••"
              >
            </div>
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-gray-900 focus:ring-gray-900 border-gray-300 rounded cursor-pointer">
              <label for="remember-me" class="ml-2 block text-sm text-gray-700 cursor-pointer">
                Ghi nhớ đăng nhập
              </label>
            </div>

            <div class="text-sm">
              <router-link to="/client/forgot" class="font-medium text-gray-600 hover:text-gray-900 transition duration-150">
                Quên mật khẩu?
              </router-link>
            </div>
          </div>

          <!-- Submit Button -->
          <div>
            <button 
              type="submit" 
              class="w-full flex justify-center items-center py-3.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition active:scale-[0.98] duration-200 ease-in-out"
            >
              Đăng nhập
            </button>
          </div>
        </form>

        <!-- Divider -->
        <div class="mt-8">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-4 bg-white text-gray-500">
                Hoặc đăng nhập bằng
              </span>
            </div>
          </div>

          <!-- Social Buttons -->
          <div class="mt-6 grid grid-cols-2 gap-4">
            <button class="w-full flex items-center justify-center py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition duration-200">
              <img class="h-5 w-5" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
              <span class="ml-2">Google</span>
            </button>
            <button class="w-full flex items-center justify-center py-2.5 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition duration-200">
              <img class="h-5 w-5" src="https://www.svgrepo.com/show/475647/facebook-color.svg" alt="Facebook">
              <span class="ml-2">Facebook</span>
            </button>
          </div>
        </div>

        <!-- Register Link -->
        <p class="mt-8 text-center text-sm text-gray-600">
          Chưa có tài khoản?
          <router-link to="/client/register" class="font-medium text-gray-900 hover:underline hover:text-black">
            Đăng ký ngay
          </router-link>
        </p>

      </div>
    </div>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap');

.font-serif {
  font-family: 'Playfair Display', serif;
}
.font-sans {
  font-family: 'Inter', sans-serif;
}
</style>