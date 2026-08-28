<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Escaneos de Equipos</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Códigos escaneados por los agentes con el celular
        </p>
      </div>
      <div class="flex items-center gap-3">
        <input
          v-model="date"
          type="date"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm"
          @change="load"
        />
        <button
          type="button"
          :disabled="downloading"
          class="inline-flex items-center gap-2 px-4 py-2 bg-primary-500 hover:bg-primary-600 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition-colors"
          @click="downloadExcel"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
          </svg>
          {{ downloading ? 'Descargando...' : 'Descargar Excel' }}
        </button>
      </div>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm">
      {{ error }}
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">
      <div v-if="loading" class="p-10 text-center text-gray-400">Cargando escaneos...</div>

      <div v-else-if="scans.length === 0" class="p-10 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <p class="text-gray-500 dark:text-gray-400">
          No hay escaneos registrados para el {{ date }}
        </p>
      </div>

      <table v-else class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-750">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Hora</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Código</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Cuenta</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Empresa</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Agente</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Método</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
          <tr
            v-for="scan in scans"
            :key="scan.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
          >
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ formatTime(scan.scanned_at) }}</td>
            <td class="px-4 py-3 text-sm font-mono font-semibold text-gray-800 dark:text-gray-100">{{ scan.code }}</td>
            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ scan.client?.full_name || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 hidden md:table-cell">{{ scan.client?.order_number || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 hidden lg:table-cell">{{ scan.company?.name || '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ scan.scanner?.name || '—' }}</td>
            <td class="px-4 py-3 hidden md:table-cell">
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="scan.method === 'manual'
                  ? 'bg-amber-100 text-amber-700'
                  : 'bg-green-100 text-green-700'"
              >
                {{ scan.method === 'manual' ? 'Manual' : 'Cámara' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="scans.length" class="mt-3 text-xs text-gray-400">
      {{ scans.length }} escaneo(s) · mostrando máximo 500 registros
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/utils/api'

const scans = ref([])
const date = ref(new Date().toISOString().slice(0, 10))
const loading = ref(false)
const downloading = ref(false)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/scans', { params: { date: date.value } })
    scans.value = data.data ?? []
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al cargar los escaneos'
    scans.value = []
  } finally {
    loading.value = false
  }
}

async function downloadExcel() {
  downloading.value = true
  error.value = null
  try {
    const response = await api.get('/scans/export', {
      params: { date: date.value },
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `escaneos_equipos_${date.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al descargar el archivo'
  } finally {
    downloading.value = false
  }
}

function formatTime(value) {
  if (!value) return '—'
  return value.slice(11, 19)
}

onMounted(load)
</script>
