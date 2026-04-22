import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    watch: {
      usePolling: true,
      // Giảm polling interval → giảm CPU usage trong Docker
      interval: 1000,
    },
    host: true,
    strictPort: true,
    port: 3302,
    // Cho phép domain production truy cập dev server
    allowedHosts: ['ocean.pro.vn', 'www.ocean.pro.vn', 'api.ocean.pro.vn'],
    // HMR: dùng public domain thay vì localhost khi chạy trong Docker
    hmr: {
      host: 'ocean.pro.vn',
      protocol: 'wss',
      overlay: true,
    },
  },
  build: {
    // Tách code thành nhiều chunk nhỏ
    rollupOptions: {
      output: {
        manualChunks: {
          // Tách vendor libs ra chunk riêng → cache lâu dài
          'vendor-vue': ['vue', 'vue-router'],
          'vendor-axios': ['axios'],
        },
      },
    },
    // Kích thước chunk cảnh báo
    chunkSizeWarningLimit: 500,
    // Minify bằng esbuild (nhanh hơn terser)
    minify: 'esbuild',
    // Tạo source map cho debug (tắt khi production thật)
    sourcemap: false,
    // CSS code splitting
    cssCodeSplit: true,
  },
  // Tối ưu dependencies pre-bundling
  optimizeDeps: {
    include: ['vue', 'vue-router', 'axios'],
  },
})
