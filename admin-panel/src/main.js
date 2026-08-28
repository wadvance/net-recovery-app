import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('./sw.js')
      .then((registration) => registration.update())
      .catch(() => {})
  })
}

// Silencia AbortError inofensivo del video/cámara ("The play() request was interrupted...")
window.addEventListener('unhandledrejection', (e) => {
  if (e.reason?.name === 'AbortError' && String(e.reason?.message || '').includes('play()')) e.preventDefault()
})
window.addEventListener('error', (e) => {
  if (String(e.message || '').includes('play()') && String(e.message || '').includes('interrupted')) e.preventDefault()
})

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')