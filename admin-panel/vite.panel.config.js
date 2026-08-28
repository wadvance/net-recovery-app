import { defineConfig } from 'vite'
import baseConfigured from './vite.config.js'

const base = baseConfigured({ mode: 'production' })

export default defineConfig({
  ...base,
  base: '/panel/',
  build: {
    ...base.build,
    outDir: 'dist-panel',
    emptyOutDir: true,
  },
})