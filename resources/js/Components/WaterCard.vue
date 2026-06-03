<script setup>
/**
 * WaterCard.vue
 * FILE PATH: resources/js/Components/WaterCard.vue
 *
 * Import on Dashboard.vue:
 *   import WaterCard from '@/Components/WaterCard.vue'
 *   <WaterCard />
 */
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const glasses   = ref(0)
const goal      = ref(8)
const loading   = ref(false)
const justAdded = ref(false)

const pct      = computed(() => Math.min(100, (glasses.value / goal.value) * 100))
const isFull   = computed(() => glasses.value >= goal.value)
const emoji    = computed(() => {
  if (isFull.value)        return '🎉'
  if (glasses.value >= 6)  return '💧'
  if (glasses.value >= 3)  return '🚰'
  return '😮‍💨'
})

const fetchToday = async () => {
  const { data } = await axios.get(route('water.today'))
  glasses.value = data.glasses
  goal.value    = data.goal
}

const addGlass = async () => {
  if (loading.value) return
  loading.value = true
  justAdded.value = true
  const { data } = await axios.post(route('water.add'))
  glasses.value = data.glasses
  loading.value = false
  setTimeout(() => justAdded.value = false, 600)
}

const removeGlass = async () => {
  if (loading.value || glasses.value === 0) return
  loading.value = true
  const { data } = await axios.post(route('water.remove'))
  glasses.value = data.glasses
  loading.value = false
}

onMounted(fetchToday)
</script>

<template>
  <div class="bg-gradient-to-br from-blue-500 to-cyan-500 text-white rounded-3xl p-6 shadow-xl shadow-blue-500/20 relative overflow-hidden">
    <!-- Background wave -->
    <div class="absolute bottom-0 left-0 right-0 transition-all duration-700 opacity-20 pointer-events-none"
      :style="`height: ${pct}%`">
      <svg viewBox="0 0 400 60" class="w-full" preserveAspectRatio="none">
        <path d="M0,30 C100,10 300,50 400,30 L400,60 L0,60 Z" fill="white" />
      </svg>
    </div>

    <!-- Content -->
    <div class="relative z-10">
      <div class="flex items-start justify-between mb-4">
        <div>
          <p class="text-[10px] font-black uppercase tracking-widest text-blue-100 mb-1">Water Today</p>
          <div class="flex items-baseline gap-1">
            <span class="text-5xl font-black tabular-nums">{{ glasses }}</span>
            <span class="text-blue-200 font-medium text-lg">/ {{ goal }}</span>
          </div>
          <p class="text-blue-100 text-sm font-medium mt-1">glasses {{ emoji }}</p>
        </div>

        <!-- Add / Undo buttons -->
        <div class="flex flex-col gap-2">
          <button @click="addGlass" :disabled="loading || isFull"
            class="w-12 h-12 rounded-2xl bg-white/20 hover:bg-white/30 active:scale-75 transition-all flex items-center justify-center text-xl font-black disabled:opacity-40"
            :class="justAdded ? 'scale-110' : ''">
            +
          </button>
          <button @click="removeGlass" :disabled="loading || glasses === 0"
            class="w-12 h-12 rounded-2xl bg-white/10 hover:bg-white/20 active:scale-75 transition-all flex items-center justify-center text-lg font-black disabled:opacity-30">
            -
          </button>
        </div>
      </div>

      <!-- Glass visualizer -->
      <div class="flex gap-1.5 flex-wrap">
        <div v-for="i in goal" :key="i"
          class="w-7 h-7 rounded-lg border-2 border-white/30 flex items-center justify-center text-sm transition-all duration-300"
          :class="i <= glasses ? 'bg-white text-blue-500' : 'bg-white/10 text-transparent'">
          💧
        </div>
      </div>

      <!-- Goal complete message -->
      <p v-if="isFull" class="mt-3 text-sm font-bold text-blue-100 animate-bounce">
        🎉 Daily goal reached! Great job staying hydrated!
      </p>
    </div>
  </div>
</template>