<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          WhatsApp
        </h1>
        <p class="text-gray-500 mt-1 dark:text-gray-400">
          Envío masivo a los clientes de las tareas del día
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Send Bulk -->
      <div class="card lg:col-span-2">
        <h3 class="font-semibold mb-4 text-gray-800 dark:text-white">
          Destinatarios por fecha
        </h3>
        <form
          class="space-y-4"
          @submit.prevent="sendBulk"
        >
          <div class="flex gap-3 flex-wrap">
            <div v-if="!isAgent">
              <label class="label">Empresa</label>
              <select
                v-model="form.company_id"
                class="input"
                required
              >
                <option value="">
                  Seleccionar
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
            <div v-else>
              <label class="label">Empresa</label>
              <input
                :value="companies.find(c => String(c.id) === String(form.company_id))?.name || companies[0]?.name"
                class="input"
                disabled
              >
            </div>
            <div>
              <label class="label">Fecha</label>
              <input
                v-model="form.scheduled_date"
                type="date"
                class="input"
                required
                @change="selectAllDay"
              >
            </div>
            <div>
              <label class="label">Fechas con tareas</label>
              <div class="flex items-center gap-2 flex-wrap pt-1">
                <button
                  v-for="d in availableDates"
                  :key="d"
                  type="button"
                  class="px-3 py-1 rounded-full border text-xs font-medium"
                  :class="d === form.scheduled_date ? 'bg-primary-500 text-white border-primary-500' : 'hover:bg-gray-100 dark:hover:bg-gray-700 border-gray-300 dark:border-gray-600 dark:text-gray-200'"
                  @click="pickDate(d)"
                >
                  {{ formatShortDate(d) }}
                </button>
                <span
                  v-if="!availableDates.length"
                  class="text-gray-400 text-sm"
                >Sin datos</span>
              </div>
            </div>
          </div>

          <div
            v-for="group in dayGroups"
            :key="group.key"
            class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
          >
            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
              <label class="flex items-center gap-2 text-sm font-medium cursor-pointer text-gray-800 dark:text-white">
                <input
                  v-model="selectedByUser[group.key]"
                  type="checkbox"
                  class="accent-primary-500"
                >
                <span class="text-gray-900 dark:text-white">{{ group.userName }}</span>
                <span class="text-xs text-gray-600 dark:text-gray-300">({{ group.clients.length }})</span>
              </label>
              <span class="text-xs text-gray-600 dark:text-gray-300">{{ group.clients.length }} clientes · {{ group.phoneCount }} con teléfono</span>
            </div>
            <div class="max-h-64 overflow-auto">
              <table class="w-full text-xs">
                <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                  <tr class="text-left text-gray-500 dark:text-gray-400">
                    <th class="px-3 py-2 font-medium">
                      NOMBRE
                    </th>
                    <th class="px-3 py-2 font-medium">
                      CEDULA
                    </th>
                    <th class="px-3 py-2 font-medium">
                      SUSCRIPTOR
                    </th>
                    <th class="px-3 py-2 font-medium">
                      T.RESIDENCIA 1
                    </th>
                    <th class="px-3 py-2 font-medium">
                      T.RESIDENCIA 2
                    </th>
                    <th class="px-3 py-2 font-medium">
                      PROVINCIA
                    </th>
                    <th class="px-3 py-2 font-medium">
                      DISTRITO
                    </th>
                    <th class="px-3 py-2 font-medium">
                      CORREGIMIENTO
                    </th>
                    <th class="px-3 py-2 font-medium">
                      BARRIO
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                  <tr
                    v-for="c in group.clients"
                    :key="c.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                  >
                    <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-100 whitespace-nowrap">
                      {{ c.full_name }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                      {{ c.reference || c.metadata?.cedula || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                      {{ c.metadata?.suscriptor || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                      +{{ c.phone }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                      +{{ c.alternate_phone || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                      {{ c.metadata?.provincia || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                      {{ c.metadata?.distrito || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                      {{ c.metadata?.corregimiento || '-' }}
                    </td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-300">
                      {{ c.metadata?.barrio || '-' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div
            v-if="!dayGroups.length"
            class="text-sm text-gray-400 p-4 border border-dashed border-gray-200 rounded-lg text-center"
          >
            No hay tareas para la fecha seleccionada
          </div>

          <button
            type="submit"
            :disabled="sending || !selectedClientIds.length"
            class="btn btn-primary w-full"
          >
            {{ sending ? 'Enviando...' : `Enviar a ${selectedClientIds.length} clientes` }}
          </button>
        </form>
      </div>

      <!-- Message Preview -->
      <div class="card">
        <h3 class="font-semibold mb-4 text-gray-800 dark:text-white">
          Vista previa del mensaje
        </h3>
        <div class="bg-green-50 dark:bg-green-900/30 rounded-xl p-4">
          <div class="bg-white dark:bg-gray-700 rounded-lg p-3 shadow-sm max-w-xs">
            <p class="text-sm text-gray-800 dark:text-gray-100 leading-relaxed">
              Estimado/a <strong>[Nombre]</strong>, le informamos que el Departamento de Recuperación de Equipos de <strong>[Empresa]</strong> se comunicó con usted respecto al pedido #<strong>[Pedido]</strong>. Un agente se acercará a la dirección registrada para retirar los equipos. Por favor manténgase atento/a a su teléfono. Gracias.
            </p>
            <p class="text-xs text-gray-400 text-right mt-2">
              WhatsApp
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Message History -->
    <div class="card mt-6">
      <h3 class="font-semibold mb-4 text-gray-800 dark:text-white">
        Historial de mensajes
      </h3>
      <table class="w-full">
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-b dark:border-gray-700">
            <th class="pb-3 font-medium">
              Teléfono
            </th>
            <th class="pb-3 font-medium">
              Cliente
            </th>
            <th class="pb-3 font-medium">
              Template
            </th>
            <th class="pb-3 font-medium">
              Estado
            </th>
            <th class="pb-3 font-medium">
              Fecha
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="msg in messages"
            :key="msg.id"
            class="border-b border-gray-50 last:border-0 dark:border-gray-700"
          >
            <td class="py-3 text-sm font-mono text-gray-800 dark:text-gray-100">
              {{ msg.to_phone }}
            </td>
            <td class="py-3 text-sm text-gray-700 dark:text-gray-200">
              {{ msg.client?.full_name || '-' }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-gray-300">
              {{ msg.template_name }}
            </td>
            <td class="py-3">
              <span
                :class="msg.status === 'sent' || msg.status === 'delivered' ? 'badge-completed' : msg.status === 'failed' ? 'badge-failed' : 'badge-pending'"
                class="badge"
              >
                {{ msg.status }}
              </span>
            </td>
            <td class="py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ msg.created_at }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { whatsappApi, companiesApi, tasksApi } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const isAgent = computed(() => authStore.user?.role === 'agent')
const companies = ref([])
const tasks = ref([])
const messages = ref([])
const sending = ref(false)
const selectedByUser = ref({})
const form = ref({ company_id: '', template_name: 'equipment_recovery_notification', scheduled_date: '' })

onMounted(async () => {
  await fetchCompanies()
  await fetchTasks()
  await fetchMessages()
  if (availableDates.value.length) {
    form.value.scheduled_date = availableDates.value[availableDates.value.length - 1]
  }
  if (isAgent.value && authStore.user?.company_id) {
    form.value.company_id = authStore.user.company_id
  } else if (companies.value.length) {
    form.value.company_id = companies.value[0].id
  }
  selectAllDay()
})

async function fetchCompanies() {
  const res = await companiesApi.getAll()
  companies.value = res.data.data || res.data
}

async function fetchTasks() {
  const params = { per_page: 10000 }
  if (isAgent.value) {
    params.assigned_to = authStore.user?.id
  }
  const res = await tasksApi.getAll(params)
  tasks.value = res.data.data || res.data
}

async function fetchMessages() {
  const res = await whatsappApi.getMessages()
  messages.value = res.data.data || res.data
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

const dayGroups = computed(() => {
  const byUser = new Map()
  for (const t of tasks.value) {
    if (normalizeDate(t.scheduled_date) !== form.value.scheduled_date) continue
    const userId = t.assignee?.id ?? 0
    const key = String(userId)
    if (!byUser.has(key)) {
      byUser.set(key, { key, userId, userName: t.assignee?.name || 'Sin asignar', clients: [] })
    }
    const group = byUser.get(key)
    if (t.client && !group.clients.some(c => c && c.id === t.client.id)) {
      group.clients.push(t.client)
    }
  }
  const groups = [...byUser.values()].sort((a, b) => (a.userId === 0 ? 1 : b.userId === 0 ? -1 : a.userName.localeCompare(b.userName)))
  for (const g of groups) {
    g.phoneCount = g.clients.filter(c => c.phone).length
  }
  return groups
})

const selectedClientIds = computed(() => {
  const ids = []
  for (const g of dayGroups.value) {
    if (!selectedByUser.value[g.key]) continue
    for (const c of g.clients) {
      if (c && c.id) ids.push(c.id)
    }
  }
  return ids
})

function selectAllDay() {
  selectedByUser.value = {}
  for (const g of dayGroups.value) selectedByUser.value[g.key] = true
}

function pickDate(d) {
  form.value.scheduled_date = d
  selectAllDay()
}

function formatShortDate(date) {
  const d = new Date(date + 'T00:00:00')
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`
}

async function sendBulk() {
  if (!form.value.company_id) return
  const clientIds = selectedClientIds.value
  if (!clientIds.length) return
  sending.value = true
  try {
    const companyId = isAgent.value ? (form.value.company_id || (companies.value[0]?.id)) : form.value.company_id
    if (!companyId) return
    if (!confirm(`Enviar mensaje a ${clientIds.length} clientes?`)) return

    await whatsappApi.sendBulk({
      company_id: companyId,
      client_ids: clientIds,
      template_name: form.value.template_name,
    })
    alert(`Mensajes procesados para ${clientIds.length} clientes`)
    fetchMessages()
  } catch (e) {
    alert('Error: ' + (e.response?.data?.message || e.message))
  } finally {
    sending.value = false
  }
}
</script>