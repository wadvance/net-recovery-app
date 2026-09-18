<template>
  <div class="p-4 lg:p-8">
    <div class="flex items-center justify-between mb-8 flex-wrap gap-2">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          WhatsApp
        </h1>
        <p class="text-gray-500 mt-1 dark:text-gray-400">
          Envío masivo a los clientes de las tareas del día
        </p>
      </div>
      <button
        type="button"
        class="px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 text-xs font-medium hover:bg-red-50 dark:hover:bg-red-900/20"
        @click="clearFileList"
      >
        🗑️ Limpiar lista de archivos
      </button>
    </div>

    <div
      v-if="waStatus && !waStatus.has_session"
      class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-300 dark:border-yellow-700 rounded-xl p-4 text-sm text-yellow-800 dark:text-yellow-200 leading-relaxed"
    >
      <strong>⚠️ Sin sesión de WhatsApp:</strong> los mensajes masivos no llegarán a los clientes hasta que configures tu sesión.
      Cada usuario debe registrar su propia API Key en <strong>Usuarios &gt; Editar</strong>: con <strong>Zavu</strong> basta tu API Key de tu cuenta Zavu;
      con <strong>YCloud</strong> crea tu sesión en <a href="https://ycloud.com" target="_blank" rel="noopener" class="underline font-semibold">ycloud.com</a>
      y agrega además tu número remitente.
      <span v-if="waStatus.phone_number">Número detectado: <strong>{{ waStatus.phone_number }}</strong>.</span>
    </div>
    <div
      v-else-if="waStatus && waStatus.has_session"
      class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-xl px-4 py-3 text-sm text-green-800 dark:text-green-200"
    >
      ✅ Sesión {{ waStatus.provider === 'zavu' ? 'Zavu' : 'YCloud' }} activa<span v-if="waStatus.phone_number"> — los masivos saldrán desde <strong>{{ waStatus.phone_number }}</strong></span><span v-else> — los masivos saldrán desde tu número</span>.
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
            <div>
              <label class="label">Cliente</label>
              <input
                v-model="clientSearch"
                type="text"
                class="input"
                placeholder="Buscar por nombre..."
              >
            </div>
            <div>
              <label class="label">Teléfono</label>
              <input
                v-model="phoneSearch"
                type="text"
                class="input"
                placeholder="Buscar por teléfono..."
              >
            </div>
            <div>
              <label class="label">Empresa</label>
              <select
                v-model="form.company_id"
                class="input"
              >
                <option value="">
                  Todos
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
                      EMPRESA
                    </th>
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
                    <td class="px-3 py-2 whitespace-nowrap">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                        :style="{ backgroundColor: companyColor(c) + '22', color: companyColor(c) }"
                      >
                        {{ companyName(c) }}
                      </span>
                    </td>
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
                      {{ c.alternate_phone ? '+' + c.alternate_phone : '-' }}
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
              Estimado(a) cliente: Reciba un cordial saludo de parte de WODEN PANAMA, empresa encargada de la gestion y recuperacion de equipos a nivel nacional para TIGO PANAMA. Nos permitimos contactarle debido a que hemos recibido una orden de recuperacion de equipos. Con el proposito de coordinar la visita y realizar el proceso de manera agil, segura y conveniente para usted, agradecemos su colaboracion proporcionandonos por este medio su ubicacion en tiempo actual mediante WhatsApp. Agradecemos de antemano su atencion y colaboracion. Saludos cordiales, WODEN PANAMA.
            </p>
            <p class="text-xs text-gray-400 text-right mt-2">
              WhatsApp
            </p>
          </div>
          <div class="mt-4">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
              Destinatarios ({{ selectedClients.length }})
            </p>
            <ul
              v-if="selectedClients.length"
              class="space-y-1.5 max-h-56 overflow-auto"
            >
              <li
                v-for="c in selectedClients"
                :key="c.id"
                class="flex items-center justify-between gap-2 text-xs"
              >
                <span class="text-gray-800 dark:text-gray-100 truncate">
                  {{ c.full_name || '-' }}
                </span>
                <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap font-mono">
                  #{{ c.metadata?.suscriptor || c.order_number || '-' }}
                </span>
                <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap font-mono">
                  +{{ c.phone || '-' }}
                </span>
              </li>
            </ul>
            <p
              v-else
              class="text-xs text-gray-400"
            >
              Selecciona al menos un cliente
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
      <div class="overflow-x-auto">
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
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { whatsappApi, companiesApi, tasksApi, excelApi } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const isAgent = computed(() => authStore.user?.role === 'agent')
const companies = ref([])
const tasks = ref([])
const messages = ref([])
const sending = ref(false)
const selectedByUser = ref({})
const clientSearch = ref('')
const phoneSearch = ref('')
const waStatus = ref(null)
const form = ref({ company_id: '', template_name: 'equipment_recovery_notification', scheduled_date: '' })

