<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Trophy, Lock, Star } from 'lucide-vue-next'

const props = defineProps({
  achievements:     Array,   // All achievements from DB
  userAchievements: Array,   // Achievements the user has unlocked
  totalPoints:      Number,
})

// Map user unlocks to a Set for fast lookup
const unlockedIds = computed(() => new Set(props.userAchievements?.map(ua => ua.achievement_id) ?? []))

const isUnlocked = (id) => unlockedIds.value.has(id)

const unseenCount = computed(() =>
  props.userAchievements?.filter(ua => !ua.seen).length ?? 0
)

// Mark all as seen when this page is visited
if (unseenCount.value > 0) {
  router.patch(route('achievements.markSeen'), {}, { preserveScroll: true })
}

// Group by category
const grouped = computed(() => {
  const groups = {}
  for (const a of props.achievements ?? []) {
    if (!groups[a.category]) groups[a.category] = []
    groups[a.category].push(a)
  }
  return groups
})

const categoryLabels = {
  habits: '🔁 Habits',
  tasks:  '📋 Tasks',
  focus:  '⏱ Focus',
  streak: '🔥 Streaks',
}

const tierColors = {
  bronze:   'border-amber-600/50 bg-amber-50   dark:bg-amber-900/10   text-amber-700  dark:text-amber-400',
  silver:   'border-slate-400/50  bg-slate-50   dark:bg-slate-800/50   text-slate-600  dark:text-slate-300',
  gold:     'border-yellow-400/60 bg-yellow-50  dark:bg-yellow-900/10  text-yellow-700 dark:text-yellow-400',
  platinum: 'border-violet-400/50 bg-violet-50  dark:bg-violet-900/10  text-violet-700 dark:text-violet-400',
}

const unlockedStyle = 'ring-2 ring-teal-400 dark:ring-teal-500 bg-white dark:bg-[#242526] shadow-xl shadow-teal-500/10'
</script>

<template>
  <Head title="Achievements" />
  <div class="max-w-5xl mx-auto pb-16 space-y-10">

    <!-- Header -->
    <div class="bg-gradient-to-tr from-yellow-500 via-amber-500 to-orange-500 rounded-3xl p-10 text-white shadow-2xl relative overflow-hidden">
      <div class="absolute -right-8 -top-8 w-48 h-48 bg-white/10 rounded-full pointer-events-none" />
      <Trophy class="w-12 h-12 mb-4 drop-shadow-lg" />
      <h1 class="text-4xl font-black tracking-tight">Achievements</h1>
      <p class="mt-2 text-yellow-100 font-medium">
        Unlock badges by building habits, crushing tasks, and staying focused.
      </p>
      <div class="mt-6 flex flex-wrap gap-4">
        <div class="bg-white/20 backdrop-blur-sm rounded-2xl px-5 py-3">
          <span class="text-3xl font-black">{{ userAchievements?.length ?? 0 }}</span>
          <p class="text-[10px] font-black uppercase tracking-widest text-yellow-100 mt-0.5">Unlocked</p>
        </div>
        <div class="bg-white/20 backdrop-blur-sm rounded-2xl px-5 py-3">
          <span class="text-3xl font-black">{{ totalPoints ?? 0 }}</span>
          <p class="text-[10px] font-black uppercase tracking-widest text-yellow-100 mt-0.5">⭐ Points</p>
        </div>
        <div class="bg-white/20 backdrop-blur-sm rounded-2xl px-5 py-3">
          <span class="text-3xl font-black">{{ achievements?.length ?? 0 }}</span>
          <p class="text-[10px] font-black uppercase tracking-widest text-yellow-100 mt-0.5">Total Badges</p>
        </div>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-2xl p-5 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <span class="font-black text-sm text-slate-700 dark:text-white">Overall Progress</span>
        <span class="text-sm font-bold text-slate-400">
          {{ userAchievements?.length ?? 0 }} / {{ achievements?.length ?? 0 }}
        </span>
      </div>
      <div class="h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
        <div class="h-full bg-gradient-to-r from-teal-400 to-amber-400 rounded-full transition-all duration-700"
          :style="`width: ${Math.round(((userAchievements?.length ?? 0) / Math.max(achievements?.length ?? 1, 1)) * 100)}%`" />
      </div>
    </div>

    <!-- Achievement groups by category -->
    <div v-for="(items, category) in grouped" :key="category" class="space-y-4">
      <h2 class="text-sm font-black uppercase tracking-widest text-slate-400">
        {{ categoryLabels[category] ?? category }}
      </h2>

      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div v-for="a in items" :key="a.id"
          class="border rounded-2xl p-5 flex flex-col items-center text-center gap-2 transition-all duration-300 relative"
          :class="[
            isUnlocked(a.id)
              ? unlockedStyle
              : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40 opacity-50 grayscale',
            tierColors[a.tier] ?? ''
          ]"
        >
          <!-- New badge -->
          <span v-if="isUnlocked(a.id) && userAchievements?.find(ua => ua.achievement_id === a.id && !ua.seen)"
            class="absolute -top-2 -right-2 bg-teal-500 text-white text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full shadow">
            NEW
          </span>

          <!-- Icon -->
          <span class="text-4xl leading-none">{{ a.icon }}</span>

          <!-- Title + description -->
          <div>
            <p class="font-black text-sm leading-tight">{{ a.title }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-snug">{{ a.description }}</p>
          </div>

          <!-- Points -->
          <div class="flex items-center gap-1 text-[10px] font-black uppercase tracking-widest mt-auto pt-2 border-t border-current/10 w-full justify-center"
            :class="isUnlocked(a.id) ? '' : 'text-slate-400'">
            <Star class="w-3 h-3" />
            {{ a.points }} pts
          </div>

          <!-- Locked icon -->
          <Lock v-if="!isUnlocked(a.id)" class="absolute top-3 right-3 w-3.5 h-3.5 text-slate-400" />
        </div>
      </div>
    </div>
  </div>
</template>