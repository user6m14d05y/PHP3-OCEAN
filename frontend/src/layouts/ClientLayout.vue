<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios  from "axios";

const router = useRouter();
const isLoggedIn = ref(false);
const name = ref('');
const email = ref('');


onMounted(() => {
    const userSession = localStorage.getItem('user');
    if (userSession) {
        const userData = JSON.parse(userSession);
        isLoggedIn.value = userData.isLoggedIn;
        name.value = userData.name;
    }
});

const SubmitContact = async () => {

  if(email.value === ""){
    // error.value = "Email của bạn đang để trống";
    alert ("abcdef");
    return;
  }

  try {
    const reponse = await axios.post("http://localhost:8383/api/SubmitContact", {
      email: email.value
    }); 
    if (reponse.data.status == "success"){
     alert(reponse.data.message);
     email.value = "";
    }
  } catch (error) {
        alert("loi gui email: " + error);
        console.error('Lỗi đăng ký:', error);
    };
};

const logout = async () => {
    try {
        await axios.post('http://localhost:8383/api/Logout'); 
    } catch (error) {
        console.error("Lỗi logout server:", error);
    } finally {
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
        isLoggedIn.value = false;
        name.value = '';
        router.push('/client/login');
    }
};
</script>

<template>
  <div class="min-h-screen flex flex-col font-sans text-gray-900 bg-gray-50">
    <!-- Header -->
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
                <span class="text-sm font-semibold text-gray-800">Hi, {{ name }}</span>
                <button @click="logout" class="text-sm font-medium text-gray-500 hover:text-red-600 transition">Đăng xuất</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
      <!-- <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8"> -->
        <router-view></router-view>
      <!-- </div> -->
    </main>

    <!-- Footer -->
     <footer class="bg-gray-900 pt-16 pb-8 text-white border-t border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
          <div class="col-span-1 md:col-span-1">
            <h2 class="text-2xl font-serif font-bold tracking-widest mb-6">OCEAN</h2>
            <p class="text-gray-400 text-sm font-light leading-relaxed mb-6">Phân phối thời trang cao cấp với thiết kế tinh xảo và tính ứng dụng cao nhất cho cuộc sống hiện đại.</p>
          </div>
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider mb-5">Danh Mục</h3>
            <ul class="space-y-3 text-gray-400 text-sm font-light">
              <li><a href="#" class="hover:text-white transition">Sản Phẩm Mới</a></li>
              <li><a href="#" class="hover:text-white transition">Áo Khoác Nam</a></li>
              <li><a href="#" class="hover:text-white transition">Váy Đầm Nữ</a></li>
              <li><a href="#" class="hover:text-white transition">Giày Phụ Kiện</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider mb-5">Hỗ Trợ</h3>
            <ul class="space-y-3 text-gray-400 text-sm font-light">
              <li><a href="#" class="hover:text-white transition">Chính Sách Giao Hàng</a></li>
              <li><a href="#" class="hover:text-white transition">Đổi Trả Khách Hàng</a></li>
              <li><a href="#" class="hover:text-white transition">Câu Hỏi Thường Gặp</a></li>
              <li><a href="#" class="hover:text-white transition">Liên Hệ</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider mb-5">Nhận Bản Tin</h3>
            <p class="text-gray-400 text-sm font-light mb-4">Đăng ký để nhận ưu đãi hấp dẫn ngay lập tức.</p>
            <form @submit.prevent="SubmitContact" class="flex border-b border-gray-600 focus-within:border-white transition pb-1">
              <input v-model="email" type="email" placeholder="Email của bạn..." class="bg-transparent border-none outline-none text-sm w-full font-light text-white placeholder-gray-500">
              <button type="submit" class="text-white hover:text-gray-300 font-bold">→</button>
            </form>
          </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-gray-500 text-xs font-light">
          <p>© 2026 Ocean Fashion. All rights reserved.</p>
          <div class="flex space-x-6 mt-4 md:mt-0">
            <a href="#" class="hover:text-white transition">Điều Khoản Phục Vụ</a>
            <a href="#" class="hover:text-white transition">Bảo Mật Quyền Riêng Tư</a>
          </div>
        </div>
      </div>
    </footer>
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
