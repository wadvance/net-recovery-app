<template>
  <div class="p-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        Dashboard
      </h1>
      <p class="text-gray-500 dark:text-gray-400 mt-1">
        Resumen general del sistema de recuperación
      </p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <StatCard
        title="Total Clientes"
        :value="stats.overview?.total_clients || 0"
        icon="users"
        color="blue"
      />
      <StatCard
        title="Tareas Completadas"
        :value="stats.overview?.completed_tasks || 0"
        icon="check"
        color="green"
        :subtitle="`${stats.success_rate || 0}% tasa de éxito`"
      />
      <StatCard
        title="Tareas Pendientes"
        :value="stats.overview?.pending_tasks || 0"
        icon="clock"
        color="orange"
      />
      <StatCard
        title="En Progreso"
        :value="stats.overview?.in_progress_tasks || 0"
        icon="spinner"
        color="purple"
      />
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Tasks by Company -->
      <div class="card">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">
          Tareas por Empresa
        </h3>
        <div class="space-y-3">
          <div
            v-for="company in stats.by_company || []"
            :key="company.id"
            class="flex items-center gap-4"
          >
            <div class="flex items-center gap-2 w-32">
              <div
                class="w-3 h-3 rounded-full"
                :style="{ backgroundColor: getCompanyColor(company.code) }"
              />
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ company.name }}</span>
            </div>
            <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
              <div
                class="h-full rounded-full flex items-center justify-end pr-2"
                :style="{
                  width: getCompanyPercentage(company.tasks_count) + '%',
                  backgroundColor: getCompanyColor(company.code),
                }"
              >
                <span class="text-xs text-white font-medium">{{ company.tasks_count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- WhatsApp Stats -->
      <div class="card">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4">
          WhatsApp - Hoy
        </h3>
        <div class="grid grid-cols-3 gap-4">
          <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
              {{ stats.whatsapp_stats?.sent_today || 0 }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Enviados
            </p>
          </div>
          <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-xl">
            <p class="text-2xl font-bold text-green-600 dark:text-green-400">
              {{ stats.whatsapp_stats?.delivered_today || 0 }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Entregados
            </p>
          </div>
          <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-xl">
            <p class="text-2xl font-bold text-red-600 dark:text-red-400">
              {{ stats.whatsapp_stats?.failed_today || 0 }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Fallidos
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Agent Performance -->
    <div class="card">
      <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800 dark:text-white">
          Rendimiento de Agentes
        </h3>
        <router-link
          to="/tasks"
          class="text-sm text-primary-500 hover:text-primary-600 dark:text-primary-400 dark:hover:text-primary-300"
        >
          Ver todas las tareas →
        </router-link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
              <th class="pb-3 font-medium">
                Agente
              </th>
              <th class="pb-3 font-medium text-center">
                Total
              </th>
              <th class="pb-3 font-medium text-center">
                Completadas
              </th>
              <th class="pb-3 font-medium text-center">
                Fallidas
              </th>
              <th class="pb-3 font-medium text-center">
                Tasa de Éxito
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="agent in agents"
              :key="agent.id"
              class="border-b border-gray-50 dark:border-gray-700 last:border-0"
            >
              <td class="py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center">
                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400">
                      {{ agent.name.split(' ').map(n => n[0]).join('') }}
                    </span>
                  </div>
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ agent.name }}</span>
                </div>
              </td>
              <td class="py-3 text-center text-sm text-gray-600 dark:text-gray-400">
                {{ agent.total_tasks }}
              </td>
              <td class="py-3 text-center">
                <span class="badge badge-completed">{{ agent.completed }}</span>
              </td>
              <td class="py-3 text-center">
                <span class="badge badge-failed">{{ agent.failed }}</span>
              </td>
              <td class="py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                  <div class="w-16 bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                    <div
                      class="h-full rounded-full"
                      :class="agent.success_rate >= 70 ? 'bg-green-500' : agent.success_rate >= 40 ? 'bg-orange-500' : 'bg-red-500'"
                      :style="{ width: agent.success_rate + '%' }"
                    />
                  </div>
                  <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ agent.success_rate }}%</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { dashboardApi } from '@/utils/api'
import StatCard from '@/components/StatCard.vue'

const stats = ref({})
const agents = ref([])

onMounted(async () => {
  await Promise.all([fetchStats(), fetchAgentPerformance()])
})

async function fetchStats() {
  try {
    const response = await dashboardApi.stats({ period: 'week' })
    stats.value = response.data
  } catch (error) {
    console.error('Error fetching stats:', error)
  }
}

async function fetchAgentPerformance() {
  try {
    const response = await dashboardApi.agentPerformance({ period: 'week' })
    agents.value = response.data
  } catch (error) {
    console.error('Error fetching agent performance:', error)
  }
}

function getCompanyColor(code) {
  const colors = { TIGO: '#00A3E0', MASMOVIL: '#FF6B00', TELCA: '#0066CC' }
  return colors[code] || '#6B7280'
}

function getCompanyPercentage(count) {
  const total = stats.value.by_company?.reduce((sum, c) => sum + c.tasks_count, 0) || 1
  return Math.round((count / total) * 100)
}
</script>