import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token') || '')
  const loading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isSupervisor = computed(() => ['admin', 'supervisor'].includes(user.value?.role))

  async function login(credentials) {
    loading.value = true
    error.value = null
    try {
      const response = await authApi.login(credentials)
      const { user: userData, token: tokenValue } = response.data

      user.value = userData
      token.value = tokenValue

      localStorage.setItem('user', JSON.stringify(userData))
      localStorage.setItem('token', tokenValue)

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al iniciar sesión'
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