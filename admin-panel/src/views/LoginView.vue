<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-500 to-primary-700 dark:from-gray-900 dark:to-primary-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Theme toggle -->
      <div class="flex justify-end mb-4">
        <button
          class="p-2 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors"
          @click="toggle()"
        >
          <svg
            v-if="isDark"
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
            />
          </svg>
          <svg
            v-else
            class="w-5 h-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
            />
          </svg>
        </button>
      </div>

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white dark:bg-gray-800 rounded-2xl shadow-lg mb-4">
          <svg
            class="w-10 h-10 text-primary-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-white">
          Equipment Recovery
        </h1>
        <p class="text-primary-100 dark:text-primary-300 mt-1">
          Panel de Administración
        </p>
      </div>

      <!-- Login form -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-6">
          Iniciar Sesión
        </h2>

        <form
          class="space-y-4"
          @submit.prevent="handleLogin"
        >
          <div>
            <label class="label">Correo electrónico</label>
            <input
              v-model="form.email"
              type="email"
              class="input"
              placeholder="admin@recovery.local"
              required
            >
          </div>

          <div>
            <label class="label">Contraseña</label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="input pr-10"
                placeholder="••••••••"
                required
              >
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="showPassword = !showPassword"
              >
                <svg
                  v-if="showPassword"
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                  />
                </svg>
                <svg
                  v-else
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
              </button>
            </div>
          </div>

          <div
            v-if="authStore.error"
            class="p-3 bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800 rounded-lg"
          >
            <p class="text-sm text-red-600 dark:text-red-400">
              {{ authStore.error }}
            </p>
          </div>

          <button
            type="submit"
            class="btn btn-primary w-full py-3"
            :disabled="authStore.loading"
          >
            <span v-if="authStore.loading">Iniciando sesión...</span>
            <span v-else>Iniciar Sesión</span>
          </button>
        </form>

        <!-- Demo credentials -->
        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
          <p class="text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">
            Credenciales de prueba:
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
            admin@recovery.local
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
            password123
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'

const router = useRouter()
const authStore = useAuthStore()
const { isDark, toggle } = useTheme()

const form = ref({
  email: 'admin@recovery.local',
  password: 'password123',
})

const showPassword = ref(false)

async function handleLogin() {
  const success = await authStore.login(form.value)
  if (success) {
    router.push('/')
  }
}
</script>