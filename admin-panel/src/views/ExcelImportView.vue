<template>
  <div class="p-4 lg:p-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
          Importar Excel
        </h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">
          Sube un archivo Excel con clientes para crear tareas
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="inline-flex items-center gap-2 px-5 py-3 bg-green-500 hover:bg-green-600 text-white rounded-xl font-semibold shadow-sm transition-colors"
          @click="scanRef && scanRef.show()"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9V5a2 2 0 012-2h4m6 0h4a2 2 0 012 2v4m0 6v4a2 2 0 01-2 2h-4m-6 0H5a2 2 0 01-2-2v-4M7 12h10" />
          </svg>
          Escanear Códigos
        </button>
        <button
          v-if="isSupervisorRole"
          type="button"
          class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-500"
          @click="clearAllData"
        >
          🧹 Limpiar todos los datos
        </button>
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
              Columnas: SUSCRIPTOR, NOMBRE, T.RESIDENCIA 1, T.RESIDENCIA 2,
              LUGAR, USUARIO, EMPRESA
            </p>
            <p
              class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium"
            >
              Al subir el archivo, cada tarea se asigna automáticamente al
              agente de la columna USUARIO.
            </p>
          </div>

          <div>
            <label class="label">Fecha programada de las tareas</label>
            <input
              v-model="scheduledDate"
              type="date"
              class="input"
            >
          </div>

          <button
            :disabled="!file || uploading"
            class="btn btn-primary w-full"
            @click="uploadFile"
          >
            {{ uploading ? "Subiendo..." : "Subir archivo" }}
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
          {{
            imported
              ? "2. Resultado del envío"
              : "2. Revisa el archivo antes de enviar"
          }}
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Archivo: {{ importData.original_filename }} · Total de filas:
          {{ importData.total_rows }}
        </p>

        <template v-if="imported">
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
            <div
              class="rounded-xl bg-green-50 dark:bg-green-900/20 p-4 text-center"
            >
              <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ lastResult.successful || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Clientes / tareas
              </p>
            </div>
            <div
              class="rounded-xl bg-blue-50 dark:bg-blue-900/20 p-4 text-center"
            >
              <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ lastResult.skipped || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Omitidos
              </p>
            </div>
            <div
              class="rounded-xl bg-red-50 dark:bg-red-900/20 p-4 text-center"
            >
              <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                {{ lastResult.failed || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Fallidos
              </p>
            </div>
            <div
              class="rounded-xl bg-teal-50 dark:bg-teal-900/20 p-4 text-center"
            >
              <p class="text-2xl font-bold text-teal-600 dark:text-teal-400">
                {{ lastResult.notified || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Notificados WhatsApp
              </p>
            </div>
            <div
              class="rounded-xl bg-yellow-50 dark:bg-yellow-900/20 p-4 text-center"
            >
              <p
                class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"
              >
                {{ lastResult.notify_failed || 0 }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Avisos fallidos
              </p>
            </div>
            <div class="rounded-xl bg-gray-50 dark:bg-gray-800 p-4 text-center">
              <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">
                {{ importData.total_rows }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Filas del archivo
              </p>
            </div>
          </div>

          <div
            v-if="lastResult.errors && lastResult.errors.length"
            class="mb-4"
          >
            <h4 class="text-sm font-semibold mb-2 dark:text-white">
              Errores / avisos
            </h4>
            <ul
              class="max-h-48 overflow-y-auto rounded-lg bg-gray-50 dark:bg-gray-800 p-3 space-y-1"
            >
              <li
                v-for="(err, i) in lastResult.errors"
                :key="i"
                class="text-xs text-gray-600 dark:text-gray-300"
              >
                {{ err }}
              </li>
            </ul>
          </div>

          <p class="text-sm text-gray-600 dark:text-gray-400">
            Tareas asignadas al agente de la columna USUARIO del archivo. Las
            filas sin usuario quedaron sin asignar y se listan en el módulo
            WhatsApp.
          </p>

          <button
            class="btn btn-secondary w-full mt-4"
            @click="resetImport"
          >
            📤 Subir otro archivo
          </button>
        </template>

        <div
          v-else
          class="space-y-4"
        >
          <div v-if="previewRows.length">
            <label class="label">Previsualización del archivo</label>
            <div
              class="overflow-x-auto max-h-80 rounded-lg border border-gray-200 dark:border-gray-600"
            >
              <table class="min-w-full text-xs">
                <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                  <tr>
                    <th
                      class="px-2 py-2 text-left font-medium text-gray-500 dark:text-gray-300"
                    >
                      #
                    </th>
                    <th
                      v-for="h in headers"
                      :key="h"
                      class="px-2 py-2 text-left font-medium text-gray-500 dark:text-gray-300 whitespace-nowrap"
                    >
                      {{ h }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in previewRows"
                    :key="i"
                    class="border-t border-gray-100 dark:border-gray-700"
                  >
                    <td class="px-2 py-1.5 text-gray-400">
                      {{ i + 1 }}
                    </td>
                    <td
                      v-for="h in headers"
                      :key="h"
                      class="px-2 py-1.5 text-gray-700 dark:text-gray-200"
                    >
                      {{ row[h] }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p
              v-if="importData.total_rows > previewRows.length"
              class="text-xs text-gray-400 mt-1"
            >
              Mostrando las primeras {{ previewRows.length }} filas de
              {{ importData.total_rows }}
            </p>
          </div>

          <div v-if="false" class="space-y-3">
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
              <label class="label">Cuenta</label>
              <select
                v-model="columnMapping.cuenta"
                class="input"
              >
                <option value="">
                  Seleccionar columna (opcional)
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
              <label class="label">Lugar (dirección) *</label>
              <select
                v-model="columnMapping.lugar"
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
            <div>
              <label class="label">Empresa (opcional, después de USUARIO)</label>
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
                Si el Excel trae EMPRESA al final, se usa para asignar a TIGO / MAS MOVIL / TELCA
              </p>
            </div>
          </div>

          <div>
            <label class="label">Fecha programada</label>
            <input
              v-model="scheduledDate"
              type="date"
              class="input"
            >
          </div>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Al procesar, cada tarea se asigna al usuario de la columna USUARIO y
            se envía un aviso de WhatsApp a cada cliente.
          </p>
          <button
            class="btn btn-success w-full"
            :disabled="processing"
            @click="processImport"
          >
            {{
              processing
                ? "Procesando y enviando..."
                : "🚀 Procesar y enviar a los clientes"
            }}
          </button>
        </div>
      </div>
    </div>

    <ScannerModal ref="scanRef" />

    <!-- Import History -->
    <div class="card mt-6">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <h3 class="font-semibold dark:text-white">
          Historial de importaciones
        </h3>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg border border-red-300 dark:border-red-600 text-red-600 dark:text-red-400 text-xs font-medium hover:bg-red-50 dark:hover:bg-red-900/20"
          @click="clearFileList"
        >
          🗑️ Limpiar lista de archivos
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr
              class="text-left text-xs text-gray-500 dark:text-gray-400 border-b dark:border-gray-700"
            >
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
                >{{
                  imp.status
                }}</span>
              </td>
              <td class="py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ imp.created_at }}
              </td>
              <td class="py-3 whitespace-nowrap">
                <button
                  v-if="isSupervisorRole"
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { excelApi } from "@/utils/api";
import { companiesApi } from "@/utils/api";
import { useAuthStore } from "@/stores/auth";
import ScannerModal from "@/components/ScannerModal.vue";

const scanRef = ref(null);

const authStore = useAuthStore();

const isSupervisorRole = computed(() =>
  ["admin", "supervisor"].includes(authStore.user?.role),
);

const companies = ref([]);
const imports = ref([]);
const file = ref(null);
const uploading = ref(false);
const processing = ref(false);
const selectedCompanyId = ref("");
const importData = ref(null);
const imported = ref(false);
const lastResult = ref({});
const headers = ref([]);
const previewRows = ref([]);
const showEditModal = ref(false);
const editForm = ref({ id: null, original_filename: "", company_id: "" });
const scheduledDate = ref(new Date().toISOString().split("T")[0]);
const columnMapping = ref({
  suscriptor: "",
  full_name: "",
  lugar: "",
  cliente: "",
  cedula: "",
  cuenta: "",
  telefono_residencia_1: "",
  telefono_residencia_2: "",
  numero_celular: "",
  numero_contacto: "",
  provincia: "",
  distrito: "",
  corregimiento: "",
  barrio: "",
  usuario: "",
  empresa: "",
});

onMounted(() => {
  fetchCompanies();
  fetchImports();
});

async function fetchCompanies() {
  try {
    const res = await companiesApi.getAll();
    companies.value = res.data.data || res.data;
  } catch (e) {}
}

async function fetchImports() {
  try {
    const res = await excelApi.getAll();
    imports.value = res.data.data || res.data;
  } catch (e) {}
}

function handleFile(e) {
  file.value = e.target.files[0];
}

async function uploadFile() {
  if (!file.value) return;
  uploading.value = true;
  imported.value = false;
  lastResult.value = {};
  previewRows.value = [];
  try {
    const formData = new FormData();
    formData.append("file", file.value);
    if (selectedCompanyId.value) formData.append("company_id", selectedCompanyId.value);
    formData.append("scheduled_date", scheduledDate.value);
    const res = await excelApi.import(formData);
    importData.value = res.data.import;
    headers.value = res.data.headers || [];
    columnMapping.value = res.data.mapping || columnMapping.value;
    previewRows.value = res.data.preview || [];
    file.value = null;
    alert(
      res.data.message ||
        'Archivo subido correctamente. Revisa la previsualización y presiona "Procesar y enviar".',
    );
  } catch (e) {
    alert(
      "Error subiendo archivo: " + (e.response?.data?.message || e.message),
    );
  } finally {
    uploading.value = false;
  }
}

function openEditImport(imp) {
  editForm.value = {
    id: imp.id,
    original_filename: imp.original_filename || "",
    company_id: imp.company_id || (companies.value[0]?.id ?? ""),
  };
  showEditModal.value = true;
}

async function saveEditImport() {
  try {
    await excelApi.update(editForm.value.id, {
      original_filename: editForm.value.original_filename,
      company_id: editForm.value.company_id,
    });
    showEditModal.value = false;
    await fetchImports();
    alert("Importación actualizada");
  } catch (e) {
    alert("Error al actualizar: " + (e.response?.data?.message || e.message));
  }
}

async function deleteImport(imp) {
  if (
    !confirm(
      `¿Eliminar la importación "${imp.original_filename}" del historial?`,
    )
  )
    return;
  try {
    await excelApi.delete(imp.id);
    await fetchImports();
    alert("Importación eliminada");
  } catch (e) {
    alert("Error al eliminar: " + (e.response?.data?.message || e.message));
  }
}

async function clearFileList() {
  const ok = confirm(
    "¿Limpiar la lista de archivos de Excel subidos?\n\nSe eliminan los registros y archivos del historial para empezar el día siguiente. No se borran clientes ni tareas ya creadas.",
  );
  if (!ok) return;
  try {
    const res = await excelApi.clearList();
    if (importData.value && importData.value.id) {
      importData.value = null;
      previewRows.value = [];
      imported.value = false;
      lastResult.value = {};
    }
    file.value = null;
    await fetchImports();
    alert(res.data?.message || "Lista de archivos limpiada");
  } catch (e) {
    alert("Error al limpiar: " + (e.response?.data?.message || e.message));
  }
}

async function processImport() {
  const mapping = { ...columnMapping.value };
  Object.keys(mapping).forEach((key) => {
    if (!mapping[key]) delete mapping[key];
  });

  if (
    !mapping.full_name ||
    !mapping.suscriptor ||
    !mapping.lugar ||
    (!mapping.telefono_residencia_1 && !mapping.telefono_residencia_2)
  ) {
    alert(
      "Debe mapear al menos: Nombre del Cliente, Suscriptor, Lugar y un Teléfono de Residencia",
    );
    return;
  }

  processing.value = true;
  try {
    const res = await excelApi.process(importData.value.id, {
      column_mapping: mapping,
      scheduled_date: scheduledDate.value || undefined,
    });
    lastResult.value = res.data;
    imported.value = true;
    alert(
      `Envío completado: ${res.data.successful} clientes/tareas procesados, ${res.data.skipped} omitidos, ${res.data.failed} fallidos. ${res.data.notified} notificados por WhatsApp.`,
    );
    fetchImports();
  } catch (e) {
    alert("Error: " + (e.response?.data?.message || e.message));
  } finally {
    processing.value = false;
  }
}

function resetImport() {
  importData.value = null;
  previewRows.value = [];
  imported.value = false;
  lastResult.value = {};
  file.value = null;
}

async function clearAllData() {
  const ok = confirm(
    "¿Eliminar TODOS los clientes, tareas, reportes e historial de importaciones?\n\nEsta acción no se puede deshacer.",
  );
  if (!ok) return;
  const ok2 = confirm(
    "Confirmación final: se borrará toda la data subida para cargar el Excel nuevo. ¿Continuar?",
  );
  if (!ok2) return;
  try {
    await excelApi.clearAll();
    importData.value = null;
    file.value = null;
    await fetchImports();
    alert("Base de datos limpiada correctamente");
  } catch (e) {
    alert("Error al limpiar: " + (e.response?.data?.message || e.message));
  }
}

async function downloadTemplate() {
  const res = await excelApi.downloadTemplate();
  const url = window.URL.createObjectURL(new Blob([res.data]));
  const link = document.createElement("a");
  link.href = url;
  link.setAttribute("download", "plantilla_clientes.xlsx");
  document.body.appendChild(link);
  link.click();
  link.remove();
}
</script>
