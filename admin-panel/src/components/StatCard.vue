<template>
  <div class="card hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
          {{ title }}
        </p>
        <p
          class="text-3xl font-bold"
          :class="textColor"
        >
          {{ formattedValue }}
        </p>
        <p
          v-if="subtitle"
          class="text-xs text-gray-400 dark:text-gray-500 mt-1"
        >
          {{ subtitle }}
        </p>
      </div>
      <div
        class="w-12 h-12 rounded-xl flex items-center justify-center"
        :class="bgColor"
      >
        <svg
          v-if="icon === 'users'"
          class="w-6 h-6"
          :class="iconColor"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
          />
        </svg>
        <svg
          v-else-if="icon === 'check'"
          class="w-6 h-6"
          :class="iconColor"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <svg
          v-else-if="icon === 'clock'"
          class="w-6 h-6"
          :class="iconColor"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <svg
          v-else-if="icon === 'spinner'"
          class="w-6 h-6"
          :class="iconColor"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
          />
        </svg>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: 'users' },
  color: { type: String, default: 'blue' },
})

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.value.toLocaleString()
  }
  return props.value
})

const colorMap = {
  blue: { text: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-900/20', icon: 'text-blue-500 dark:text-blue-400' },
  green: { text: 'text-green-600 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-900/20', icon: 'text-green-500 dark:text-green-400' },
  orange: { text: 'text-orange-600 dark:text-orange-400', bg: 'bg-orange-50 dark:bg-orange-900/20', icon: 'text-orange-500 dark:text-orange-400' },
  purple: { text: 'text-purple-600 dark:text-purple-400', bg: 'bg-purple-50 dark:bg-purple-900/20', icon: 'text-purple-500 dark:text-purple-400' },
  red: { text: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-900/20', icon: 'text-red-500 dark:text-red-400' },
}

const textColor = computed(() => colorMap[props.color]?.text || 'text-blue-600')
const bgColor = computed(() => colorMap[props.color]?.bg || 'bg-blue-50')
const iconColor = computed(() => colorMap[props.color]?.icon || 'text-blue-500')
</script>