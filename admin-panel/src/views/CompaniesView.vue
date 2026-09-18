<template>
  <div class="p-4 lg:p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Empresas
        </h1>
        <p class="text-gray-500 mt-1 dark:text-gray-400">
          Gestiona las empresas (Tigo, Más Móvil, Telca)
        </p>
      </div>
      <button
        class="btn btn-primary"
        @click="showModal = true"
      >
        + Nueva Empresa
      </button>
    </div>

    <div class="card">
      <div
        v-if="loading"
        class="text-center py-8"
      >
        <div class="animate-spin w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full mx-auto" />
      </div>
      <div
        v-else-if="companies.length === 0"
        class="text-center py-8 text-gray-500 dark:text-gray-400"
      >
        No hay empresas registradas
      </div>
      <table
        v-else
        class="w-full"
      >
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700">
            <th class="pb-3 font-medium">
              Empresa
            </th>
            <th class="pb-3 font-medium">
              Código
            </th>
            <th class="pb-3 font-medium">
              Plantilla WhatsApp
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
            v-for="company in companies"
            :key="company.id"
            class="border-b border-gray-50 last:border-0 dark:border-gray-700"
          >
            <td class="py-4">
              <div class="flex items-center gap-3">
                <div
                  class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                  :style="{ backgroundColor: getCompanyColor(company.code) }"
                >
                  {{ company.code.substring(0, 1) }}
                </div>
                <span class="font-medium text-gray-700 dark:text-white">{{ company.name }}</span>
              </div>
            </td>
            <td class="py-4 text-sm text-gray-600 dark:text-gray-300">
              {{ company.code }}
            </td>
            <td class="py-4 text-sm text-gray-600 dark:text-gray-300 font-mono">
              {{ company.settings?.whatsapp_template || 'equipment_recovery_notification' }}
            </td>
            <td class="py-4">
              <span
                :class="company.is_active ? 'badge-completed' : 'badge-failed'"
                class="badge"
              >
                {{ company.is_active ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="py-4">
              <button
                class="text-primary-500 hover:text-primary-600 text-sm font-medium"
                @click="editCompany(company)"
              >
                Editar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          {{ editing ? 'Editar' : 'Nueva' }} Empresa
        </h3>
        <form
          class="space-y-4"
          @submit.prevent="saveCompany"
        >
          <div>
            <label class="label">Nombre</label>
            <input
              v-model="form.name"
              type="text"
              class="input"
              required
            >
          </div>
          <div>
            <label class="label">Código</label>
            <input
              v-model="form.code"
              type="text"
              class="input"
              required
            >
          </div>
          <div>
            <label class="label">Descripción</label>
            <textarea
              v-model="form.description"
              class="input"
              rows="2"
            />
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
              📱 Mensaje WhatsApp de esta empresa
            </h4>
            <div class="space-y-3">
              <div>
                <label class="label">Plantilla (YCloud/Meta aprobada)</label>
                <input
                  v-model="form.whatsapp_template"
                  type="text"
                  class="input font-mono text-sm"
                  placeholder="equipment_recovery_notification"
                >
                <p class="text-xs text-gray-400 mt-1">
                  Nombre exacto de la plantilla aprobada en YCloud para esta empresa. Así TIGO usa la suya y MAS MOVIL la suya.
                </p>
              </div>
              <div>
                <label class="label">Texto libre (vista previa y respaldo)</label>
                <textarea
                  v-model="form.whatsapp_text"
                  class="input text-sm"
                  rows="5"
                  placeholder="Texto que reciben los clientes de esta empresa..."
                />
              </div>
            </div>
          </div>
          <div class="flex gap-3 pt-2">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="closeModal"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary flex-1"
            >
              Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { companiesApi } from '@/utils/api'

const companies = ref([])
const loading = ref(true)
const showModal = ref(false)
const editing = ref(null)
const form = ref({ name: '', code: '', description: '', whatsapp_template: '', whatsapp_text: '' })

onMounted(fetchCompanies)

async function fetchCompanies() {
  try {
    const res = await companiesApi.getAll()
    companies.value = res.data.data || res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function editCompany(company) {
  editing.value = company
  form.value = {
    name: company.name,
    code: company.code,
    description: company.description,
    whatsapp_template: company.settings?.whatsapp_template || '',
    whatsapp_text: company.settings?.whatsapp_text || '',
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
  form.value = { name: '', code: '', description: '', whatsapp_template: '', whatsapp_text: '' }
}

async function saveCompany() {
  try {
    const settings = { ...((editing.value?.settings) || {}) }
    if (form.value.whatsapp_template?.trim()) settings.whatsapp_template = form.value.whatsapp_template.trim()
    else delete settings.whatsapp_template
    if (form.value.whatsapp_text?.trim()) settings.whatsapp_text = form.value.whatsapp_text.trim()
    else delete settings.whatsapp_text
    const payload = {
      name: form.value.name,
      code: form.value.code,
      description: form.value.description,
      settings,
    }
    if (editing.value) {
      await companiesApi.update(editing.value.id, payload)
    } else {
      await companiesApi.create(payload)
    }
    closeModal()
    fetchCompanies()
  } catch (e) {
    alert(e.response?.data?.message || 'Error guardando')
  }
}

function getCompanyColor(code) {
  const colors = { TIGO: '#00A3E0', MASMOVIL: '#FF6B00', TELCA: '#0066CC' }
  return colors[code] || '#6B7280'
}
</script>