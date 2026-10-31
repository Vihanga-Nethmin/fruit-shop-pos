import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Inventory API address. Change with VITE_API_TARGET (for example http://127.0.0.1:8002).
const target = process.env.VITE_API_TARGET || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    // /api walata yana requests Laravel inventory-service ekata yawanawa (CORS awul nae)
    proxy: {
      '/api': { target, changeOrigin: true },
    },
  },
})