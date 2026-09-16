<template>
  <div class="p-4 lg:p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">
          Usuarios
        </h1>
        <p class="text-gray-500 mt-1">
          Gestiona agentes y supervisores
        </p>
      </div>
      <button
        v-if="authStore.isAdmin"
        class="btn btn-primary"
        @click="openModal()"
      >
        + Nuevo Usuario
      </button>
    </div>

    <div class="card">
      <div class="flex gap-3 mb-4">
        <input
          v-model="search"
          type="text"
          class="input max-w-xs"
          placeholder="Buscar..."
          @input="fetchUsers"
        >
        <select
          v-model="roleFilter"
          class="input max-w-[160px]"
          @change="fetchUsers"
        >
          <option value="">
            Todos
          </option>
          <option value="agent">
            Agentes
          </option>
          <option value="supervisor">
            Supervisores
          </option>
          <option
            v-if="authStore.isAdmin"
            value="admin"
          >
            Admins
          </option>
        </select>
      </div>

      <table class="w-full">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b">
            <th class="pb-3 font-medium">
              Nombre
            </th>
            <th class="pb-3 font-medium">
              Email
            </th>
            <th class="pb-3 font-medium">
              Rol
            </th>
            <th class="pb-3 font-medium">
              Estado
            </th>
            <th class="pb-3 font-medium">
              WhatsApp
            </th>
            <th class="pb-3 font-medium">
              Acciones
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in users"
            :key="user.id"
            class="border-b border-gray-50 last:border-0"
          >
            <td class="py-3 text-sm font-medium text-gray-700 dark:text-white">
              {{ user.name }}
            </td>
            <td class="py-3 text-sm text-gray-600 dark:text-white">
              {{ user.email }}
            </td>
            <td class="py-3">
              <span
                class="badge"
                :class="roleClass(user.role)"
              >{{ user.role }}</span>
            </td>
            <td class="py-3">
              <span
                :class="user.is_active ? 'badge-completed' : 'badge-failed'"
                class="badge"
              >
                {{ user.is_active ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="py-3">
              <span
                v-if="user.settings?.whatsapp_api_key"
                class="badge bg-green-100 text-green-700"
                title="WhatsApp configurado"
              >
                📱 WA
              </span>
              <span
                v-else
                class="badge bg-gray-100 text-gray-500"
                title="Sin WhatsApp propio"
              >
                —
              </span>
            </td>
            <td class="py-3">
              <button
                class="text-sm text-gray-500 hover:text-primary-500 mr-3"
                @click="toggleStatus(user)"
              >
                {{ user.is_active ? 'Desactivar' : 'Activar' }}
              </button>
              <button
                class="text-sm text-primary-500 hover:text-primary-600"
                @click="openModal(user)"
              >
                Editar
              </button>
              <button
                v-if="authStore.isAdmin"
                class="text-sm text-orange-500 hover:text-orange-600 ml-3"
                @click="openResetModal(user)"
              >
                Restablecer contraseña
              </button>
              <button
                v-if="authStore.isSupervisor"
                class="text-sm text-red-500 hover:text-red-600 ml-3"
                @click="confirmDelete(user)"
              >
                Eliminar
              </button>
              <button
                class="text-sm text-green-600 hover:text-green-700 ml-3"
                :disabled="waSendingId === user.id"
                @click="sendWhatsappBulk(user)"
              >
                {{ waSendingId === user.id ? 'Enviando...' : 'WhatsApp masivo' }}
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
        <h3 class="text-lg font-semibold mb-4 dark:text-white">
          {{ editing ? 'Editar' : 'Nuevo' }} Usuario
        </h3>
        <form
          class="space-y-4"
          autocomplete="off"
          @submit.prevent="save"
        >
          <div>
            <label class="label">Nombre</label><input
              v-model="form.name"
              autocomplete="off"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
              required
            >
          </div>
          <div>
            <label class="label">Email</label><input
              v-model="form.email"
              type="email"
              autocomplete="off"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
              required
            >
          </div>
          <div>
            <label class="label">Teléfono</label><input
              v-model="form.phone"
              autocomplete="off"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
            >
          </div>
          <div>
            <label class="label">Rol</label>
            <select
              v-model="form.role"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
            >
              <option
                value=""
                disabled
              >
                Selecciona un rol
              </option><option value="agent">
                Agente
              </option><option value="supervisor">
                Supervisor
              </option><option value="admin">
                Admin
              </option>
            </select>
          </div>
          <div v-if="authStore.isAdmin">
            <label class="label block mb-1">Contraseña</label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                name="new-password"
                autocomplete="new-password"
                class="w-full px-4 py-2.5 pr-10 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
                minlength="8"
                :required="!editing"
              >
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="showPassword = !showPassword"
              >
                <svg
                  v-if="showPassword"
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                  />
                </svg>
                <svg
                  v-else
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
              </button>
            </div>
            <div class="flex items-center justify-between mt-2">
              <p class="text-xs text-gray-400">
                {{ editing ? 'Si lo dejas vacío, la contraseña no cambia' : 'Mínimo 8 caracteres' }}
              </p>
              <button
                type="button"
                class="text-xs text-primary-500 hover:text-primary-600 font-medium"
                @click="generatePassword"
              >
                Generar contraseña
              </button>
            </div>
          </div>

          <!-- WhatsApp Configuration -->
          <div class="border-t border-gray-200 pt-4 mt-4">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
              <span class="text-green-600">📱</span> Configuración WhatsApp (YCloud)
            </h4>
            <div class="space-y-3">
              <div>
                <label class="label">API Key de YCloud</label>
                <input
                  v-model="form.whatsapp_api_key"
                  type="password"
                  autocomplete="off"
                  class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all text-sm"
                  placeholder="ycloud_api_key_..."
                >
                <p class="text-xs text-gray-400 mt-1">
                  Cada usuario puede tener su propia API key de YCloud
                </p>
              </div>
              <div>
                <label class="label">Phone Number ID</label>
                <input
                  v-model="form.whatsapp_phone_number_id"
                  autocomplete="off"
                  class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all text-sm"
                  placeholder="123456789012345"
                >
                <p class="text-xs text-gray-400 mt-1">
                  ID del número de teléfono en YCloud/Meta
                </p>
              </div>
            </div>
          </div>
          <div class="flex gap-3 pt-2">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="showModal = false"
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

    <!-- Reset password modal -->
    <div
      v-if="showResetModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4 dark:text-white">
          Restablecer contraseña
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
          Nueva contraseña para <strong class="text-gray-700 dark:text-gray-200">{{ resetUser?.name }}</strong> ({{ resetUser?.email }})
        </p>
        <form
          class="space-y-4"
          @submit.prevent="resetPassword"
        >
          <div>
            <label class="label block mb-1">Nueva contraseña</label>
            <div class="relative">
              <input
                v-model="resetPasswordForm"
                :type="showResetPassword ? 'text' : 'password'"
                class="w-full px-4 py-2.5 pr-10 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
                minlength="8"
                required
              >
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="showResetPassword = !showResetPassword"
              >
                <svg
                  v-if="showResetPassword"
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"
                  />
                </svg>
                <svg
                  v-else
                  class="w-5 h-5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
              </button>
            </div>
            <div class="flex items-center justify-between mt-1">
              <p class="text-xs text-gray-400">
                Mínimo 8 caracteres. Compártela con el usuario.
              </p>
              <button
                type="button"
                class="text-xs text-primary-500 hover:text-primary-600 font-medium"
                @click="generatePassword(true)"
              >
                Generar
              </button>
            </div>
          </div>
          <div class="flex gap-3 pt-2">
            <button
              type="button"
              class="btn btn-secondary flex-1"
              @click="showResetModal = false"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary flex-1"
            >
              Restablecer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usersApi } from '@/utils/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const users = ref([])
const search = ref('')
const roleFilter = ref('')
const showModal = ref(false)
const editing = ref(null)
const form = ref({ name: '', email: '', phone: '', role: '', password: '', whatsapp_api_key: '', whatsapp_phone_number_id: '' })
const showResetModal = ref(false)
const resetUser = ref(null)
const resetPasswordForm = ref('')
const showPassword = ref(false)
const showResetPassword = ref(false)
const waSendingId = ref(null)

onMounted(fetchUsers)

function generatePassword(reset = false) {
  const lower = 'abcdefghijklmnopqrstuvwxyz'
  const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const digits = '0123456789'
  const symbols = '!@#$%&*+-_'
  const pick = (chars) => chars[Math.floor(Math.random() * chars.length)]
  const password = [
    pick(upper), pick(lower), pick(digits), pick(symbols),
  ].concat(Array.from({ length: 8 }, () => pick(upper + lower + digits + symbols)))
    .sort(() => Math.random() - 0.5)
    .join('')
  if (reset) {
    resetPasswordForm.value = password
    showResetPassword.value = true
  } else {
    form.value.password = password
    showPassword.value = true
  }
}

async function fetchUsers() {
  const res = await usersApi.getAll({ search: search.value, role: roleFilter.value })
  const list = res.data.data || res.data
  users.value = list.filter(u => authStore.isAdmin || u.role !== 'admin')
}

function openModal(user = null) {
  editing.value = user
  form.value = user
    ? {
        name: user.name,
        email: user.email,
        phone: user.phone,
        role: user.role,
        password: '',
        whatsapp_api_key: user.settings?.whatsapp_api_key || '',
        whatsapp_phone_number_id: user.settings?.whatsapp_phone_number_id || '',
      }
    : { name: '', email: '', phone: '', role: '', password: '', whatsapp_api_key: '', whatsapp_phone_number_id: '' }
  showModal.value = true
}

async function save() {
  try {
    if (editing.value) await usersApi.update(editing.value.id, form.value)
    else await usersApi.create(form.value)
    showModal.value = false
    fetchUsers()
  } catch (e) { alert(e.response?.data?.message || 'Error') }
}

async function toggleStatus(user) {
  await usersApi.toggleStatus(user.id)
  fetchUsers()
}

function openResetModal(user) {
  resetUser.value = user
  resetPasswordForm.value = ''
  showResetModal.value = true
}

async function resetPassword() {
  try {
    await usersApi.resetPassword(resetUser.value.id, resetPasswordForm.value)
    showResetModal.value = false
    alert(`Contraseña de ${resetUser.value.name} restablecida correctamente.`)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al restablecer la contraseña')
  }
}

async function sendWhatsappBulk(user) {
  if (!confirm(`Enviar WhatsApp masivo a los clientes asignados a "${user.name}"?`)) return
  waSendingId.value = user.id
  try {
    const res = await usersApi.whatsappBulk(user.id, { template_name: 'equipment_recovery_notification' })
    alert(res.data.message || `Enviados: ${res.data.created}, omitidos: ${res.data.skipped}`)
  } catch (e) { alert(e.response?.data?.message || 'Error al enviar') }
  finally { waSendingId.value = null }
}

async function confirmDelete(user) {
  if (!confirm(`¿Estás seguro de que deseas eliminar a "${user.name}"? Esta acción no se puede deshacer.`)) return
  try {
    await usersApi.delete(user.id)
    fetchUsers()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar el usuario')
  }
}

function roleClass(role) {
  return { admin: 'bg-red-100 text-red-700', supervisor: 'bg-purple-100 text-purple-700', agent: 'bg-blue-100 text-blue-700' }[role] || 'bg-gray-100'
}
</script>