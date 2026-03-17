<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const name = ref('');
const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const errorMsg = ref('');
const isSubmitting = ref(false);
const router = useRouter();

import Swal from 'sweetalert2';

const handleRegister = async () => {
    if (name.value === "") {
      errorMsg.value = "Vui lòng nhập tên"
      return;
    }
    if (email.value === "") {
      errorMsg.value = "Vui lòng nhập email"
      return;
    }
    if (password.value === "") {
      errorMsg.value = "Vui lòng nhập mật khẩu"
      return;
    }
    if (password_confirmation.value === "") {
      errorMsg.value = "Vui lòng nhập mật khẩu xác nhận"
      return;
    }
    if (password.value !== password_confirmation.value) {
        errorMsg.value = 'Mật khẩu xác nhận không khớp!';
        return;
    }
    
    errorMsg.value = '';
    isSubmitting.value = true;
    
    try {
        const response = await axios.post('http://localhost:8383/api/register', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value
        });
        
        if (response.data.status === 'success') {
            // Show Sweet alert 2
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: response.data.message || 'Đăng ký thành công!',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            router.push('/client/login');
        }
    } catch (error) {
        let errorText = 'Có lỗi xảy ra, vui lòng thử lại sau.';
        if (error.response && error.response.data.errors) {
            const errors = error.response.data.errors;
            errorText = Object.values(errors)[0][0];
        }
        
        // Hiển thị Banner đỏ của Form như bạn thiết kế
        errorMsg.value = errorText;
        
        // Show Sweet alert 2
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: errorText,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        console.error('Lỗi đăng ký:', error);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-4xl w-full flex bg-white rounded-2xl shadow-2xl overflow-hidden min-h-[600px]">
      
      <!-- Left side: Image banner (Hidden on small screens) -->
      <div class="hidden md:block md:w-1/2 relative">
        <img 
          class="absolute inset-0 h-full w-full object-cover" 
          src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
          alt="Fashion Model 2" 
        />
        <div class="absolute inset-0 bg-black bg-opacity-20 transition-opacity duration-300 hover:bg-opacity-30"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
        
        <div class="absolute bottom-0 left-0 p-10 text-white">
          <h2 class="text-4xl font-serif font-bold mb-3 tracking-wide">Ocean Fashion</h2>
          <p class="text-sm opacity-90 font-light tracking-wider leading-relaxed">
            Gia nhập cộng đồng Ocean Fashion. Tận hưởng ưu đãi độc quyền dành riêng cho thành viên.
          </p>
        </div>
      </div>

      <!-- Right side: Register Form -->
      <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center">
        <!-- Logo / Title -->
        <div class="mb-6 text-center md:text-left">
          <h2 class="text-3xl font-serif font-bold text-gray-900 tracking-tight mb-2">Đăng ký</h2>
          <p class="text-sm text-gray-500">Tạo tài khoản mới để bắt đầu mua sắm.</p>
        </div>

        <form @submit.prevent="handleRegister" class="space-y-4">
          
          <!-- Error Message Banner -->
          <div v-if="errorMsg" class="mb-4 bg-red-50 p-4 rounded-lg flex items-start">
             <svg class="h-5 w-5 text-red-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm font-medium text-red-800">{{ errorMsg }}</p>
          </div>

          <!-- Name Input -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Họ và tên</label>
            <div class="mt-1">
              <input 
                id="name" 
                name="name" 
                type="text" 
                required 
                v-model="name"
                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="Nguyễn Văn A"
                :disabled="isSubmitting"
              >
            </div>
          </div>

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
                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="you@example.com"
                :disabled="isSubmitting"
              >
            </div>
          </div>

          <!-- Password Input -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Mật khẩu</label>
            <div class="mt-1 relative">
              <input 
                id="password" 
                name="password" 
                type="password" 
                required 
                v-model="password"
                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="••••••••"
                :disabled="isSubmitting"
              >
            </div>
          </div>

          <!-- Confirm Password Input -->
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Xác nhận mật khẩu</label>
            <div class="mt-1 relative">
              <input 
                id="password_confirmation" 
                name="password_confirmation" 
                type="password" 
                required 
                v-model="password_confirmation"
                class="appearance-none block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition duration-200"
                placeholder="••••••••"
                :disabled="isSubmitting"
              >
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-2">
            <button 
              type="submit" 
              class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition active:scale-[0.98] duration-200 ease-in-out disabled:opacity-70 disabled:cursor-not-allowed"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting" class="mr-2">
                 <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </span>
              {{ isSubmitting ? 'Đang xử lý...' : 'Đăng ký tài khoản' }}
            </button>
          </div>
        </form>

        <!-- Divider -->
        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">
                Hoặc đăng ký bằng
              </span>
            </div>
          </div>

          <!-- Social Buttons -->
          <div class="mt-4 grid grid-cols-2 gap-4">
            <button class="w-full flex items-center justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition duration-200">
              <img class="h-5 w-5" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
              <span class="ml-2">Google</span>
            </button>
            <button class="w-full flex items-center justify-center py-2 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition duration-200">
              <img class="h-5 w-5" src="https://www.svgrepo.com/show/475647/facebook-color.svg" alt="Facebook">
              <span class="ml-2">Facebook</span>
            </button>
          </div>
        </div>

        <!-- Login Link -->
        <p class="mt-6 text-center text-sm text-gray-600">
          Đã có tài khoản?
          <router-link to="/client/login" class="font-medium text-gray-900 hover:underline hover:text-black">
            Đăng nhập ngay
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
