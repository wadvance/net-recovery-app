<template>
  <div class="p-8">
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
const form = ref({ name: '', code: '', description: '' })

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
  form.value = { name: company.name, code: company.code, description: company.description }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
  form.value = { name: '', code: '', description: '' }
}

async function saveCompany() {
  try {
    if (editing.value) {
      await companiesApi.update(editing.value.id, form.value)
    } else {
      await companiesApi.create(form.value)
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