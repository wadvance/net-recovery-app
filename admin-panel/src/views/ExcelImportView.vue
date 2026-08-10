<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Importar Excel
        </h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">
          Sube un archivo Excel con clientes para crear tareas
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Upload Section -->
      <div class="card">
        <h3 class="font-semibold mb-4 dark:text-white">
          1. Subir archivo
        </h3>
        <div class="space-y-4">
          <div>
            <label class="label">Archivo Excel</label>
            <input
              type="file"
              accept=".xlsx,.xls,csv"
              class="input"
              @change="handleFile"
            >
            <p class="text-xs text-gray-400 mt-1">
              Columnas: SUSCRIPTOR, NOMBRE, CLIENTE, CEDULA, CUENTA, T.RESIDENCIA 1, T.RESIDENCIA 2, PROVINCIA, DISTRITO, CORREGIMIENTO, BARRIO, USUARIO
            </p>
          </div>

          <button
            :disabled="!file || uploading"
            class="btn btn-primary w-full"
            @click="uploadFile"
          >
            {{ uploading ? 'Subiendo...' : 'Subir archivo' }}
          </button>

          <button
            class="btn btn-secondary w-full"
            @click="downloadTemplate"
          >
            📥 Descargar plantilla
          </button>
        </div>
      </div>

      <!-- Column Mapping -->
      <div
        v-if="importData"
        class="card"
      >
        <h3 class="font-semibold mb-4 dark:text-white">
          2. Mapear columnas
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Total de filas: {{ importData.total_rows }}
        </p>

        <div class="space-y-3">
          <div>
            <label class="label">Nombre del Cliente *</label>
            <select
              v-model="columnMapping.full_name"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Suscriptor (número) *</label>
            <select
              v-model="columnMapping.suscriptor"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">CLIENTE (código/ID del cliente)</label>
            <select
              v-model="columnMapping.cliente"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Cedula</label>
            <select
              v-model="columnMapping.cedula"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Cuenta *</label>
            <select
              v-model="columnMapping.cuenta"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">T. Residencia 1 *</label>
            <select
              v-model="columnMapping.telefono_residencia_1"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">T. Residencia 2</label>
            <select
              v-model="columnMapping.telefono_residencia_2"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">N° Celular (fallback si falta residencial)</label>
            <select
              v-model="columnMapping.numero_celular"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">N° Contacto (fallback)</label>
            <select
              v-model="columnMapping.numero_contacto"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Provincia</label>
            <select
              v-model="columnMapping.provincia"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Distrito</label>
            <select
              v-model="columnMapping.distrito"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Corregimiento</label>
            <select
              v-model="columnMapping.corregimiento"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Barrio</label>
            <select
              v-model="columnMapping.barrio"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
          <div>
            <label class="label">Empresa (opcional, si el Excel mezcla varias)</label>
            <select
              v-model="columnMapping.empresa"
              class="input"
            >
              <option value="">
                No usar (asignar la empresa seleccionada)
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
            <p class="text-xs text-gray-400 mt-1">
              Se usa el valor de la fila para asignar a TIGO / TELCA / MAS MOVIL
            </p>
          </div>
          <div>
            <label class="label">Usuario (asignación por nombre, opcional)</label>
            <select
              v-model="columnMapping.usuario"
              class="input"
            >
              <option value="">
                Seleccionar columna
              </option>
              <option
                v-for="col in headers"
                :key="col"
                :value="col"
              >
                {{ col }}
              </option>
            </select>
          </div>
        </div>

        <div class="mt-4 space-y-3">
          <div>
            <label class="label">Fecha programada</label>
            <input
              v-model="scheduledDate"
              type="date"
              class="input"
            >
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Cada tarea se asigna automáticamente al usuario de la columna USUARIO. Las filas sin usuario quedan sin asignar para repartirlas desde Tareas.
          </p>
          <button
            class="btn btn-success w-full"
            @click="processImport"
          >
            🚀 Procesar importación
          </button>
        </div>
      </div>
    </div>

    <!-- Import History -->
    <div class="card mt-6">
      <h3 class="font-semibold mb-4 dark:text-white">
        Historial de importaciones
      </h3>
      <table class="w-full">
        <thead>
          <tr class="text-left text-xs text-gray-500 dark:text-gray-400 border-b dark:border-gray-700">
            <th class="pb-3 font-medium">
              Archivo
            </th>
            <th class="pb-3 font-medium">
              Empresa
            </th>
            <th class="pb-3 font-medium">
              Filas
            </th>
            <th class="pb-3 font-medium">
              Exitosas
            </th>
            <th class="pb-3 font-medium">
              Estado
            </th>
            <th class="pb-3 font-medium">
              Fecha
            </th>
            <th class="pb-3 font-medium">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="imp in imports"
            :key="imp.id"
            class="border-b border-gray-50 dark:border-gray-700 last:border-0"
          >
            <td class="py-3 text-sm dark:text-gray-300">
              {{ imp.original_filename }}
            </td>
            <td class="py-3 text-sm dark:text-gray-300">
              {{ imp.company?.name }}
            </td>
            <td class="py-3 text-sm dark:text-gray-300">
              {{ imp.total_rows }}
            </td>
            <td class="py-3 text-sm dark:text-gray-300">
              {{ imp.successful_rows }}
            </td>
            <td class="py-3">
              <span
                :class="'badge-' + imp.status"

                class="badge"
              >{{ imp.status }}</span>
            </td>
            <td class="py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ imp.created_at }}
            </td>
            <td class="py-3 whitespace-nowrap">
              <button
                type="button"
                class="text-xs px-2.5 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 mr-2"
                @click="openEditImport(imp)"
              >
                Editar
              </button>
              <button
                type="button"
                class="text-xs px-2.5 py-1.5 rounded border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                @click="deleteImport(imp)"
              >
                Eliminar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Edit Import Modal -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
          Editar importación
        </h3>
        <form
          class="space-y-4"
          @submit.prevent="saveEditImport"
        >
          <div>
            <label class="label">Nombre del archivo</label>
            <input
              v-model="editForm.original_filename"
              type="text"
              class="input"
            >
          </div>
          <div>
            <label class="label">Empresa</label>
            <select
              v-model="editForm.company_id"
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
          <div class="flex gap-3">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="showEditModal = false"
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
import { excelApi } from '@/utils/api'
import { companiesApi } from '@/utils/api'