onMounted(async () => {
  await fetchCompanies()
  await fetchTasks()
  await fetchMessages()
  await fetchWaStatus()
  form.value.scheduled_date = todayDate()
  if (isAgent.value && authStore.user?.company_id) {
    form.value.company_id = authStore.user.company_id
  } else {
    form.value.company_id = ''
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

async function fetchWaStatus() {
  try {
    const res = await whatsappApi.status()
    waStatus.value = res.data
  } catch {
    waStatus.value = null
  }
}

function normalizeDate(value) {
  if (!value) return null
  if (/^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10)
  const m = value.match(/^(\d{2})\/(\d{2})\/(\d{4})/)
  if (m) return `${m[3]}-${m[2]}-${m[1]}`
  return value.slice(0, 10)
}

function todayDate() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
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
  const nameQ = clientSearch.value.trim().toLowerCase()
  const phoneQ = phoneSearch.value.replace(/\D/g, '').trim()
  for (const t of tasks.value) {
    if (normalizeDate(t.scheduled_date) !== form.value.scheduled_date) continue
    if (!t.client) continue
    if (nameQ && !(t.client.full_name || '').toLowerCase().includes(nameQ)) continue
    const phoneDigits = ((t.client.phone || '') + (t.client.alternate_phone || '')).replace(/\D/g, '')
    if (phoneQ && !phoneDigits.includes(phoneQ)) continue
    const userId = t.assignee?.id ?? 0
    const key = String(userId)
    if (!byUser.has(key)) {
      byUser.set(key, { key, userId, userName: t.assignee?.name || 'Sin asignar', clients: [] })
    }
    const group = byUser.get(key)
    if (!group.clients.some(c => c && c.id === t.client.id)) {
      const clientWithCompany = t.client.company
        ? t.client
        : { ...t.client, company: t.company || null }
      group.clients.push(clientWithCompany)
    }
  }
  const groups = [...byUser.values()].sort((a, b) => (a.userId === 0 ? 1 : b.userId === 0 ? -1 : a.userName.localeCompare(b.userName)))
  for (const g of groups) {
    g.phoneCount = g.clients.filter(c => c.phone).length
  }
  return groups
})

const selectedClients = computed(() => {
  const arr = []
  for (const g of dayGroups.value) {
    if (!selectedByUser.value[g.key]) continue
    for (const c of g.clients) {
      if (c && c.id) arr.push(c)
    }
  }
  return arr
})

const selectedClientIds = computed(() => selectedClients.value.map(c => c.id))

const companyIdOf = (c) => c?.company?.id || c?.company_id

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

const COMPANY_COLORS = { TIGO: '#00A3E0', MASMOVIL: '#FF6B00', TELCA: '#0066CC' }

async function clearFileList() {
  const ok = confirm(
    "¿Limpiar la lista de archivos de Excel subidos?\n\nSe eliminan los registros y archivos del historial para empezar el día siguiente. No se borran clientes ni tareas ya creadas.",
  )
  if (!ok) return
  try {
    const res = await excelApi.clearList()
    alert(res.data?.message || "Lista de archivos limpiada")
  } catch (e) {
    alert("Error al limpiar: " + (e.response?.data?.message || e.message))
  }
}

function companyName(c) {
  if (c?.company?.name) return c.company.name
  const local = companies.value.find(x => x && String(x.id) === String(c?.company_id))
  if (local) return local.name
  return 'Sin empresa'
}

function companyColor(c) {
  if (c?.company?.code) return COMPANY_COLORS[c.company.code] || '#6B7280'
  const local = companies.value.find(x => x && String(x.id) === String(c?.company_id))
  if (local) return COMPANY_COLORS[local.code] || '#6B7280'
  return '#6B7280'
}

async function sendBulk() {
  const clientIds = selectedClientIds.value
  if (!clientIds.length) return
  if (waStatus.value && waStatus.value.has_session === false) {
    alert(waStatus.value.message || 'Configura tu sesión de WhatsApp antes de enviar. Cada usuario necesita su API Key (Zavu o YCloud).')
    return
  }
  sending.value = true
  try {
    let groups
    if (form.value.company_id) {
      const ids = selectedClients.value
        .filter(c => String(companyIdOf(c)) === String(form.value.company_id))
        .map(c => c.id)
      if (!ids.length) return
      groups = [{ companyId: form.value.company_id, ids }]
    } else {
      const byCompany = {}
      for (const c of selectedClients.value) {
        const cid = companyIdOf(c)
        if (!cid) continue
        ;(byCompany[cid] ||= []).push(c.id)
      }
      groups = Object.entries(byCompany).map(([companyId, ids]) => ({ companyId: Number(companyId), ids }))
    }
    if (!confirm(`Enviar mensaje a ${clientIds.length} clientes?`)) return

    const companyNameById = (id) => companies.value.find(c => String(c.id) === String(id))?.name || `Empresa ${id}`
    const lines = []
    const noCompany = selectedClients.value.filter(c => !companyIdOf(c)).length
    if (noCompany) lines.push(`⚠️ ${noCompany} cliente(s) sin empresa no se incluyeron`)
    for (const g of groups) {
      if (!g.companyId || !g.ids.length) continue
      try {
        const res = await whatsappApi.sendBulk({
          company_id: g.companyId,
          client_ids: g.ids,
          template_name: form.value.template_name,
        })
        const d = res.data || {}
        let line = `${companyNameById(g.companyId)}: ${d.sent ?? d.created ?? g.ids.length} enviado(s)`
        if (d.skipped) line += `, ${d.skipped} omitido(s) por ya notificados`
        if (d.failed) line += `, ${d.failed} fallido(s)`
        lines.push(line)
        if (d.errors?.length) lines.push(`   Motivo: ${d.errors[0]}`)
      } catch (err) {
        lines.push(`${companyNameById(g.companyId)}: ERROR — ${err.response?.data?.message || err.message}`)
      }
    }
    alert(lines.join('\n') || 'Sin resultados')
    fetchMessages()
  } catch (e) {
    alert('Error: ' + (e.response?.data?.message || e.message))
  } finally {
    sending.value = false
  }
}
</script>