<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-4"
    @click.self="close"
  >
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
        <h3 class="font-bold text-gray-800 dark:text-gray-100">Escanear código de equipo</h3>
        <button
          type="button"
          class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg"
          aria-label="Cerrar"
          @click="close"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Cámara -->
      <div class="relative bg-black">
        <div id="scanner-region" class="w-full" />
        <div
          v-if="starting"
          class="absolute inset-0 flex items-center justify-center text-gray-300 text-sm"
        >
          Iniciando cámara...
        </div>
      </div>

      <!-- Estado / feedback -->
      <div class="px-5 py-3 space-y-2">
        <p v-if="message" :class="['text-sm font-medium', lastOk ? 'text-green-600' : 'text-red-500']">
          {{ message }}
        </p>

        <div v-if="sessionScans.length" class="max-h-32 overflow-y-auto space-y-1">
          <div
            v-for="(s, i) in sessionScans"
            :key="i"
            class="flex items-center justify-between text-xs bg-gray-50 dark:bg-gray-700 rounded-lg px-3 py-1.5"
          >
            <span class="font-mono text-gray-700 dark:text-gray-200">{{ s }}</span>
            <span class="text-green-500">✓</span>
          </div>
        </div>

        <!-- Manual -->
        <form class="flex gap-2 pt-1" @submit.prevent="registerManual">
          <input
            v-model="manualCode"
            type="text"
            placeholder="Código manual (ej. EQ-00123)"
            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm"
          />
          <button
            type="submit"
            :disabled="!manualCode.trim() || registering"
            class="px-4 py-2 bg-primary-500 hover:bg-primary-600 disabled:opacity-40 text-white rounded-lg text-sm font-medium"
          >
            Registrar
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onUnmounted } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'
import api from '@/utils/api'

const open = ref(false)
const starting = ref(false)
const registering = ref(false)
const message = ref('')
const lastOk = ref(false)
const manualCode = ref('')
const sessionScans = ref([])

let scanner = null
let lastCode = ''
let lastTime = 0

async function startCamera() {
  starting.value = true
  await nextTick()
  try {
    scanner = new Html5Qrcode('scanner-region', {
      formatsToSupport: undefined,
      verbose: false,
    })
    await scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 140 } },
      onScan,
      () => {}
    )
  } catch (e) {
    message.value = 'No se pudo abrir la cámara. Verifica permisos y usa HTTPS.'
    lastOk.value = false
  } finally {
    starting.value = false
  }
}

async function stopCamera() {
  if (scanner) {
    try { await scanner.stop() } catch {}
    try { scanner.clear() } catch {}
    scanner = null
  }
}

function onScan(decodedText) {
  const now = Date.now()
  if (decodedText === lastCode && now - lastTime < 2500) return // antirrebote
  lastCode = decodedText
  lastTime = now
  register(decodedText)
}

async function registerManual() {
  const code = manualCode.value.trim()
  if (!code) return
  manualCode.value = ''
  await register(code)
}

async function register(code) {
  registering.value = true
  try {
    await api.post('/scans', { code, method: 'camera' })
    sessionScans.value.unshift(code)
    message.value = `Equipo registrado: ${code}`
    lastOk.value = true
    if (navigator.vibrate) navigator.vibrate(80)
  } catch (e) {
    message.value = e.response?.data?.message || `Error al registrar ${code}`
    lastOk.value = false
  } finally {
    registering.value = false
  }
}

async function show() {
  open.value = true
  message.value = ''
  sessionScans.value = []
  await startCamera()
}

async function close() {
  await stopCamera()
  open.value = false
}

onUnmounted(stopCamera)

defineExpose({ show })
</script>
