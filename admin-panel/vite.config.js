import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

const isFirebase = process.env.FIREBASE === 'true'

export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  base: isFirebase ? '/' : '/admin/',
  envPrefix: 'VITE_',
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  build: {
    outDir: isFirebase ? 'dist' : 'public/admin',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    strictPort: true,
    hmr: false,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
}))
