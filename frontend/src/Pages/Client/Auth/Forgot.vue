<script setup>
import { ref } from 'vue';

const email = ref('');

const handleForgotPassword = () => {
    // Handle forgot password logic here
    console.log('Sending reset link to:', email.value);
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-4xl w-full flex bg-white rounded-2xl shadow-2xl overflow-hidden min-h-[500px]">
      
      <!-- Left side: Image banner (Hidden on small screens) -->
      <div class="hidden md:block md:w-1/2 relative">
        <img 
          class="absolute inset-0 h-full w-full object-cover grayscale-[20%]" 
          src="https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
          alt="Fashion Detail" 
        />
        <div class="absolute inset-0 bg-black bg-opacity-20 transition-opacity duration-300 hover:bg-opacity-30"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
        
        <div class="absolute bottom-0 left-0 p-10 text-white">
          <h2 class="text-4xl font-serif font-bold mb-3 tracking-wide">Ocean Fashion</h2>
          <p class="text-sm opacity-90 font-light tracking-wider leading-relaxed">
            Khôi phục quyền truy cập vào tài khoản của bạn.
          </p>
        </div>
      </div>

      <!-- Right side: Forgot Password Form -->
      <div class="w-full md:w-1/2 p-8 sm:p-12 flex flex-col justify-center">
        <!-- Logo / Title -->
        <div class="mb-10 text-center md:text-left">
          <h2 class="text-3xl font-serif font-bold text-gray-900 tracking-tight mb-2">Quên mật khẩu?</h2>
          <p class="text-sm text-gray-500 leading-relaxed">
            Đừng lo lắng, chuyện này thường xuyên xảy ra. Vui lòng nhập địa chỉ email liên kết với tài khoản của bạn để nhận liên kết đặt lại mật khẩu.
          </p>
        </div>

        <form @submit.prevent="handleForgotPassword" class="space-y-6">
          
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

          <!-- Submit Button -->
          <div class="pt-2">
            <button 
              type="submit" 
              class="w-full flex justify-center items-center py-3.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition active:scale-[0.98] duration-200 ease-in-out"
            >
              Gửi liên kết đặt lại
            </button>
          </div>
        </form>

        <!-- Back to Login Link -->
        <div class="mt-8 text-center text-sm">
          <router-link to="/client/login" class="flex items-center justify-center text-gray-600 hover:text-gray-900 transition duration-150 font-medium">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Quay lại đăng nhập
          </router-link>
        </div>

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
