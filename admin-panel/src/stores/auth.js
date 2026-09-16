import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  let storedUser = null
  try {
    const raw = localStorage.getItem('user')
    if (raw && raw !== 'undefined') {
      storedUser = JSON.parse(raw)
    } else {
      localStorage.removeItem('user')
    }
  } catch (_) {
    localStorage.removeItem('user')
    storedUser = null
  }

  const user = ref(storedUser)
  const token = ref(localStorage.getItem('token') || '')
  const loading = ref(false)
  const error = ref(null)

  let deviceId = localStorage.getItem('device_id')
  if (!deviceId) {
    deviceId = `device-${(crypto.randomUUID?.() || `d${Date.now()}${Math.random().toString(16).slice(2)}`)}`
    localStorage.setItem('device_id', deviceId)
  }

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isSupervisor = computed(() => ['admin', 'supervisor'].includes(user.value?.role))

  async function login(credentials) {
    loading.value = true
    error.value = null
    try {
      const response = await authApi.login({ ...credentials, device_name: deviceId })
      const { user: userData, token: tokenValue } = response.data

      user.value = userData
      token.value = tokenValue

      localStorage.setItem('user', JSON.stringify(userData))
      localStorage.setItem('token', tokenValue)

      return true
    } catch (err) {
      if (err.isNetworkError || !err.response) {
        error.value = 'No se pudo conectar con el servidor. Verifica tu conexión o intenta de nuevo en unos segundos.'
      } else {
        error.value =
          err.response?.data?.message ||
          err.response?.data?.errors?.email?.[0] ||
          'Error al iniciar sesión'
      }
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    try {
      await authApi.logout()
    } catch (e) {
      // Ignore errors
    } finally {
      user.value = null
      token.value = ''
      localStorage.removeItem('user')
      localStorage.removeItem('token')
    }
  }

  async function fetchUser() {
    try {
      const response = await authApi.getUser()
      user.value = response.data
      localStorage.setItem('user', JSON.stringify(response.data))
    } catch (e) {
      logout()
    }
  }

  async function updateProfile(data) {
    const response = await authApi.updateProfile(data)
    user.value = response.data
    localStorage.setItem('user', JSON.stringify(response.data))
  }

  return {
    user,
    token,
    loading,
    error,
    isAuthenticated,
    isAdmin,
    isSupervisor,
    login,
    logout,
    fetchUser,
    updateProfile,
  }
})