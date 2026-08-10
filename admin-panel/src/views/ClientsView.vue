<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Clientes
        </h1>
        <p class="text-gray-500 mt-1 dark:text-gray-400">
          Listado de clientes importados
        </p>
      </div>
    </div>

    <div class="card">
      <div class="flex gap-3 mb-4 flex-wrap items-end">
        <div>
          <label class="label">Usuario</label>
          <select
            v-model="userFilterName"
            class="input max-w-[180px]"
            @change="onUserInput"
          >
            <option value="">
              Todos los usuarios
            </option>
            <option
              v-for="a in agents"
              :key="a.id"
              :value="a.name"
            >
              {{ a.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Fecha</label>
          <input
            v-model="dateFilter"
            type="date"
            class="input"
            @change="fetchClients"
          >
        </div>
        <select
          v-model="statusFilter"
          class="input max-w-[160px]"
          @change="fetchClients"
        >
          <option value="">
            Todos los estados
          </option>
          <option value="seguimiento">
            Seguimiento
          </option>
          <option value="por_buscar">
            Por buscar
          </option>
          <option value="retirado">
            Retirado
          </option>
        </select>
        <select
          v-model="companyFilter"
          class="input max-w-[160px]"
          @change="fetchClients"
        >
          <option value="">
            Todas las empresas
          </option>
          <option
            v-for="c in companies"
            :key="c.id"
            :value="c.id"
          >
            {{ c.name }}
          </option>
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 self-end">
          <input
            v-model="unassignedOnly"
            type="checkbox"
            class="rounded"
            @change="refreshClients"
          >
          Solo clientes sin asignar
        </label>
        <button
          class="btn btn-secondary self-end"
          @click="refreshClients"
        >
          Buscar
        </button>
      </div>

      <div class="flex gap-4 mb-4 text-xs text-gray-600 dark:text-gray-300">
        <span class="flex items-center gap-1.5"><span
          class="w-3 h-3 rounded-full inline-block"
          style="background-color:#2563EB"
        /> Seguimiento</span>
        <span class="flex items-center gap-1.5"><span
          class="w-3 h-3 rounded-full inline-block"
          style="background-color:#16A34A"
        /> Retirado</span>
        <span class="flex items-center gap-1.5"><span
          class="w-3 h-3 rounded-full inline-block"
          style="background-color:#DC2626"
        /> Por buscar</span>
      </div>

      <div
        v-if="selected.length > 0"
        class="flex gap-2 mb-4"
      >
        <span class="text-sm text-gray-600 dark:text-gray-300">{{ selected.length }} seleccionados</span>
        <button
          class="btn btn-primary text-sm py-1.5 px-3"
          @click="showAssignModal = true"
        >
          Asignar a agente
        </button>
      </div>

      <div
        v-if="!loading && clients.length === 0 && !loadError"
        class="py-8 text-center text-gray-600 dark:text-gray-300"
      >
        No se encontraron clientes para los filtros seleccionados.
      </div>

      <table
        :key="page + '-' + clients.length"
        class="w-full"
      >
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-b dark:border-gray-700">
            <th class="pb-3">
              <input
                type="checkbox"
                :checked="selected.length && selected.length === clients.length"
                @change="toggleAll"
              >
            </th>
            <th class="pb-3 font-medium">
              N° Suscriptor
            </th>
            <th class="pb-3 font-medium">
              Nombre del Cliente
            </th>
            <th class="pb-3 font-medium">
              Cédula
            </th>
            <th class="pb-3 font-medium">
              Cuenta
            </th>
            <th class="pb-3 font-medium">
              T. Residencia 1
            </th>
            <th class="pb-3 font-medium">
              T. Residencia 2
            </th>
            <th class="pb-3 font-medium">
              Provincia
            </th>
            <th class="pb-3 font-medium">
              Distrito
            </th>
            <th class="pb-3 font-medium">
              Corregimiento
            </th>
            <th class="pb-3 font-medium">
              Barrio
            </th>
            <th class="pb-3 font-medium">
              Empresa
            </th>
            <th class="pb-3 font-medium">
              Usuario
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
            v-for="client in clients"
            :key="client.id"
            class="border-b border-gray-50 last:border-0 dark:border-gray-700"
          >
            <td class="py-3">
              <input
                v-model="selected"
                type="checkbox"
                :value="client.id"
              >
            </td>
            <td class="py-3 text-sm font-mono text-gray-600 dark:text-gray-200">
              {{ client.metadata?.suscriptor || '-' }}
            </td>
            <td class="py-3 text-sm font-medium text-gray-700 dark:text-white">
              {{ client.full_name }}
            </td>
            <td class="py-3 text-sm font-mono text-gray-600 dark:text-gray-200">
              {{ client.metadata?.cedula || client.reference || '-' }}
            </td>
            <td class="py-3 text-sm font-mono text-gray-600 dark:text-gray-200">
              {{ client.order_number }}
            </td>
            <td class="py-3 text-sm font-mono text-gray-600 dark:text-gray-200">
              +{{ client.phone }}
            </td>
            <td class="py-3 text-sm font-mono text-gray-600 dark:text-gray-200">
              +{{ client.alternate_phone || '-' }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-200">
              {{ client.metadata?.provincia || '-' }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-200">
              {{ client.metadata?.distrito || '-' }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-200">
              {{ client.metadata?.corregimiento || '-' }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-200">
              {{ client.metadata?.barrio || '-' }}
            </td>
            <td class="py-3">
              <span
                class="badge"
                :style="{ backgroundColor: getCompanyColor(client.company?.code) + '20', color: getCompanyColor(client.company?.code) }"
              >{{ client.company?.name }}</span>
            </td>
            <td class="py-3">
              <span class="inline-block bg-white text-black px-2 py-0.5 rounded text-sm font-medium border border-gray-200 dark:bg-white dark:text-black">{{ client.assigned_user?.name || '-' }}</span>
            </td>
            <td class="py-3">
              <div class="flex gap-1">
                <button
                  class="px-2 py-1 rounded text-white text-xs font-medium transition-opacity hover:opacity-80"
                  :style="{ backgroundColor: '#2563EB', opacity: client.status === 'seguimiento' ? 1 : 0.35 }"
                  title="Seguimiento"
                  @click="setStatus(client, 'seguimiento')"
                >
                  Seguimiento
                </button>
                <button
                  class="px-2 py-1 rounded text-white text-xs font-medium transition-opacity hover:opacity-80"
                  :style="{ backgroundColor: '#16A34A', opacity: client.status === 'retirado' ? 1 : 0.35 }"
                  title="Retirado"
                  @click="setStatus(client, 'retirado')"
                >
                  Retirado
                </button>
                <button
                  class="px-2 py-1 rounded text-white text-xs font-medium transition-opacity hover:opacity-80"
                  :style="{ backgroundColor: '#DC2626', opacity: client.status === 'por_buscar' ? 1 : 0.35 }"
                  title="Por buscar"
                  @click="setStatus(client, 'por_buscar')"
                >
                  Buscar
                </button>
              </div>
            </td>
            <td class="py-3">
              <router-link
                :to="`/clients/${client.id}`"
                class="text-sm text-primary-500 hover:text-primary-600"
              >
                Ver
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="flex items-center justify-between mt-4 text-sm text-gray-600 dark:text-gray-300">
        <span v-if="loading">Cargando...</span>
        <span
          v-else-if="loadError"
          class="text-red-500"
        >{{ loadError }}</span>
        <span v-else>Mostrando {{ clients.length }} de {{ total }}</span>
        <div class="flex gap-2">
          <button
            class="btn btn-secondary"
            :disabled="page <= 1 || loading"
            @click="changePage(page - 1)"
          >
            ← Anterior
          </button>
          <span class="px-3 py-1.5 text-sm">Pág. {{ page }}</span>
          <button
            class="btn btn-secondary"
            :disabled="page >= totalPages || loading"
            @click="changePage(page + 1)"
          >
            Siguiente →
          </button>
        </div>
      </div>
    </div>

    <!-- Assign Modal -->
    <div
      v-if="showAssignModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          Asignar {{ selected.length }} clientes
        </h3>
        <form
          class="space-y-4"
          @submit.prevent="bulkAssign"
        >
          <div>
            <label class="label">Agente</label>
            <select
              v-model="assignForm.user_id"
              class="input"
              required
            >
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
            <label class="label">Fecha programada</label>
            <input
              v-model="assignForm.scheduled_date"
              type="date"
              class="input"
              required
            >
          </div>
          <div class="flex gap-3">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="showAssignModal.value = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary flex-1"
            >
              Asignar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
// eslint-disable-next-line
import { clientsApi } from '@/utils/api'
import { usersApi } from '@/utils/api'
import { companiesApi } from '@/utils/api'

const clients = ref([])
const companies = ref([])
const agents = ref([])
const statusFilter = ref('')
const companyFilter = ref('')
const userFilterName = ref('')
const userFilterId = ref('')
const dateFilter = ref('')
const unassignedOnly = ref(true)
const selected = ref([])
const showAssignModal = ref(false)
const assignForm = ref({ user_id: '', scheduled_date: '' })
const page = ref(1)
const total = ref(0)
const totalPages = ref(1)
const loading = ref(false)
const loadError = ref('')

onMounted(() => {
  fetchClients(); fetchCompanies(); fetchAgents()
})

async function fetchClients() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await clientsApi.getAll({
      status: statusFilter.value || undefined,
      company_id: companyFilter.value || undefined,
      user_id: userFilterId.value || undefined,
      date: dateFilter.value || undefined,
      unassigned: unassignedOnly.value || undefined,
      per_page: 200,
      page: page.value,
    })
    const body = res.data.data ? res.data : res.data
    clients.value = body.data
    total.value = body.total
    totalPages.value = body.last_page
  } catch (e) {
    loadError.value = e.response?.data?.message || e.message || 'Error al cargar clientes'
  } finally {
    loading.value = false
  }
}

function onUserInput() {
  const typed = userFilterName.value.trim()
  const match = agents.value.find(a => (a.name || '').toLowerCase() === typed.toLowerCase())
  userFilterId.value = match ? match.id : ''
  page.value = 1
  fetchClients()
}

function refreshClients() {
  page.value = 1
  fetchClients()
}

async function setStatus(client, status) {
  try {
    await clientsApi.updateStatus(client.id, status)
    client.status = status
    const task = client.tasks?.find(t => ['assigned', 'in_progress'].includes(t.status))
    if (task) task.status = status === 'retirado' ? 'completed' : status === 'seguimiento' ? 'in_progress' : 'assigned'
  } catch (e) {
    alert(e.response?.data?.message || 'Error al actualizar el estado')
  }
}

function changePage(newPage) {
  page.value = newPage
  selected.value = []
  fetchClients()
}
async function fetchCompanies() {
  const res = await companiesApi.getAll()
  companies.value = res.data.data || res.data
}
async function fetchAgents() {
  const res = await usersApi.agentsList()
  agents.value = res.data
}
function toggleAll(e) {
  selected.value = e.target.checked ? clients.value.map(c => c.id) : []
}
async function bulkAssign() {
  await clientsApi.bulkAssign({ client_ids: selected.value, ...assignForm.value })
  showAssignModal.value = false
  selected.value = []
  fetchClients()
}
function getCompanyColor(code) {
  return { TIGO: '#00A3E0', MASMOVIL: '#FF6B00', TELCA: '#0066CC' }[code] || '#6B7280'
}
</script>