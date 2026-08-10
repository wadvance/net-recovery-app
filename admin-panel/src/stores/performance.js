import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { performanceApi } from '@/utils/api'

export const usePerformanceStore = defineStore('performance', () => {
  const daily = ref([])
  const summary = ref({
    total_assigned: 0,
    completed: 0,
    pending: 0,
    in_progress: 0,
    success_rate: 0,
  })
  const reports = ref([])
  const loading = ref(false)
  const error = ref(null)
  const generating = ref(false)

  const dailyChart = computed(() => ({
    labels: daily.value.map((d) => d.date),
    success: {
      label: 'Tareas completadas',
      data: daily.value.map((d) => d.completed),
      borderColor: '#10B981',
      backgroundColor: 'rgba(16,185,129,0.1)',
    },
    pending: {
      label: 'Pendientes',
      data: daily.value.map((d) => d.pending),
      borderColor: '#F59E0B',
      backgroundColor: 'rgba(245,158,11,0.1)',
    },
    inProgress: {
      label: 'En progreso',
      data: daily.value.map((d) => d.in_progress),
      borderColor: '#8B5CF6',
      backgroundColor: 'rgba(139,92,246,0.1)',
    },
  }))

  async function fetchDaily(params) {
    loading.value = true
    error.value = null
    try {
      const { data } = await performanceApi.daily(params)
      daily.value = data.daily || []
      summary.value = data.summary || summary.value
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al cargar rendimiento'
    } finally {
      loading.value = false
    }
  }

  async function fetchMyReports(params) {
    loading.value = true
    error.value = null
    try {
      const { data } = await performanceApi.myReports(params)
      reports.value = data.reports || []
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al cargar reportes'
    } finally {
      loading.value = false
    }
  }

  async function generateReport(payload) {
    generating.value = true
    error.value = null
    try {
      const { data } = await performanceApi.generate({
        date_from: payload.dateFrom,
        date_to: payload.dateTo,
        format: payload.format,
        name: payload.name,
        user_id: payload.user_id ?? null,
      })
      await fetchMyReports()
      return data
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al generar reporte'
      throw err
    } finally {
      generating.value = false
    }
  }

  async function downloadReport(id) {
    try {
      const { data, headers } = await performanceApi.downloadReport(id)
      const disposition = headers['content-disposition'] || ''
      let filename = `reporte-rendimiento-${id}.xlsx`
      const match = disposition.match(/filename="?(.*)"?$/i)
      if (match) filename = match[1].replace(/"/g, '')
      const blob = new Blob([data], {
        type: headers['content-type'] || 'application/octet-stream',
      })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      document.body.appendChild(a)
      a.click()
      a.remove()
      URL.revokeObjectURL(url)
    } catch (err) {
      error.value = err.response?.data?.message || 'Error al descargar reporte'
      throw err
    }
  }

  function $reset() {
    daily.value = []
    reports.value = []
    summary.value = {
      total_assigned: 0,
      completed: 0,
      pending: 0,
      in_progress: 0,
      success_rate: 0,
    }
    error.value = null
  }

  return {
    daily,
    summary,
    reports,
    dailyChart,
    loading,
    generating,
    error,
    fetchDaily,
    fetchMyReports,
    generateReport,
    downloadReport,
    $reset,
  }
})
