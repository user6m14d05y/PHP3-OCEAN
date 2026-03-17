import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
  ],
<<<<<<< HEAD
  server: {
    watch: {
      usePolling: true,
    }
  },
=======
>>>>>>> 85eed9c2 (first commit)
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
<<<<<<< HEAD
=======
  server: {
    watch: {
      usePolling: true,
    },
    host: true,
    strictPort: true,
    port: 3302,
  },
>>>>>>> 85eed9c2 (first commit)
})
