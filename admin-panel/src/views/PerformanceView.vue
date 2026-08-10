<template>
  <div class="p-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        Rendimiento
      </h1>
      <p class="text-gray-500 dark:text-gray-400 mt-1">
        Seguimiento diario de la productividad del equipo.
      </p>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[140px]">
          <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Desde</label>
          <input
            v-model="filters.dateFrom"
            type="date"
            class="input"
            @change="applyFilters"
          >
        </div>
        <div class="flex-1 min-w-[140px]">
          <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Hasta</label>
          <input
            v-model="filters.dateTo"
            type="date"
            class="input"
            @change="applyFilters"
          >
        </div>
        <div
          v-if="canGenerate"
          class="flex items-end"
        >
          <button
            class="btn btn-primary"
            @click="openGenerateModal = true"
          >
            <svg
              class="w-4 h-4 mr-1 -mt-0.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            ><path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 5v14m7-7H5"
            /></svg>
            Generar Reporte
          </button>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
      <StatCard
        title="Total asignados"
        :value="summary.total_assigned || 0"
        icon="users"
        color="blue"
      />
      <StatCard
        title="Completadas"
        :value="summary.completed || 0"
        icon="check"
        color="green"
        :subtitle="`${summary.success_rate || 0}% éxito`"
      />
      <StatCard
        title="Pendientes"
        :value="summary.pending || 0"
        icon="clock"
        color="orange"
      />
      <StatCard
        title="En Progreso"
        :value="summary.in_progress || 0"
        icon="spinner"
        color="purple"
      />
      <StatCard
        :title="`Tasa de Éxito`"
        :value="`${summary.success_rate || 0}%`"
        icon="check"
        color="green"
      />
    </div>

    <!-- Daily Chart -->
    <div class="card mb-8">
      <h3 class="font-semibold text-gray-800 dark:text-white mb-4">
        Tendencia diaria
      </h3>
      <div
        v-if="!chartReady"
        class="h-72 flex items-center justify-center"
      >
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Sin datos para el período seleccionado.
        </p>
      </div>
      <Line
        v-else
        :data="chartData"
        :options="chartOptions"
        class="h-72 w-full"
      />
    </div>

    <!-- My Reports -->
    <div class="card">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          Reportes generados
        </h3>
        <button
          v-if="reports.length"
          :disabled="loadingReports"
          class="text-sm text-primary-500 hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
          @click="refreshReports"
        >
          <svg
            v-if="loadingReports"
            class="w-4 h-4 animate-spin inline mr-1"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          ><circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
          /><path
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H2c0 3.071 1.835 5.64 4.5 6.708z"
          /></svg>
          <span>Actualizar</span>
        </button>
      </div>

      <div
        v-if="loadingReports && !reports.length"
        class="space-y-3 animate-pulse"
      >
        <div
          v-for="i in 3"
          :key="i"
          class="h-12 bg-gray-200 dark:bg-gray-700 rounded"
        />
      </div>

      <div
        v-else-if="!reports.length"
        class="py-10 text-center text-gray-500 dark:text-gray-400"
      >
        Aún no has generado reportes.
      </div>

      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full">
          <thead>
            <tr
              class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700"
            >
              <th class="pb-3 font-medium">
                Reporte
              </th>
              <th class="pb-3 font-medium">
                Periodo
              </th>
              <th class="pb-3 font-medium">
                Formato
              </th>
              <th class="pb-3 font-medium text-center">
                Estado
              </th>
              <th class="pb-3 font-medium text-right">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="report in reports"
              :key="report.id"
              class="border-b border-gray-50 dark:border-gray-700 last:border-0"
            >
              <td class="py-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                  {{ report.name || `Rendimiento #${report.id}` }}
                </span>
              </td>
              <td class="py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ formatDateRange(report.date_from, report.date_to) }}
              </td>
              <td class="py-3 text-sm text-gray-600 dark:text-gray-400">
                {{ (report.format || 'xlsx').toUpperCase() }}
              </td>
              <td class="py-3 text-center">
                <span :class="statusClass(report.status)">{{ statusLabel(report.status) }}</span>
              </td>
              <td class="py-3 text-right">
                <div v-if="report.status === 'ready'">
                  <button
                    :disabled="downloading === report.id"
                    class="text-sm text-primary-500 hover:text-primary-600 dark:text-primary-400"
                    title="Descargar"
                    @click="downloadReport(report.id)"
                  >
                    <span
                      v-if="downloading === report.id"
                      class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"
                    />
                    <svg
                      v-else
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    ><path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 13l4-4m0 0l4 4m-4-4v4"
                    /></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Generate Modal -->
  <transition
    appear
    leave-active-class="dur-300"
  >
    <div
      v-if="openGenerateModal"
      class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
    >
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
            Generar Reporte de Rendimiento
          </h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Define el rango de fechas y el formato del reporte.
          </p>

          <div class="mt-4 space-y-4">
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Desde</label>
              <input
                v-model="reportForm.dateFrom"
                type="date"
                class="input"
              >
            </div>
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Hasta</label>
              <input
                v-model="reportForm.dateTo"
                type="date"
                class="input"
              >
            </div>
            <div>
              <label class="text-xs text-gray-500 dark:text-gray-400 mb-1 block">Formato</label>
              <select
                v-model="reportForm.format"
                class="input"
              >
                <option value="xlsx">
                  XLSX (.xlsx)
                </option>
                <option value="csv">
                  CSV (.csv)
                </option>
              </select>
            </div>
          </div>

          <div class="mt-6 flex items-center justify-end gap-3">
            <button
              class="btn btn-ghost"
              @click="openGenerateModal = false"
            >
              Cancelar
            </button>
            <button
              :disabled="generating"
              class="btn btn-primary"
              @click="createReport"
            >
              <span v-if="generating">Generando…</span>
              <span v-else>Crear Reporte</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'