const companies = ref([])
const imports = ref([])
const file = ref(null)
const uploading = ref(false)
const importData = ref(null)
const headers = ref([])
const showEditModal = ref(false)
const editForm = ref({ id: null, original_filename: '', company_id: '' })
const scheduledDate = ref(new Date().toISOString().split('T')[0])
const columnMapping = ref({
  suscriptor: '', full_name: '', cliente: '', cedula: '', cuenta: '', telefono_residencia_1: '',
  telefono_residencia_2: '', numero_celular: '', numero_contacto: '', provincia: '', distrito: '', corregimiento: '', barrio: '', usuario: '', empresa: ''
})

onMounted(() => { fetchCompanies(); fetchImports() })

async function fetchCompanies() {
  try {
    const res = await companiesApi.getAll()
    companies.value = res.data.data || res.data
  } catch (e) {}
}

async function fetchImports() {
  try {
    const res = await excelApi.getAll()
    imports.value = res.data.data || res.data
  } catch (e) {}
}

function handleFile(e) {
  file.value = e.target.files[0]
}

async function uploadFile() {
  if (!file.value) return
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('company_id', companies.value[0]?.id || 1)
const res = await excelApi.import(formData)
    importData.value = res.data.import
    headers.value = res.data.headers || ['Nombre del Cliente', 'Cedula', 'Cuenta', 'Telefono Residencia 1', 'Telefono Residencia 2', 'Provincia', 'Distrito', 'Corregimiento', 'Barrio', 'Usuario']
columnMapping.value = {
      suscriptor: headers.value.find(h => /suscriptor|susc|subscriber/i.test(h)) || '',
      full_name: headers.value.find(h => /^nombre|nombre\s*del|nombre_cliente|cliente\s*nombre|^name|firstname|first_name/i.test(h)) || headers.value.find(h => /nombre|name/i.test(h)) || '',
      cliente: headers.value.find(h => /^cliente|cliente\b|client id|suscriptor|^id|^code|^codigo/i.test(h)) || '',
      cedula: headers.value.find(h => /ced|identif|doc|ci\b/i.test(h)) || '',
      cuenta: headers.value.find(h => /cuenta|cu\b|account|n[ií]|nro|numero|number|order|pedido/i.test(h)) || '',
      telefono_residencia_1: headers.value.find(h => /residencia\s*1|t\.residencia|tel.*1|fono.*1|telefono.*1|phone.*1|cel.*1/i.test(h)) || headers.value.find(h => /residencial_1|residencial1/i.test(h)) || '',
      telefono_residencia_2: headers.value.find(h => /residencia\s*2|tel.*2|fono.*2|telefono.*2|phone.*2|cel.*2/i.test(h)) || headers.value.find(h => /residencial_2|residencial2/i.test(h)) || '',
      numero_celular: headers.value.find(h => /numero_celular|num.*cel|celtel|mobile.*number|celular/i.test(h)) || '',
      numero_contacto: headers.value.find(h => /numero_contacto|num.*contact|contact.*number|worrent.*1/i.test(h)) || '',
      provincia: headers.value.find(h => /prov/i.test(h)) || '',
      distrito: headers.value.find(h => /dist|district/i.test(h)) || '',
      corregimiento: headers.value.find(h => /correg|corr/i.test(h)) || '',
      barrio: headers.value.find(h => /barri|neighbor|barrio/i.test(h)) || '',
      usuario: headers.value.find(h => /usu|user|agent|usuario|nombre del agente/i.test(h)) || '',
      empresa: headers.value.find(h => /empresa|compa[ñn][íi]a|compania|operador|tipo de base|proveedor|marca/i.test(h)) || ''
    }
  } catch (e) {
    alert('Error subiendo archivo: ' + (e.response?.data?.message || e.message))
  } finally {
    uploading.value = false
  }
}

