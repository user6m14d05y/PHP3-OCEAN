 <script setup>
import { ref } from 'vue';
import axios  from "axios";
import Swal from 'sweetalert2';

const email = ref('');

const SubmitContact = async () => {

  if(email.value === ""){
    // error.value = "Email của bạn đang để trống";
    alert ("abcdef");
    return;
  }

  try {
    const reponse = await axios.post("http://localhost:8000/api/SubmitContact", {
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

</script>    

<template>
    <!-- Footer Simple -->
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
    </template>
 