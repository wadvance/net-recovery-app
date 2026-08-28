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
          <div class="flex justify-between">
            <span class="text-gray-500">WhatsApp</span>
            <button
              v-if="task.client?.phone"
              type="button"
              :disabled="sendingWhatsApp"
              class="px-2 py-1 rounded text-xs font-medium text-white"
              style="background-color:#25D366"
              @click="sendWhatsApp"
            >
              {{ sendingWhatsApp ? 'Enviando…' : 'Enviar WhatsApp' }}
            </button>
            <span
              v-else
              class="text-gray-400"
            >Sin teléfono</span>
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
const sendingWhatsApp = ref(false)

onMounted(async () => {
  const res = await tasksApi.get(route.params.id)
  task.value = res.data
})

async function sendWhatsApp() {
  const phone = task.value?.client?.phone
  if (!phone) {
    alert('El cliente no tiene teléfono registrado')
    return
  }
  sendingWhatsApp.value = true
  try {
    const res = await tasksApi.sendWhatsApp(task.value.id, {
      template_name: 'equipment_recovery_notification',
    })
    const data = res.data
    switch (data.status) {
      case 'sent':
        alert(`WhatsApp enviado a ${phone}`)
        break
      case 'failed':
        alert('No se pudo enviar WhatsApp: ' + (data.error || data.message || 'error desconocido'))
        break
      default:
        alert('Estado del mensaje: ' + (data.status || data.message))
    }
  } catch (e) {
    alert('Error al enviar WhatsApp: ' + (e.response?.data?.message || e.message || e))
  } finally {
    sendingWhatsApp.value = false
  }
}
</script>