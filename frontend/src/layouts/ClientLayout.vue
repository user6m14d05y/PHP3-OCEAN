<template>
  <div class="min-h-screen flex flex-col font-sans text-gray-900 bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center">
            <router-link to="/" class="text-2xl font-black text-blue-600 tracking-tight">OCEAN</router-link>
            <nav class="hidden md:flex ml-10 space-x-8">
              <router-link to="/" class="text-gray-500 hover:text-gray-900 font-medium transition">Home</router-link>
              <a href="#" class="text-gray-500 hover:text-gray-900 font-medium transition">Categories</a>
              <a href="#" class="text-gray-500 hover:text-gray-900 font-medium transition">Promotions</a>
            </nav>
          </div>
          <div class="flex items-center space-x-4">
            <router-link to="/admin" class="text-sm text-gray-500 hover:text-gray-900">Admin</router-link>
            <button class="p-2 text-gray-400 hover:text-gray-500 transition">
              <span class="sr-only">Search</span>
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </button>
            <button class="p-2 text-gray-400 hover:text-gray-500 transition relative">
              <span class="sr-only">Cart</span>
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full">3</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
      <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <router-view></router-view>
      </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300">
      <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-sm">
          <div>
            <h3 class="text-white font-bold mb-4 tracking-wider uppercase text-xs">Ocean Store</h3>
            <p class="mb-4">Providing the best quality products for your everyday needs. Exceptional service, guaranteed.</p>
          </div>
          <div>
            <h3 class="text-white font-bold mb-4 tracking-wider uppercase text-xs">Quick Links</h3>
            <ul class="space-y-2">
              <li><a href="#" class="hover:text-white transition">About Us</a></li>
              <li><a href="#" class="hover:text-white transition">Contact</a></li>
              <li><a href="#" class="hover:text-white transition">Policy</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-white font-bold mb-4 tracking-wider uppercase text-xs">Contact</h3>
            <ul class="space-y-2">
              <li>Email: contact@oceanstore.com</li>
              <li>Phone: +84 123 456 789</li>
              <li>Address: Ho Chi Minh City, Vietnam</li>
            </ul>
          </div>
        </div>
        <div class="border-t border-gray-800 mt-12 pt-8 text-center text-xs">
          <p>&copy; 2026 Ocean Store. All rights reserved.</p>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
</script>
