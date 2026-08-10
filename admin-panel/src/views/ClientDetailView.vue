<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold text-gray-800">
        Detalle del Cliente
      </h1>
      <router-link
        to="/clients"
        class="btn btn-secondary"
      >
        ← Volver
      </router-link>
    </div>
    <div
      v-if="client"
      class="grid grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <div class="card">
        <h3 class="font-semibold mb-4">
          Información del cliente
        </h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Nombre del Cliente</span><span class="font-medium">{{ client.full_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">N° Suscriptor</span><span class="font-mono">{{ client.metadata?.suscriptor || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">CLIENTE (Código)</span><span class="font-mono">{{ client.metadata?.cliente || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Cedula</span><span class="font-mono">{{ client.reference || client.metadata?.cedula || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Cuenta</span><span class="font-mono">{{ client.order_number }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Telefono Residencia 1</span><span>+{{ client.phone }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Telefono Residencia 2</span><span>+{{ client.alternate_phone || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Provincia</span><span>{{ metadata.provincia || client.address || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Distrito</span><span>{{ metadata.distrito || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Corregimiento</span><span>{{ metadata.corregimiento || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Barrio</span><span>{{ metadata.barrio || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Usuario asignado</span><span>{{ client.assigned_user?.name || '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Estado</span><span
              :class="'badge-' + client.status"
              class="badge"
            >{{ client.status }}</span>
          </div>
        </div>
      </div>
      <div class="card">
        <h3 class="font-semibold mb-4">
          Tareas
        </h3>
        <div
          v-for="task in client.tasks || []"
          :key="task.id"
          class="p-3 bg-gray-50 rounded-lg mb-2 text-sm"
        >
          <div class="flex justify-between">
            <span>{{ task.title }}</span><span
              :class="'badge-' + task.status"
              class="badge"
            >{{ task.status }}</span>
          </div>
          <div
            v-if="task.assigned_to"
            class="text-xs text-gray-400 mt-1"
          >
            Asignado a: {{ task.assignee?.name || task.assigned_to }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { clientsApi } from '@/utils/api'

const route = useRoute()
const client = ref(null)

const metadata = computed(() => client.value?.metadata || {})

onMounted(async () => {
  const res = await clientsApi.get(route.params.id)
  client.value = res.data
})
</script>
