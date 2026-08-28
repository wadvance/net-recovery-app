import { ref, onMounted, onUnmounted } from 'vue'

export function usePwaInstall() {
  const canInstall = ref(false)
  const installed = ref(false)
  const isStandalone = ref(
    typeof window !== 'undefined' &&
      (window.matchMedia?.('(display-mode: standalone)').matches ||
        navigator.standalone === true)
  )
  const isIOS = ref(
    /(iPhone|iPad|iPod)/i.test(navigator.userAgent) ||
      (/Mac/.test(navigator.platform) && navigator.maxTouchPoints > 1)
  )
  let deferredPrompt = null

  function onBeforeInstallPrompt(e) {
    e.preventDefault()
    deferredPrompt = e
    canInstall.value = true
  }

  function onAppInstalled() {
    canInstall.value = false
    installed.value = true
    isStandalone.value = true
    deferredPrompt = null
  }

  function onDisplayModeChange(e) {
    if (e.matches) {
      installed.value = true
      isStandalone.value = true
    }
  }

  async function install() {
    if (isIOS.value) {
      alert('Para instalar la app: abre el menú Compartir de tu navegador y toca "Añadir a pantalla de inicio".')
      return
    }
    if (!deferredPrompt) {
      alert('Para instalar la app: abre el menú ⋮ de Chrome y toca "Instalar aplicación" o "Añadir a la pantalla de inicio".')
      return
    }
    deferredPrompt.prompt()
    await deferredPrompt.userChoice
    deferredPrompt = null
    canInstall.value = false
  }

  let displayMedia

  onMounted(() => {
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt)
    window.addEventListener('appinstalled', onAppInstalled)
    if (window.matchMedia) {
      displayMedia = window.matchMedia('(display-mode: standalone)')
      displayMedia.addEventListener?.('change', onDisplayModeChange)
    }
  })

  onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt)
    window.removeEventListener('appinstalled', onAppInstalled)
    displayMedia?.removeEventListener?.('change', onDisplayModeChange)
  })

  return { canInstall, installed, isStandalone, isIOS, install }
}