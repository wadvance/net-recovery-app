<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Tareas
        </h1>
        <p class="text-gray-500 mt-1 dark:text-gray-400">
          Tareas agrupadas por fecha y usuario con el listado completo de cada cliente
        </p>
      </div>
      <div class="flex gap-3">
        <button
          v-if="isAdminOrSupervisor"
          class="btn btn-secondary"
          @click="openAutoModal"
        >
          ⚡ Auto-asignar
        </button>
      </div>
    </div>

    <div class="card">
      <div class="flex gap-3 mb-6 flex-wrap items-end">
        <div>
          <label class="label">Fecha</label>
          <input
            v-model="selectedDate"
            type="date"
            class="input"
            @change="selectDate(selectedDate)"
          >
        </div>
        <div>
          <label class="label">Buscar (Agente)</label>
          <select
            v-model="agentFilterName"
            class="input max-w-xs"
            @change="onAgentInput"
            :disabled="isAgent"
          >
            <option
              v-if="isAgent"
              :value="authStore.user?.name || ''"
            >
              {{ authStore.user?.name }}
            </option>
            <template v-else>
              <option value="">
                Todos los agentes
              </option>
              <option
                v-for="a in agents"
                :key="a.id"
                :value="a.name"
              >
                {{ a.name }}
              </option>
            </template>
          </select>
        </div>
        <div>
          <label class="label">Estado</label>
          <select
            v-model="statusFilter"
            class="input"
          >
            <option value="">
              Todos
            </option>
            <option value="pending">
              Pendiente
            </option>
            <option value="assigned">
              Asignado
            </option>
            <option value="in_progress">
              En Progreso
            </option>
            <option value="completed">
              Completado
            </option>
            <option value="failed">
              Fallido
            </option>
          </select>
        </div>
        <button
          class="btn btn-secondary"
          @click="fetchTasks"
        >
          Filtrar
        </button>
      </div>

      <div class="flex items-center gap-2 mb-4 text-sm text-gray-600 dark:text-gray-300 flex-wrap">
        <span>Fechas con tareas:</span>
        <button
          v-for="date in availableDates"
          :key="date"
          class="px-3 py-1 rounded-full border text-xs font-medium transition-colors"
          :class="date === selectedDate ? 'bg-primary-500 text-white border-primary-500' : 'hover:bg-gray-100 dark:hover:bg-gray-700 border-gray-300 dark:border-gray-600 dark:text-gray-200'"
          @click="selectDate(date)"
        >
          {{ formatShortDate(date) }}
        </button>
        <span
          v-if="!availableDates.length"
          class="text-gray-400"
        >Sin datos</span>
      </div>
    </div>

    <!-- Pool de clientes sin asignar -->
    <div
      v-if="isAdminOrSupervisor"
      class="card mt-6"
    >
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-bold text-gray-800 dark:text-white">
            Clientes sin asignar
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Listado completo de clientes pendientes para distribuir por usuario
          </p>
        </div>
        <div class="flex gap-2 items-end">
          <div>
            <label class="label">Asignar a usuario</label>
            <select
              v-model="assignForm.userId"
              class="input"
            >
              <option value="">
                Seleccionar usuario...
              </option>
              <option
                v-for="a in agents"
                :key="a.id"
                :value="a.id"
              >
                {{ a.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Fecha programada</label>
            <input
              v-model="assignForm.date"
              type="date"
              class="input"
            >
          </div>
          <button
            class="btn btn-primary"
            :disabled="poolAssigning || !selectedPool.length || !assignForm.userId"
            @click="assignSelectedPool"
          >
            {{ poolAssigning ? 'Asignando...' : (`Asignar (${selectedPool.length})`) }}
          </button>
        </div>
      </div>

      <div class="flex gap-2 mb-3 flex-wrap items-end">
        <div>
          <label class="label">Empresa</label>
          <select
            v-model="poolCompanyFilter"
            class="input max-w-[160px]"
            @change="fetchPool"
          >
            <option value="">
              Todas
            </option>
            <option
              v-for="c in companies"
              :key="c.id"
              :value="c.id"
            >
              {{ c.name }}
            </option>
          </select>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 self-end pb-1">
          {{ poolClients.length }} clientes sin asignar
        </div>
        <button
          class="btn btn-secondary self-end"
          @click="fetchPool"
        >
          Recargar
        </button>
      </div>

      <div
        v-if="poolLoading"
        class="py-6 text-center text-gray-500"
      >
        Cargando clientes...
      </div>
      <div
        v-else-if="!poolClients.length"
        class="py-6 text-center text-gray-500"
      >
        No hay clientes sin asignar.
      </div>
      <div
        v-else
        class="overflow-x-auto"
      >
        <table class="w-full">
          <thead>
            <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-b dark:border-gray-700">
              <th class="pb-3">
                <input
                  type="checkbox"
                  :checked="selectedPool.length && selectedPool.length === poolClients.length"
                  @change="toggleAllPool"
                >
              </th>
              <th class="pb-3 font-medium">
                Suscriptor
              </th>
              <th class="pb-3 font-medium">
                Nombre del cliente
              </th>
              <th class="pb-3 font-medium">
                Teléfono
              </th>
              <th class="pb-3 font-medium">
                Empresa
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="client in poolClients"
              :key="client.id"
              class="border-b border-gray-50 last:border-0 dark:border-gray-700"
            >
              <td class="py-2">
                <input
                  v-model="selectedPool"
                  type="checkbox"
                  :value="client.id"
                >
              </td>
              <td class="py-2 text-sm font-mono text-gray-600 dark:text-gray-200">
                {{ client.metadata?.suscriptor || '-' }}
              </td>
              <td class="py-2 text-sm font-medium text-gray-700 dark:text-white">
                {{ client.full_name }}
              </td>
              <td class="py-2 text-sm font-mono text-gray-600 dark:text-gray-200">
                +{{ client.phone }}
              </td>
              <td class="py-2">
                <span
                  class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                  :style="{ backgroundColor: getCompanyColor(client.company?.code) + '20', color: getCompanyColor(client.company?.code) }"
                >{{ client.company?.name }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Groups by date -->
    <div
      v-if="loading"
      class="mt-6 card p-8 text-center text-gray-500"
    >
      Cargando tareas...
    </div>

    <div
      v-else-if="loadError"
      class="mt-6 card p-8 text-center"
    >
      <p class="text-red-600 font-medium">
        Error al cargar tareas
      </p>
      <p class="text-gray-500 text-sm mt-1">
        {{ loadError }}
      </p>
    </div>

    <div
      v-else-if="!tasks.length && !loadedOnce"
      class="mt-6 card p-12 text-center"
    >
      <p class="text-xl font-semibold text-gray-800 dark:text-white mb-2">
        Gestión de tareas por día
      </p>
      <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
        <template v-if="isAdminOrSupervisor">
          Usa <strong>Auto-asignar</strong> para repartir las tareas pendientes entre los usuarios por empresa.
        </template>
        <template v-else>
          Estas son las tareas asignadas para tu fecha. Al completar, utiliza el módulo WhatsApp para enviar mensaje a tus clientes.
        </template>
        Cada usuario verá el detalle del día con la ubicación (mapa) de cada cliente.
      </p>
      <button
        v-if="isAdminOrSupervisor"
        class="btn btn-primary"
        @click="openAutoModal"
      >
        ⚡ Auto-asignar tareas
      </button>
    </div>

    <div
      v-else-if="!tasks.length"
      class="mt-6 card p-8 text-center text-gray-500"
    >
      No hay tareas para mostrar.
    </div>

    <div
      v-else
      class="mt-6"
    >
      <div
        v-for="dateGroup in groupedByDate"
        :key="dateGroup.date"
        class="mt-6"
      >
        <div class="flex items-center gap-3 mb-4">
          <h2 class="text-xl font-bold text-gray-800 dark:text-white">
            {{ formatLongDate(dateGroup.date) }}
          </h2>
          <span class="text-sm text-gray-500 dark:text-gray-400">
            ({{ dateGroup.tasks.length }} tareas)
          </span>
        </div>

        <!-- Users of the date -->
        <div
          v-for="userGroup in dateGroup.users"
          :key="userGroup.userId"
          class="card mb-4 overflow-hidden"
        >
          <button
            class="w-full flex items-center justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            @click="toggleUser(dateGroup.date, userGroup.userId)"
          >
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 bg-primary-100 dark:bg-primary-900 rounded-full flex items-center justify-center">
                <span class="text-primary-700 dark:text-primary-300 font-semibold text-sm">{{ initials(userGroup.userName) }}</span>
              </div>
              <div class="text-left">
                <p class="font-semibold text-gray-800 dark:text-white">
                  {{ userGroup.userName }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ userGroup.tasks.length }} tareas
                </p>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <span class="text-xs text-gray-500 dark:text-gray-400">{{ userGroup.assignedCount }}/{{ userGroup.tasks.length }} asignadas</span>
              <svg
                class="w-5 h-5 text-gray-400 transition-transform"
                :class="isUserOpen(dateGroup.date, userGroup.userId) ? 'rotate-180' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </div>
          </button>

          <div v-if="isUserOpen(dateGroup.date, userGroup.userId)">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-t border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                  <th class="px-4 py-2.5 font-medium">
                    N° Suscriptor
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Nombre del Cliente
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Cedula
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Cuenta
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    T. Residencia 1
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    T. Residencia 2
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Provincia
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Distrito
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Corregimiento
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Barrio
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Estado
                  </th>
                  <th
                    v-if="isAdminOrSupervisor"
                    class="px-4 py-2.5 font-medium"
                  >
                    Agente
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Mapa
                  </th>
                  <th class="px-4 py-2.5 font-medium">
                    Ver
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="task in filteredTasks(userGroup)"
                  :key="task.id"
                  class="border-t border-gray-50 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-200">
                    {{ task.client?.metadata?.suscriptor || '-' }}
                  </td>
                  <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                    {{ task.client?.full_name || '-' }}
                  </td>
                  <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300">
                    {{ task.client?.metadata?.cedula || task.client?.reference || '-' }}
                  </td>
                  <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300">
                    {{ task.client?.order_number || '-' }}
                  </td>
                  <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300">
                    {{ task.client?.phone || '-' }}
                  </td>
                  <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-300">
                    {{ task.client?.alternate_phone || '-' }}
                  </td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {{ task.client?.metadata?.provincia || '-' }}
                  </td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {{ task.client?.metadata?.distrito || '-' }}
                  </td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {{ task.client?.metadata?.corregimiento || '-' }}
                  </td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                    {{ task.client?.metadata?.barrio || '-' }}
                  </td>
                  <td class="px-4 py-3">
                    <span
                      :class="'badge-' + task.status"
                      class="badge"
                    >{{ statusLabel(task.status) }}</span>
                  </td>
                  <td
                    v-if="isAdminOrSupervisor"
                    class="px-4 py-3"
                  >
                    <select
                      class="input !py-1 !px-2 text-xs"
                      :value="task.assignee?.id || ''"
                      :disabled="assigningTask === task.id"
                      @change="changeAssignee(task, $event.target.value)"
                    >
                      <option
                        value=""
                        disabled
                      >
                        (sin agente)
                      </option>
                      <option
                        v-for="a in agents"
                        :key="a.id"
                        :value="a.id"
                      >
                        {{ a.name }}
                      </option>
                    </select>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex gap-2 items-center">
                      <a
                        :href="mapsLink(task, 'google')"
                        target="_blank"
                        rel="noopener"
                        class="px-2 py-1 rounded text-xs text-white font-medium"
                        style="background-color:#4285F4"
                        title="Abrir en Google Maps"
                      >
                        Google Maps
                      </a>
                      <a
                        :href="mapsLink(task, 'waze')"
                        target="_blank"
                        rel="noopener"
                        class="px-2 py-1 rounded text-xs text-white font-medium"
                        style="background-color:#33CCFF"
                        title="Abrir en Waze"
                      >
                        Waze
                      </a>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <router-link
                      :to="`/tasks/${task.id}`"
                      class="text-sm text-primary-500 font-medium"
                    >
                      Ver
                    </router-link>
                  </td>
                </tr>
              </tbody>
            </table>

            <div
              v-if="userGroup.tasks.length > (visibleCounts.value[userGroup.userId] ?? 250)"
              class="px-4 py-2 text-center"
            >
              <button
                class="btn btn-secondary text-sm"
                @click="showMore(dateGroup.date, userGroup.userId)"
              >
                Cargar más
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Auto-assign Modal -->
    <div
      v-if="showAutoModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          Auto-asignar tareas pendientes
        </h3>
        <form
          class="space-y-4"
          @submit.prevent="confirmAutoAssign"
        >
          <div>
            <label class="label">Empresas (múltiple)</label>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="c in companies"
                :key="c.id"
                class="flex items-center gap-1.5 text-sm cursor-pointer select-none text-gray-800 dark:text-gray-200"
              >
                <input
                  v-model="autoForm.company_ids"
                  type="checkbox"
                  :value="c.id"
                  class="rounded text-primary-600 dark:bg-gray-700 dark:border-gray-600"
                >
                {{ c.name }}
              </label>
            </div>
            <p class="text-xs text-gray-400 mt-1">
              Sin selección = todas las empresas (se mezclan al azar)
            </p>
          </div>
          <div>
            <label class="label">Fecha programada</label>
            <input
              v-model="autoForm.scheduled_date"
              type="date"
              class="input"
              required
              @change="updateAutoCounts"
            >
          </div>
          <div>
            <label class="label">Agente</label>
            <select
              v-model="autoForm.user_id"
              class="input"
            >
              <option value="">
                Todos los agentes
              </option>
              <option
                v-for="agent in agents"
                :key="agent.id"
                :value="agent.id"
              >
                {{ agent.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Tareas por agente (máx)</label>
            <input
              v-model="autoForm.tasks_per_agent"
              type="number"
              class="input"
              min="1"
              max="10000"
              placeholder="Ej. 10 (opcional)"
            >
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input
              v-model="autoForm.redistribute"
              type="checkbox"
              class="rounded"
            >
            Redistribuir también tareas ya asignadas (repartir de nuevo)
          </label>

          <div>
            <label class="label">Usuarios y tareas en esta fecha</label>
            <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
              <div
                v-for="row in autoCounts"
                :key="row.key"
              >
                <button
                  type="button"
                  class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-left"
                  @click="toggleAutoUser(row.key)"
                >
                  <span class="flex items-center gap-2 font-medium text-gray-800 dark:text-white">
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        :d="expandedAuto[row.key] ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"
                      />
                    </svg>
                    {{ row.name }}
                  </span>
                  <span
                    class="text-xs px-2 py-0.5 rounded-full"
                    :class="row.count ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                  >
                    {{ row.count }} clientes
                  </span>
                </button>
                <div
                  v-if="expandedAutoOpen[row.key]"
                  class="px-4 pb-2 max-h-40 overflow-y-auto"
                >
                  <div
                    v-for="c in row.clients"
                    :key="c.id"
                    class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-300 py-0.5"
                  >
                    <span class="truncate pr-2">{{ c.full_name }}</span>
                    <span class="text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ c.phone }}</span>
                  </div>
                </div>
              </div>
              <div
                v-if="!autoCounts.length"
                class="px-3 py-2 text-sm text-gray-400"
              >
                No hay tareas para esta fecha
              </div>
            </div>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="showAutoModal = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary flex-1"
              :disabled="assigning"
            >
              {{ assigning ? 'Asignando...' : 'Asignar' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { tasksApi, companiesApi, usersApi, clientsApi } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const isAdminOrSupervisor = computed(() => ['admin', 'supervisor'].includes(authStore.user?.role))
const isAgent = computed(() => authStore.user?.role === 'agent')

const tasks = ref([])
const companies = ref([])
const agents = ref([])
const agentFilterName = ref('')
const agentFilterId = ref('')
const statusFilter = ref('')
const selectedDate = ref('')
const expanded = ref({})
const visibleCounts = ref({})
const showAutoModal = ref(false)
const assigning = ref(false)
const loading = ref(false)
const loadError = ref('')
const loadedOnce = ref(false)
const assigningTask = ref(null)
const autoCounts = ref([])
const expandedAuto = ref({})
const poolClients = ref([])
const poolLoading = ref(false)
const poolCompanyFilter = ref('')
const selectedPool = ref([])
const poolAssigning = ref(false)
const assignForm = ref({ userId: '', date: '' })
const autoForm = ref({
  company_ids: [],
  scheduled_date: new Date().toISOString().split('T')[0],
  tasks_per_agent: '',
  user_id: '',
  redistribute: false,
})

onMounted(async () => {
  await fetchCompanies()
  await fetchAgents()
  await fetchPool()
  if (isAgent.value) {
    agentFilterName.value = authStore.user?.name || ''
    agentFilterId.value = authStore.user?.id || ''
    await fetchTasks()
    const today = new Date().toISOString().split('T')[0]
    if (availableDates.value.includes(today)) selectedDate.value = today
    else if (availableDates.value.length) selectedDate.value = availableDates.value[availableDates.value.length - 1]
    expandDateGroups(selectedDate.value)
  }
})

async function fetchAgents() {
  try {
    const res = await usersApi.agentsList()
    agents.value = res.data || res.data.data || []
  } catch (e) {}
}

async function fetchCompanies() {
  try {
    const res = await companiesApi.getAll()
    companies.value = res.data.data || res.data
    if (companies.value.length) {
      autoForm.value.company_ids = companies.value.map(c => c.id)
    }
  } catch (e) {}
}

async function fetchPool() {
  poolLoading.value = true
  try {
    const params = {
      unassigned: true,
      company_id: poolCompanyFilter.value || undefined,
      per_page: 500,
    }
    const res = await clientsApi.getAll(params)
    poolClients.value = res.data.data || res.data || []
  } catch (e) {
    poolClients.value = []
  } finally {
    poolLoading.value = false
  }
}

function toggleAllPool(e) {
  selectedPool.value = e.target.checked ? poolClients.value.map(c => c.id) : []
}

async function assignSelectedPool() {
  if (!selectedPool.value.length) return
  const userId = assignForm.value.userId
  const scheduledDate = assignForm.value.date
  if (!userId) {
    alert('Selecciona un usuario')
    return
  }
  if (!scheduledDate) {
    alert('Selecciona la fecha programada')
    return
  }
  poolAssigning.value = true
  try {
    const res = await clientsApi.bulkAssign({
      client_ids: selectedPool.value,
      user_id: userId,
      scheduled_date: scheduledDate,
    })
    alert(`Se asignaron ${res.data?.assigned ?? selectedPool.value.length} de ${selectedPool.value.length} clientes`)
    selectedPool.value = []
    await fetchPool()
    await fetchAgents()
    fetchTasks()
  } catch (e) {
    alert(e.response?.data?.message || e.message || 'Error al asignar')
  } finally {
    poolAssigning.value = false
  }
}

function getCompanyColor(code) {
  return { TIGO: '#00A3E0', MASMOVIL: '#FF6B00', TELCA: '#0066CC' }[code] || '#6B7280'
}

async function fetchTasks() {
  loading.value = true
  loadError.value = ''
  try {
    const params = {
      status: statusFilter.value || undefined,
      assigned_to: agentFilterId.value || undefined,
      date: selectedDate.value || undefined,
      per_page: 10000,
    }
    const res = await tasksApi.getAll(params)
    tasks.value = res.data.data || res.data
    loadedOnce.value = true
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'Error al cargar tareas'
    tasks.value = []
  } finally {
    loading.value = false
  }
}

function onAgentInput() {
  const match = agents.value.find(a => a.name === agentFilterName.value)
  agentFilterId.value = match ? match.id : ''
  selectedDate.value = ''
  fetchTasks().then(() => {
    const today = new Date().toISOString().split('T')[0]
    if (!agentFilterId.value) {
      if (availableDates.value.includes(today)) selectedDate.value = today
      else if (availableDates.value.length) selectedDate.value = availableDates.value[availableDates.value.length - 1]
    }
    expandDateGroups(selectedDate.value)
  })
}

function selectDate(date) {
  selectedDate.value = date
  fetchTasks().then(() => expandDateGroups(date))
}

function expandDateGroups(date) {
  const groups = groupedByDate.value
  for (const dateGroup of groups) {
    if (date && dateGroup.date !== date) continue
    for (const userGroup of dateGroup.users) {
      expanded.value[dateGroup.date + ':' + userGroup.userId] = true
    }
  }
}

function updateAutoCounts() {
  const date = autoForm.value.scheduled_date
  if (!date) {
    autoCounts.value = []
    return
  }
  const map = new Map()
  for (const t of tasks.value) {
    if (normalizeDate(t.scheduled_date) !== date) continue
    const key = t.assignee?.id ?? 'sin-asignar'
    if (!map.has(key)) {
      map.set(key, {
        key,
        name: t.assignee?.name || 'Sin asignar',
        count: 0,
        clients: [],
      })
    }
    const row = map.get(key)
    row.count++
    if (t.client && !row.clients.some(c => c && c.id === t.client.id)) {
      row.clients.push(t.client)
    }
  }
  autoCounts.value = [...map.values()].sort((a, b) => (a.name === 'Sin asignar' ? 1 : b.name === 'Sin asignar' ? -1 : a.name.localeCompare(b.name)))
}

function toggleAutoUser(key) {
  expandedAuto.value[key] = !expandedAuto.value[key]
}

function normalizeDate(value) {
  if (!value) return null
  if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10)
  const m = value.match(/^(\d{2})\/(\d{2})\/(\d{4})/)
  if (m) return `${m[3]}-${m[2]}-${m[1]}`
  return value.slice(0, 10)
}

const availableDates = computed(() => {
  const set = new Set()
  for (const t of tasks.value) {
    const d = normalizeDate(t.scheduled_date)
    if (d) set.add(d)
  }
  return [...set].sort()
})

const groupedByDate = computed(() => {
  const source = selectedDate.value
    ? tasks.value.filter(t => normalizeDate(t.scheduled_date) === selectedDate.value)
    : tasks.value
  const byDate = new Map()
  for (const t of source) {
    const date = normalizeDate(t.scheduled_date) || 'sin-fecha'
    if (!byDate.has(date)) byDate.set(date, [])
    byDate.get(date).push(t)
  }
  return [...byDate.entries()].sort((a, b) => b[0].localeCompare(a[0])).map(([date, dateTasks]) => {
    const byUser = new Map()
    for (const t of dateTasks) {
      const userId = t.assignee?.id ?? 0
      if (!byUser.has(userId)) byUser.set(userId, { userId, userName: t.assignee?.name || 'Sin asignar', tasks: [] })
      byUser.get(userId).tasks.push(t)
    }
    return {
      date,
      tasks: dateTasks,
      users: [...byUser.values()]
        .sort((a, b) => a.userName.localeCompare(b.userName))
        .map(u => ({ ...u, assignedCount: u.tasks.filter(t => t.status === 'assigned' || t.status === 'in_progress').length })),
    }
  })
})

function filteredTasks(userGroup) {
  const visible = visibleCounts.value[userGroup.userId] ?? 250
  return userGroup.tasks.slice(0, visible)
}

function showMore(date, userId) {
  const key = date + ':' + userId
  visibleCounts.value[key] = (visibleCounts.value[key] ?? 250) + 250
}

function isUserOpen(date, userId) {
  return expanded.value[date + ':' + userId]
}

function toggleUser(date, userId) {
  const key = date + ':' + userId
  expanded.value[key] = !expanded.value[key]
}

async function changeAssignee(task, userId) {
  if (!userId) return
  if (confirm(`¿Asignar la tarea de ${task.client?.full_name} a este agente?`)) {
    assigningTask.value = task.id
    try {
      await tasksApi.assign(task.id, { user_id: userId, scheduled_date: task.scheduled_date || undefined })
      await fetchTasks()
      expandDateGroups(selectedDate.value)
    } catch (e) {
      alert('Error al asignar: ' + (e.response?.data?.message || e.message || e))
    } finally {
      assigningTask.value = null
    }
  }
}

function initials(name) {
  return (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

function clientAddress(task) {
  const m = task.client?.metadata || {}
  const parts = [m.barrio, m.corregimiento, m.distrito, m.provincia, task.client?.address]
    .filter(Boolean)
  return parts.join(', ') || task.client?.address || task.client?.full_name || ''
}

function mapsLink(task, provider) {
  const query = encodeURIComponent(clientAddress(task))
  if (provider === 'waze') {
    return `https://waze.com/ul?q=${query}&navigate=yes`
  }
  return `https://www.google.com/maps/search/?api=1&query=${query}`
}

const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

function formatLongDate(date) {
  const d = new Date(String(date || '') + 'T00:00:00')
  if (isNaN(d.getTime())) return 'Sin fecha'
  return `${d.getDate()} de ${MONTHS[d.getMonth()]} de ${d.getFullYear()}`
}

function formatShortDate(date) {
  const d = new Date(String(date || '') + 'T00:00:00')
  if (isNaN(d.getTime())) return 'Sin fecha'
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`
}

function statusLabel(s) {
  return { pending: 'Pendiente', assigned: 'Asignado', in_progress: 'En Progreso', completed: 'Completado', failed: 'Fallido' }[s] || s
}

function openAutoModal() {
  autoForm.value.scheduled_date = selectedDate.value || availableDates.value[availableDates.value.length - 1] || ''
  expandedAuto.value = {}
  updateAutoCounts()
  showAutoModal.value = true
}

async function confirmAutoAssign() {
  assigning.value = true
  try {
    const payload = { ...autoForm.value };
    payload.company_ids = payload.company_ids && payload.company_ids.length ? payload.company_ids : [];
    if (!payload.tasks_per_agent) delete payload.tasks_per_agent;
    if (!payload.user_id) delete payload.user_id;
    if (!payload.redistribute) delete payload.redistribute;
    let data = null;
    for (let attempt = 1; attempt <= 3; ++attempt) {
      const res = await fetch('/api/v1/tasks/auto-assign', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('token') || ''}`,
        },
        cache: 'no-store',
        body: JSON.stringify(payload),
      });
      if (!res.ok) throw new Error(`Request failed ${res.status}`);
      data = await res.json();
      break;
    }
    const assigned = data?.assigned ?? 0
    const total = data?.total ?? 0
    showAutoModal.value = false
    const asignDate = autoForm.value.scheduled_date || new Date().toISOString().split('T')[0]
    selectedDate.value = asignDate
    await fetchTasks()
    expandDateGroups(asignDate)
    const byCompany = data?.by_company
    const parts = byCompany && Object.keys(byCompany).length
      ? Object.entries(byCompany).map(([id, n]) => `${companies.value.find(c => String(c.id) === String(id))?.name || 'Empresa ' + id}: ${n}`)
      : []
    alert(assigned > 0
      ? `Se asignaron ${assigned} de ${total} tareas, mezclando las empresas seleccionadas al azar.${parts.length ? '\n' + parts.join('\n') : ''}`
      : `No hay tareas que repartir (${data?.message || 'todas las pendientes ya tienen agente'}). Activa "Redistribuir también las ya asignadas" para repartirlas de nuevo al azar.`)
  } catch (e) {
    alert('Error: ' + (e.message))
  } finally {
    assigning.value = false
  }
}
</script>