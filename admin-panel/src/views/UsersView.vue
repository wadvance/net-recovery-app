<template>
  <div class="p-8">
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
          <option value="admin">
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
          @submit.prevent="save"
        >
          <div>
            <label class="label">Nombre</label><input
              v-model="form.name"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
              required
            >
          </div>
          <div>
            <label class="label">Email</label><input
              v-model="form.email"
              type="email"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
              required
            >
          </div>
          <div>
            <label class="label">Teléfono</label><input
              v-model="form.phone"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
            >
          </div>
          <div>
            <label class="label">Rol</label>
            <select
              v-model="form.role"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
            >
              <option value="agent">
                Agente
              </option><option value="supervisor">
                Supervisor
              </option><option value="admin">
                Admin
              </option>
            </select>
          </div>
          <div v-if="!editing">
            <label class="label">Contraseña</label><input
              v-model="form.password"
              type="password"
              class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-black focus:border-primary-500 focus:ring-2 focus:ring-primary-100 outline-none transition-all"
              required
            >
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { usersApi } from '@/utils/api'

const users = ref([])
const search = ref('')
const roleFilter = ref('')
const showModal = ref(false)
const editing = ref(null)
const form = ref({ name: '', email: '', phone: '', role: 'agent', password: '' })

onMounted(fetchUsers)

async function fetchUsers() {
  const res = await usersApi.getAll({ search: search.value, role: roleFilter.value })
  users.value = res.data.data || res.data
}

function openModal(user = null) {
  editing.value = user
  form.value = user
    ? { name: user.name, email: user.email, phone: user.phone, role: user.role, password: '' }
    : { name: '', email: '', phone: '', role: 'agent', password: '' }
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

function roleClass(role) {
  return { admin: 'bg-red-100 text-red-700', supervisor: 'bg-purple-100 text-purple-700', agent: 'bg-blue-100 text-blue-700' }[role] || 'bg-gray-100'
}
</script>