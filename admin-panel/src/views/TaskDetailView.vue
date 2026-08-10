<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold text-gray-800">
        Detalle de Tarea
      </h1>
      <router-link
        to="/tasks"
        class="btn btn-secondary"
      >
        ← Volver
      </router-link>
    </div>
    <div
      v-if="task"
      class="grid grid-cols-1 lg:grid-cols-2 gap-6"
    >
      <div class="card">
        <h3 class="font-semibold mb-4">
          Tarea
        </h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Título</span><span>{{ task.title }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Estado</span><span
              :class="'badge-' + task.status"
              class="badge"
            >{{ task.status }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Prioridad</span><span>{{ task.priority }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Fecha</span><span>{{ task.scheduled_date }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Agente</span><span>{{ task.assignee?.name || 'Sin asignar' }}</span>
          </div>
        </div>
      </div>
      <div class="card">
        <h3 class="font-semibold mb-4">
          Cliente
        </h3>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">Nombre</span><span>{{ task.client?.full_name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Teléfono</span><span>{{ task.client?.phone }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Dirección</span><span>{{ task.client?.address }}</span>
          </div>
        </div>
      </div>
      <div class="card lg:col-span-2">
        <h3 class="font-semibold mb-4">
          Evidencias ({{ task.evidence?.length || 0 }})
        </h3>
        <div
          v-if="task.evidence?.length"
          class="grid grid-cols-2 md:grid-cols-4 gap-4"
        >
          <div
            v-for="ev in task.evidence"
            :key="ev.id"
            class="aspect-square bg-gray-100 rounded-lg flex items-center justify-center"
          >
            <img
              v-if="ev.type === 'photo'"
              :src="ev.full_url"
              class="w-full h-full object-cover rounded-lg"
            >
            <span
              v-else
              class="text-gray-400 text-xs"
            >{{ ev.type }}</span>
          </div>
        </div>
        <p
          v-else
          class="text-gray-500 text-sm"
        >
          Sin evidencias
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { tasksApi } from '@/utils/api'

const route = useRoute()
const task = ref(null)

onMounted(async () => {
  const res = await tasksApi.get(route.params.id)
  task.value = res.data
})
</script>