<template>
  <div class="p-8">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
        Mapa de Tareas - Chiriquí
      </h1>
      <div class="flex gap-3">
        <input
          v-model="date"
          type="date"
          class="input max-w-[160px]"
          @change="fetchMapData"
        >
        <select
          v-model="companyFilter"
          class="input max-w-[160px]"
          @change="fetchMapData"
        >
          <option value="">
            Todas las empresas
          </option>
          <option value="TIGO">
            Tigo
          </option>
          <option value="MASMOVIL">
            Más Móvil
          </option>
          <option value="TELCA">
            Telca
          </option>
        </select>
      </div>
    </div>

    <div class="card p-0 overflow-hidden">
      <div
        id="map"
        style="height: 600px; width: 100%;"
      />
    </div>

    <div class="grid grid-cols-4 gap-4 mt-6">
      <div class="card text-center">
        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">
          {{ counts.total }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Total
        </p>
      </div>
      <div class="card text-center">
        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
          {{ counts.pending }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Pendientes
        </p>
      </div>
      <div class="card text-center">
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
          {{ counts.completed }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Completadas
        </p>
      </div>
      <div class="card text-center">
        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
          {{ counts.failed }}
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Fallidas
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { dashboardApi } from '@/utils/api'
import L from 'leaflet'

const date = ref(new Date().toISOString().split('T')[0])
const companyFilter = ref('')
const mapData = ref([])
const counts = ref({ total: 0, pending: 0, completed: 0, failed: 0 })
let map = null
let markers = []
let districtLayers = []

const districts = [
  { name: 'David', lat: 8.4333, lng: -82.4333, corregimientos: ['David', 'Bijagual', 'Cochea', 'Chiriquí', 'Guacá', 'Las Lomas', 'Pedregal', 'San Carlos', 'San Pablo Nuevo', 'San Pablo Viejo'] },
  { name: 'Boquete', lat: 8.7833, lng: -82.4333, corregimientos: ['Boquete', 'Alto Bágala', 'Jaramillo', 'Los Naranjos', 'Palmira'] },
  { name: 'Bugaba', lat: 8.4833, lng: -82.6167, corregimientos: ['La Concepción', 'Aserrío de Gariché', 'Bugaba', 'Gómez', 'La Estrella', 'San Andrés', 'Santa Marta', 'Santa Rosa', 'Santo Domingo', 'Sortová', 'Solano'] },
  { name: 'Alanje', lat: 8.4000, lng: -82.5500, corregimientos: ['Alanje', 'Divalá', 'El Tejar', 'Guarumal', 'Palo Grande', 'Querevalo', 'Santo Tomás'] },
  { name: 'Barú', lat: 8.2833, lng: -82.5667, corregimientos: ['Puerto Armuelles', 'Limones', 'Progreso', 'Baco', 'Rodolfo Delgado'] },
  { name: 'Boquerón', lat: 8.5167, lng: -82.5667, corregimientos: ['Boquerón', 'Bágala', 'Cordillera', 'Guabal', 'Guayabal', 'Paraíso', 'Pedregal', 'Tijeras'] },
  { name: 'Dolega', lat: 8.5667, lng: -82.4167, corregimientos: ['Dolega', 'Dos Ríos', 'Los Anastacios', 'Potrerillos', 'Potrerillos Abajo', 'Rovira', 'Tinajas'] },
  { name: 'Gualaca', lat: 8.5333, lng: -82.3000, corregimientos: ['Gualaca', 'Hornito', 'Los Angeles', 'Paja de Sombrero', 'Rincón'] },
  { name: 'Remedios', lat: 8.5167, lng: -82.2500, corregimientos: ['Remedios', 'El Nancito', 'El Porvenir', 'El Puerto', 'Santa Lucía'] },
  { name: 'Renacimiento', lat: 8.7167, lng: -82.8333, corregimientos: ['Río Sereno', 'Breñón', 'Cañas Gordas', 'Dominical', 'Monte Lirio', 'Plaza de Caisán', 'Santa Cruz', 'Santa Clara'] },
  { name: 'San Félix', lat: 8.3667, lng: -82.2500, corregimientos: ['Las Lajas', 'Juay', 'San Félix', 'Lajas Adentro', 'Santa Cruz'] },
  { name: 'San Lorenzo', lat: 8.3000, lng: -82.1000, corregimientos: ['Horconcitos', 'Boca Chica', 'Boca del Monte', 'San Juan', 'San Lorenzo'] },
  { name: 'Tolé', lat: 8.4667, lng: -82.1667, corregimientos: ['Tolé', 'Cerro Viejo', 'Lajas de Tolé', 'Potrero de Caña', 'Quebrada de Piedra', 'Bella Vista', 'El Cristo', 'Justo Fidel Palacios', 'Veladero'] },
]

const districtColors = [
  '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
  '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
  '#F8C471', '#82E0AA', '#F1948A'
]

onMounted(async () => {
  await fetchMapData()
})

async function fetchMapData() {
  try {
    const res = await dashboardApi.mapData({ date: date.value, company_id: companyFilter.value })
    mapData.value = res.data
  } catch (e) {
    mapData.value = []
  }
  counts.value = {
    total: mapData.value.length,
    pending: mapData.value.filter(c => c.status === 'pending' || c.status === 'assigned').length,
    completed: mapData.value.filter(c => c.status === 'completed').length,
    failed: mapData.value.filter(c => c.status === 'failed').length
  }
  await nextTick()
  initMap()
}

function initMap() {
  if (map) {
    markers.forEach(m => map.removeLayer(m))
    districtLayers.forEach(d => map.removeLayer(d))
    markers = []
    districtLayers = []
  } else {
    map = L.map('map').setView([8.4333, -82.4333], 10)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap'
    }).addTo(map)
  }

  districts.forEach((district, index) => {
    const bounds = [
      [district.lat - 0.12, district.lng - 0.12],
      [district.lat + 0.12, district.lng + 0.12]
    ]
    const rect = L.rectangle(bounds, {
      color: districtColors[index],
      weight: 2,
      fillColor: districtColors[index],
      fillOpacity: 0.15,
      dashArray: '5, 5'
    }).addTo(map)

    const corregimientosList = district.corregimientos.join(', ')
    rect.bindPopup(`
      <strong style="font-size:14px">${district.name}</strong><br/>
      <small><em>Corregimientos:</em></small><br/>
      <small>${corregimientosList}</small>
    `)

    const label = L.marker([district.lat, district.lng], {
      icon: L.divIcon({
        className: 'district-label',
        html: `<span style="background:rgba(0,0,0,0.7);color:#fff;padding:2px 6px;border-radius:4px;font-size:11px;white-space:nowrap;font-weight:600">${district.name}</span>`,
        iconSize: [100, 20],
        iconAnchor: [50, 10]
      })
    }).addTo(map)

    districtLayers.push(rect, label)
  })

  const colors = { pending: '#FFA726', assigned: '#42A5F5', completed: '#66BB6A', failed: '#EF5350' }

  mapData.value.forEach(client => {
    const color = colors[client.status] || '#9E9E9E'
    const marker = L.circleMarker([client.lat, client.lng], {
      radius: 8,
      fillColor: color,
      color: '#fff',
      weight: 2,
      opacity: 1,
      fillOpacity: 0.8
    }).addTo(map)

    marker.bindPopup(`
      <strong>${client.name}</strong><br/>
      📱 ${client.phone}<br/>
      📦 ${client.order_number}<br/>
      📍 ${client.address}<br/>
      Estado: ${client.status}
    `)
    markers.push(marker)
  })
}
</script>

<style>
.district-label {
  background: transparent !important;
  border: none !important;
}
</style>