import { Line } from 'vue-chartjs'
import { useAuthStore } from '@/stores/auth'
import { usePerformanceStore } from '@/stores/performance'
import StatCard from '@/components/StatCard.vue'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
)

const authStore = useAuthStore()
const store = usePerformanceStore()

const canGenerate = computed(() => authStore.isSupervisor)

const today = new Date().toISOString().slice(0, 10)
const d = new Date()
d.setDate(d.getDate() - 6)
const sevenDaysAgo = d.toISOString().slice(0, 10)

const filters = ref({ dateFrom: sevenDaysAgo, dateTo: today })
const reportForm = ref({
  dateFrom: sevenDaysAgo,
  dateTo: today,
  format: 'xlsx',
  name: '',
})
const openGenerateModal = ref(false)
const downloading = ref(null)

const loadingReports = computed(() => store.loading)
const summary = computed(() => store.summary)
const reports = computed(() => store.reports)
const chartData = computed(() => store.dailyChart)
const chartReady = computed(() => store.daily.length > 0)

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  borderWidth: 2,
  plugins: { legend: { position: 'top' } },
  scales: {
    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true } },
    y: { grid: { color: 'rgba(0,0,0,0.05)' }, beginAtZero: true },
  },
}

onMounted(() => {
  fetchDaily()
  fetchReports()
})

async function fetchDaily() {
  await store.fetchDaily({
    date_from: filters.value.dateFrom,
    date_to: filters.value.dateTo,
  })
}

function applyFilters() {
  fetchDaily()
}

async function fetchReports() {
  await store.fetchMyReports()
}

function refreshReports() {
  store.reports = []
  fetchReports()
}

async function createReport() {
  const payload = { ...reportForm.value }
  if (!payload.dateFrom || !payload.dateTo) {
    store.error = 'Debes seleccionar un rango de fechas.'
    return
  }
  try {
    await store.generateReport(payload)
    openGenerateModal.value = false
    reportForm.value = {
      dateFrom: sevenDaysAgo,
      dateTo: today,
      format: 'xlsx',
      name: '',
    }
    await fetchReports()
  } catch (e) {
    // handled in store
  }
}

async function downloadReport(id) {
  downloading.value = id
  try {
    await store.downloadReport(id)
  } catch (e) {
    // handled in store
  } finally {
    downloading.value = null
  }
}

function statusClass(status) {
  const map = {
    pending: 'badge badge-pending',
    processing: 'badge badge-info',
    ready: 'badge badge-completed',
    failed: 'badge badge-failed',
    error: 'badge badge-failed',
  }
  return map[status] || 'badge badge-pending'
}

function statusLabel(status) {
  const map = {
    pending: 'Pendiente',
    processing: 'Procesando',
    ready: 'Listo',
    failed: 'Error',
    error: 'Error',
  }
  return map[status] || status
}

function formatDateRange(dateFrom, dateTo) {
  if (!dateFrom && !dateTo) return '-'
  const f = dateFrom ? dateFrom.slice(0, 10) : ''
  const t = dateTo ? dateTo.slice(0, 10) : ''
  if (!t) return `Desde ${f}`
  if (!f) return `Hasta ${t}`
  return `${f} – ${t}`
}
</script>
