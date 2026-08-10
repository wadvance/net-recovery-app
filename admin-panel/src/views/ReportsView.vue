<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        Reportes
      </h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <div class="card">
        <label class="label">Tipo</label>
        <select
          v-model="form.type"
          class="input"
        >
          <option value="weekly">
            Semanal
          </option>
          <option value="monthly">
            Mensual
          </option>
          <option value="custom">
            Personalizado
          </option>
        </select>
      </div>
      <div class="card">
        <label class="label">Desde</label>
        <input
          v-model="form.period_start"
          type="date"
          class="input"
        >
      </div>
      <div class="card">
        <label class="label">Hasta</label>
        <input
          v-model="form.period_end"
          type="date"
          class="input"
        >
      </div>
    </div>

    <button
      :disabled="generating"
      class="btn btn-primary mb-8"
      @click="generate"
    >
      {{ generating ? 'Generando...' : '📊 Generar reporte' }}
    </button>

    <div class="card">
      <h3 class="font-semibold mb-4 text-gray-800 dark:text-white">
        Reportes generados
      </h3>
      <table class="w-full">
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-b dark:border-gray-700">
            <th class="pb-3 font-medium">
              Título
            </th>
            <th class="pb-3 font-medium">
              Tipo
            </th>
            <th class="pb-3 font-medium">
              Período
            </th>
            <th class="pb-3 font-medium">
              Estado
            </th>
            <th class="pb-3 font-medium">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="report in reports"
            :key="report.id"
            class="border-b border-gray-50 last:border-0 dark:border-gray-700"
          >
            <td class="py-3 text-sm font-medium text-gray-800 dark:text-white">
              {{ report.title }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-300">
              {{ report.type }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-300">
              {{ report.period_start }} — {{ report.period_end }}
            </td>
            <td class="py-3">
              <span
                :class="report.status === 'completed' ? 'badge-completed' : report.status === 'failed' ? 'badge-failed' : 'badge-pending'"
                class="badge"
              >
                {{ report.status }}
              </span>
            </td>
            <td class="py-3">
              <a
                v-if="report.download_url"
                :href="report.download_url"
                class="text-sm text-primary-500 hover:text-primary-600"
              >
                ⬇️ Descargar
              </a>
              <button
                class="text-sm text-red-500 hover:text-red-600 ml-3"
                @click="deleteReport(report.id)"
              >
                🗑️
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { reportsApi } from '@/utils/api'

const reports = ref([])
const generating = ref(false)
const form = ref({
  type: 'weekly',
  format: 'excel',
  period_start: new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0],
  period_end: new Date().toISOString().split('T')[0],
  company_id: ''
})

onMounted(fetchReports)

async function fetchReports() {
  const res = await reportsApi.getAll()
  reports.value = res.data.data || res.data
}

async function generate() {
  generating.value = true
  try {
    await reportsApi.generate(form.value)
    fetchReports()
  } catch (e) {
    alert('Error: ' + (e.response?.data?.message || e.message))
  } finally {
    generating.value = false
  }
}

async function deleteReport(id) {
  if (!confirm('Eliminar reporte?')) return
  await reportsApi.delete(id)
  fetchReports()
}
</script>