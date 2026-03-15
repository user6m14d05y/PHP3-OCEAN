<script setup>
import Footer_client from '@/Pages/Includes/Layouts/Footer_client.vue';
import Header_client from '@/Pages/Includes/Layouts/Header_client.vue';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

// Dummy data for products
const featuredProducts = ref([
  {
    id: 1,
    name: 'Áo Khoác Blazer Classic',
    price: '1.250.000 ₫',
    image: 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
    category: 'Áo Khoác'
  },
  {
    id: 2,
    name: 'Đầm Lụa Midi Mùa Thu',
    price: '850.000 ₫',
    image: 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
    category: 'Váy & Đầm'
  },
  {
    id: 3,
    name: 'Sơ Mi Cotton Trắng Basic',
    price: '450.000 ₫',
    image: 'https://product.hstatic.net/200000525243/product/image-trang-1-ao-so-mi-basic-nam-2406003_1ae823c52e1a45dca41dfefa98683a22_1024x1024.jpg',
    category: 'Áo Sơ Mi'
  },
  {
    id: 4,
    name: 'Quần u Thanh Lịch',
    price: '650.000 ₫',
    image: 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80',
    category: 'Quần'
  }
]);

const collections = ref([
  { name: 'Bộ Sưu Tập Nữ', image: 'https://images.unsplash.com/photo-1483985988355-763728e1935b?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80' },
  { name: 'Bộ Sưu Tập Nam', image: 'https://images.unsplash.com/photo-1490578474895-699bc4e3f39a?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80' },
  { name: 'Phụ Kiện', image: 'https://images.unsplash.com/photo-1523206489230-c012c64b2b48?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80' }
]);

const logout = () => {
    // Basic logout handling
    router.push('/client/login');
};
</script>

<template>
  <div class="min-h-screen bg-white font-sans text-gray-900">
    <Header_client/>


    <!-- Hero Banner -->
    <div class="relative w-full h-[650px] bg-gray-100 flex items-center justify-center overflow-hidden">
      <img src="https://images.unsplash.com/photo-1469334031218-e382a71b716b?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80" alt="Hero Banner" class="absolute inset-0 w-full h-full object-cover">
      <div class="absolute inset-0 bg-black/30"></div>
      
      <div class="relative z-10 text-center text-white px-4 max-w-3xl">
        <span class="text-sm uppercase tracking-[0.3em] mb-4 block font-medium">Bộ Sưu Tập Mới Nhất</span>
        <h1 class="text-5xl md:text-7xl font-serif font-bold mb-6 leading-tight">Mùa Yêu Thương</h1>
        <p class="text-lg md:text-xl font-light mb-10 text-gray-100">Khám phá phong cách tối giản mang đậm chất riêng, tôn vinh vẻ đẹp thuần khiết và thanh lịch từ bên trong bạn.</p>
        <button class="bg-white text-black px-10 py-4 text-sm font-semibold tracking-wider hover:bg-gray-100 transition duration-300">
          MUA SẮM NGAY
        </button>
      </div>
    </div>

    <!-- Featured Products -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
      <div class="flex justify-between items-end mb-10">
        <div>
          <h2 class="text-3xl font-serif font-bold text-gray-900 mb-2">Hàng Mới Về</h2>
          <p class="text-gray-500 font-light">Những xu hướng thời trang nổi bật nhất tuần này.</p>
        </div>
        <a href="#" class="hidden sm:block text-sm font-medium text-black border-b border-black pb-1 hover:text-gray-600 hover:border-gray-600 transition">
          Xem tất cả
        </a>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <div v-for="product in featuredProducts" :key="product.id" class="group cursor-pointer">
          <div class="relative h-96 mb-4 overflow-hidden bg-gray-100">
            <img :src="product.image" :alt="product.name" class="w-full h-full object-cover object-center group-hover:scale-105 transition duration-700 ease-in-out">
            <div class="absolute bottom-4 left-0 right-0 flex justify-center opacity-0 group-hover:opacity-100 transition duration-300">
              <button class="bg-white px-6 py-3 border border-gray-200 text-sm font-medium shadow-lg hover:bg-black hover:text-white transition w-10/12">
                Thêm Vào Giỏ
              </button>
            </div>
          </div>
          <div>
            <span class="text-xs text-gray-500 uppercase tracking-wider mb-1 block">{{ product.category }}</span>
            <h3 class="text-base font-medium text-gray-900">{{ product.name }}</h3>
            <p class="mt-1 text-sm text-gray-700 font-semibold">{{ product.price }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Banner Split -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-w-[1400px] mx-auto px-2 mb-20">
      <div class="relative h-[500px] overflow-hidden group">
        <img src="https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Style Edit" class="w-full h-full object-cover transition duration-1000 group-hover:scale-105">
        <div class="absolute inset-0 bg-black/20 group-hover:bg-black/40 transition duration-500"></div>
        <div class="absolute inset-0 flex flex-col justify-center items-center text-white text-center p-6">
          <h3 class="text-3xl font-serif font-bold mb-4">The Style Edit</h3>
          <button class="border border-white px-8 py-3 text-sm font-medium hover:bg-white hover:text-black transition duration-300">Khám Phá</button>
        </div>
      </div>
      <div class="relative h-[500px] overflow-hidden group bg-gray-100 flex flex-col justify-center items-center text-center p-12">
        <span class="text-gray-400 text-sm uppercase tracking-[0.2em] mb-4">Cam Kết Chất Lượng</span>
        <h3 class="text-3xl font-serif font-bold text-gray-900 mb-6">Chất Liệu Bền Vững</h3>
        <p class="text-gray-600 font-light mb-8 max-w-md leading-relaxed">Chúng tôi sử dụng sợi tái chế và vải hữu cơ thiên nhiên, góp phần bảo vệ môi trường mà vẫn giữ được sự tinh tế, sang trọng cho người mặc.</p>
        <button class="text-sm font-medium text-black border-b border-black pb-1 hover:text-gray-500 hover:border-gray-500 transition">Tìm Hiểu Thêm</button>
      </div>
    </div>

   <Footer_client/>
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