function openEditImport(imp) {
  editForm.value = {
    id: imp.id,
    original_filename: imp.original_filename || '',
    company_id: imp.company_id || (companies.value[0]?.id ?? ''),
  }
  showEditModal.value = true
}

async function saveEditImport() {
  try {
    await excelApi.update(editForm.value.id, {
      original_filename: editForm.value.original_filename,
      company_id: editForm.value.company_id,
    })
    showEditModal.value = false
    await fetchImports()
    alert('Importación actualizada')
  } catch (e) {
    alert('Error al actualizar: ' + (e.response?.data?.message || e.message))
  }
}

async function deleteImport(imp) {
  if (!confirm(`¿Eliminar la importación "${imp.original_filename}" del historial?`)) return
  try {
    await excelApi.delete(imp.id)
    await fetchImports()
    alert('Importación eliminada')
  } catch (e) {
    alert('Error al eliminar: ' + (e.response?.data?.message || e.message))
  }
}

async function processImport() {
  const mapping = { ...columnMapping.value }
  Object.keys(mapping).forEach(key => { if (!mapping[key]) delete mapping[key] })

  if (!mapping.full_name || !mapping.suscriptor || !mapping.cuenta || (!mapping.telefono_residencia_1 && !mapping.telefono_residencia_2)) {
    alert('Debe mapear al menos: Nombre del Cliente, Suscriptor, Cuenta y un Teléfono de Residencia')
    return
  }

  try {
    await excelApi.process(importData.value.id, {
      column_mapping: mapping,
      scheduled_date: scheduledDate.value || undefined
    })
    alert('Importación procesada correctamente')
    importData.value = null
    file.value = null
    fetchImports()
  } catch (e) {
    alert('Error: ' + (e.response?.data?.message || e.message))
  }
}

async function downloadTemplate() {
  const res = await excelApi.downloadTemplate()
  const url = window.URL.createObjectURL(new Blob([res.data]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', 'plantilla_clientes.xlsx')
  document.body.appendChild(link)
  link.click()
  link.remove()
}
</script>