<script setup>
/**
 * Dashboard.vue / Dashboard/Index.vue
 * FILE PATH: resources/js/Pages/Dashboard.vue
 *            OR resources/js/Pages/Dashboard/Index.vue
 */
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Play, Flame, Check, Droplets, BookOpen, ChevronRight } from 'lucide-vue-next'
import ActivityRecommendations from '@/Components/ActivityRecommendations.vue'
import axios from 'axios'

const props = defineProps({
  user:             Object,
  stats:            Object,
  tasks:            { type: Array,  default: () => [] },
  habits:           { type: Array,  default: () => [] },
  weeklyFocus:      { type: Array,  default: () => [] },
  contributionData: { type: Object, default: () => ({ counts: {} }) },
  streakDays:       { type: Number, default: 0 },
  waterGlasses:     { type: Number, default: 0 },
  recentJournal:    { type: Object, default: null },
  quote:            { type: Object, default: () => ({ content: 'Keep going.', author: '' }) },
})

const safeRoute = (name, params) => {
  try { return route(name, params) } catch { return '#' }
}

// ── Date ──────────────────────────────────────────────────────
const today     = new Date()
const DAYS_FULL = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']
const DAYS_SHORT= ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']
const MONTHS    = ['January','February','March','April','May','June','July','August','September','October','November','December']
const todayDow  = today.getDay()

const weekStrip = computed(() =>
  DAYS_SHORT.map((label, i) => {
    const d = new Date(); d.setDate(d.getDate() - todayDow + i)
    return { label, date: d.getDate(), active: i === todayDow }
  })
)

// ── Habits ────────────────────────────────────────────────────
const habitDone = ref(Object.fromEntries(props.habits.map(h => [h.id, h.completed_today])))

const markHabit = async (id) => {
  habitDone.value[id] = !habitDone.value[id]
  try { await axios.patch(safeRoute('habits.complete', id)) } catch {}
}

const doneCount = computed(() => Object.values(habitDone.value).filter(Boolean).length)
const habitPct  = computed(() => props.habits.length ? Math.round((doneCount.value / props.habits.length) * 100) : 0)

// ── Water ─────────────────────────────────────────────────────
const glasses  = ref(props.waterGlasses ?? 0)
const GOAL     = 8
const addGlass = async () => { if (glasses.value >= GOAL) return; glasses.value++; try { await axios.post(safeRoute('water.add')) } catch {} }
const remGlass = async () => { if (glasses.value <= 0) return; glasses.value--; try { await axios.post(safeRoute('water.remove')) } catch {} }

// ── Contribution graph ────────────────────────────────────────
const contribDays = computed(() => {
  const days = []
  for (let i = 27; i >= 0; i--) {
    const d = new Date(); d.setDate(d.getDate() - i)
    const key = d.toISOString().split('T')[0]
    days.push({ key, count: props.contributionData?.counts?.[key] ?? 0, isToday: i === 0 })
  }
  return days
})

const cellColor = (count, isToday) => {
  if (isToday) return count > 0 ? 'bg-indigo-500 ring-2 ring-indigo-300 dark:ring-indigo-600' : 'bg-white dark:bg-slate-900 ring-2 ring-indigo-400'
  if (!count) return 'bg-slate-100 dark:bg-slate-800'
  if (count <= 1) return 'bg-emerald-200 dark:bg-emerald-900'
  if (count <= 3) return 'bg-emerald-400 dark:bg-emerald-600'
  return 'bg-emerald-600 dark:bg-emerald-500'
}

const formatFocus = (sec) => { const m = Math.floor((sec ?? 0) / 60); return m < 60 ? `${m}m` : `${Math.floor(m/60)}h ${m%60}m` }
const isOverdue   = (d) => d && new Date(d) < new Date(new Date().setHours(0,0,0,0))
const MOOD        = { great:'😄', good:'🙂', okay:'😐', bad:'😕', terrible:'😢' }
</script>

<template>
  <Head title="Home" />

  <div class="max-w-2xl mx-auto space-y-5 px-1 pb-28 md:pb-12">

    <!-- Header -->
    <div class="pt-2">
      <p class="text-xs font-black uppercase tracking-widest text-slate-400">
        {{ DAYS_FULL[todayDow] }}, {{ MONTHS[today.getMonth()] }} {{ today.getDate() }}
      </p>
      <h1 class="text-3xl font-black text-slate-800 dark:text-white mt-1 tracking-tight">
        Ready, <span class="text-teal-500">{{ user?.name?.split(' ')[0] ?? 'there' }}</span>? 👊
      </h1>
      <div class="mt-3 bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-2xl px-4 py-3">
        <p class="text-sm text-slate-600 dark:text-slate-300 italic leading-relaxed">"{{ quote?.content }}"</p>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">— {{ quote?.author }}</p>
      </div>
    </div>

    <!-- Week strip — TODAY highlighted -->
    <div class="flex gap-1.5">
      <div v-for="day in weekStrip" :key="day.label"
        class="flex flex-col items-center gap-1 flex-1 py-2.5 rounded-2xl transition-all"
        :class="day.active
          ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25'
          : 'bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 text-slate-500'"
      >
        <span class="text-[9px] font-black uppercase tracking-wider">{{ day.label }}</span>
        <span class="text-base font-black">{{ day.date }}</span>
        <div class="w-1.5 h-1.5 rounded-full" :class="day.active ? 'bg-white/60' : 'bg-transparent'" />
      </div>
    </div>

    <!-- Stats strip -->
    <div class="grid grid-cols-4 gap-2">
      <div v-for="[icon, value, label, color] in [
        ['⏱', formatFocus(stats?.total_focus_seconds_today), 'Focused',  'text-teal-500'],
        ['📋', stats?.tasks_todo ?? 0,            'To Do',   'text-amber-500'],
        ['✅', stats?.tasks_completed_today ?? 0,  'Done',    'text-emerald-500'],
        ['🔥', streakDays ?? 0,                    'Streak',  'text-orange-500'],
      ]" :key="label"
        class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-2xl p-3 text-center"
      >
        <div class="text-lg mb-0.5">{{ icon }}</div>
        <div class="text-base font-black" :class="color">{{ value }}</div>
        <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">{{ label }}</div>
      </div>
    </div>

    <!-- 🚀 BIG FOCUS BUTTON -->
    <Link :href="safeRoute('focus.index')"
      class="flex items-center justify-between w-full bg-teal-500 hover:bg-teal-600 active:scale-95 text-white rounded-3xl px-6 py-5 shadow-2xl shadow-teal-500/30 transition-all group">
      <div>
        <p class="text-[10px] font-black uppercase tracking-widest text-teal-100 mb-1">Ready to work?</p>
        <p class="text-2xl font-black tracking-tight leading-none">Start Focus Session</p>
      </div>
      <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shrink-0">
        <Play class="w-7 h-7 fill-current" />
      </div>
    </Link>

    <!-- Activity Recommendations + AI Rewards -->
    <ActivityRecommendations
      :sessions-today="stats?.next_count ?? 0"
      :tasks="tasks"
      :user-name="user?.name?.split(' ')[0] ?? 'there'"
    />

    <!-- Habits -->
    <div class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-3xl p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="font-black text-slate-800 dark:text-white text-base">Today's Habits</h2>
          <p class="text-xs text-slate-400 mt-0.5">{{ doneCount }}/{{ habits.length }} done</p>
        </div>
        <Link :href="safeRoute('habits.index')" class="text-xs font-bold text-teal-500 flex items-center gap-1">
          See all <ChevronRight class="w-3 h-3" />
        </Link>
      </div>
      <div class="h-2 bg-slate-100 dark:bg-slate-800 rounded-full mb-4 overflow-hidden">
        <div class="h-full rounded-full bg-teal-500 transition-all duration-500" :style="`width:${habitPct}%`" />
      </div>
      <div class="space-y-2">
        <div v-for="habit in habits.slice(0, 6)" :key="habit.id"
          class="flex items-center gap-3 p-3 rounded-2xl transition-all"
          :class="habitDone[habit.id] ? 'bg-teal-50 dark:bg-teal-500/10' : 'bg-slate-50 dark:bg-slate-800/50'"
        >
          <button @click="markHabit(habit.id)"
            class="w-7 h-7 rounded-xl border-2 flex items-center justify-center transition-all active:scale-75 shrink-0"
            :class="habitDone[habit.id] ? 'bg-teal-500 border-teal-500' : 'border-slate-300 dark:border-slate-600 hover:border-teal-400'"
          >
            <Check v-if="habitDone[habit.id]" class="w-4 h-4 text-white stroke-[3px]" />
          </button>
          <span class="text-sm font-bold flex-1 truncate"
            :class="habitDone[habit.id] ? 'line-through text-slate-400' : 'text-slate-700 dark:text-slate-200'">
            {{ habit.title }}
          </span>
          <div v-if="(habit.current_streak ?? 0) > 0" class="flex items-center gap-0.5 shrink-0">
            <Flame class="w-3.5 h-3.5 text-orange-400" />
            <span class="text-xs font-black text-slate-500">{{ habit.current_streak }}</span>
          </div>
        </div>
        <p v-if="!habits.length" class="text-center py-6 text-sm text-slate-400">
          No habits yet. <Link :href="safeRoute('habits.index')" class="text-teal-500 font-bold">Add one →</Link>
        </p>
      </div>
      <div v-if="doneCount === habits.length && habits.length > 0"
        class="mt-4 bg-teal-500 text-white text-sm font-black rounded-2xl p-3 text-center">
        🎉 All habits done today! You're on fire!
      </div>
    </div>

    <!-- Tasks -->
    <div class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-3xl p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-black text-slate-800 dark:text-white text-base">Top Tasks</h2>
        <Link :href="safeRoute('tasks.index')" class="text-xs font-bold text-teal-500 flex items-center gap-1">
          See all <ChevronRight class="w-3 h-3" />
        </Link>
      </div>
      <div class="space-y-2">
        <div v-for="task in tasks.slice(0, 4)" :key="task.id"
          class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-2xl group">
          <div class="w-2 h-2 rounded-full shrink-0"
            :class="{ 'bg-red-500': task.difficulty==='hard', 'bg-amber-400': task.difficulty==='medium', 'bg-emerald-400': task.difficulty==='easy' }" />
          <span class="text-sm font-bold text-slate-700 dark:text-slate-200 flex-1 truncate">{{ task.title }}</span>
          <span v-if="task.deadline"
            class="text-[9px] font-black px-2 py-0.5 rounded-full shrink-0"
            :class="isOverdue(task.deadline) ? 'bg-red-100 text-red-600' : 'bg-slate-200 dark:bg-slate-700 text-slate-500'">
            {{ task.deadline }}
          </span>
          <Link :href="`${safeRoute('focus.index')}?task_id=${task.id}`"
            class="opacity-0 group-hover:opacity-100 w-7 h-7 bg-teal-500 rounded-lg flex items-center justify-center transition-all shrink-0 active:scale-90">
            <Play class="w-3.5 h-3.5 text-white fill-current" />
          </Link>
        </div>
        <p v-if="!tasks.length" class="text-center py-6 text-sm text-slate-400">
          No tasks yet. <Link :href="safeRoute('tasks.index')" class="text-teal-500 font-bold">Add one →</Link>
        </p>
      </div>
    </div>

    <!-- Water + Journal -->
    <div class="grid grid-cols-2 gap-4">
      <!-- Water -->
      <div class="bg-gradient-to-br from-blue-500 to-cyan-400 rounded-3xl p-5 text-white relative overflow-hidden shadow-xl shadow-blue-500/20">
        <div class="absolute bottom-0 left-0 right-0 rounded-b-3xl bg-white/10 transition-all duration-700"
          :style="`height:${Math.round((glasses/GOAL)*100)}%`" />
        <div class="relative z-10">
          <Droplets class="w-5 h-5 mb-2 opacity-80" />
          <div class="text-3xl font-black">{{ glasses }}<span class="text-sm font-medium text-blue-100">/{{ GOAL }}</span></div>
          <p class="text-[10px] font-black uppercase tracking-widest text-blue-100 mt-0.5">glasses today</p>
          <div class="flex gap-2 mt-3">
            <button @click="addGlass" :disabled="glasses >= GOAL"
              class="flex-1 h-9 bg-white/20 hover:bg-white/30 active:scale-90 rounded-xl font-black text-lg transition-all disabled:opacity-30">+</button>
            <button @click="remGlass" :disabled="glasses <= 0"
              class="flex-1 h-9 bg-white/10 hover:bg-white/20 active:scale-90 rounded-xl font-black text-lg transition-all disabled:opacity-30">−</button>
          </div>
          <div class="flex flex-wrap gap-1 mt-3">
            <div v-for="i in GOAL" :key="i" class="w-4 h-4 rounded-md transition-all"
              :class="i <= glasses ? 'bg-white' : 'bg-white/20'" />
          </div>
        </div>
      </div>

      <!-- Journal -->
      <Link :href="safeRoute('journal.index')"
        class="bg-gradient-to-br from-violet-600 to-indigo-700 rounded-3xl p-5 text-white relative overflow-hidden shadow-xl shadow-violet-500/20 flex flex-col active:scale-95 transition-all">
        <BookOpen class="w-5 h-5 mb-2 opacity-80" />
        <div v-if="recentJournal">
          <p class="text-[10px] font-black uppercase tracking-widest text-violet-200">Last entry</p>
          <p class="text-sm font-bold mt-1 line-clamp-2 leading-tight">
            {{ MOOD[recentJournal.mood] ?? '📝' }} {{ recentJournal.title }}
          </p>
          <p class="text-[10px] text-violet-300 mt-1">{{ recentJournal.created_at }}</p>
        </div>
        <div v-else>
          <p class="text-sm font-bold text-violet-100 leading-snug">Write today's entry</p>
          <p class="text-[10px] text-violet-300 mt-1">Nothing yet today</p>
        </div>
        <div class="mt-auto pt-4">
          <div class="bg-white/20 rounded-xl px-3 py-2 text-xs font-black text-center tracking-wider">
            {{ recentJournal ? 'Open Journal →' : '+ New Entry' }}
          </div>
        </div>
      </Link>
    </div>

    <!-- GitHub contribution graph -->
    <div class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-3xl p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="font-black text-slate-800 dark:text-white text-sm">Activity — Last 4 Weeks</h2>
          <p class="text-[10px] text-slate-400 mt-0.5">Each square = focus sessions that day</p>
        </div>
        <Link :href="safeRoute('analytics.index')" class="text-[10px] font-black text-teal-500 uppercase tracking-widest">
          Full Stats →
        </Link>
      </div>
      <div class="grid grid-cols-7 gap-1.5">
        <div v-for="day in contribDays" :key="day.key"
          :title="`${day.key}: ${day.count} session(s)`"
          class="aspect-square rounded-md transition-all cursor-default"
          :class="cellColor(day.count, day.isToday)"
        />
      </div>
      <div class="flex items-center gap-2 mt-3 text-[9px] text-slate-400 font-semibold">
        <span>Less</span>
        <div class="w-3 h-3 rounded-sm bg-slate-100 dark:bg-slate-800" />
        <div class="w-3 h-3 rounded-sm bg-emerald-200" />
        <div class="w-3 h-3 rounded-sm bg-emerald-400" />
        <div class="w-3 h-3 rounded-sm bg-emerald-600" />
        <span>More</span>
        <div class="ml-2 w-3 h-3 rounded-sm bg-indigo-500 ring-1 ring-indigo-300" />
        <span>Today</span>
      </div>
    </div>

  </div>

  <!-- Mobile bottom nav -->
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-[#242526]/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 flex">
    <Link v-for="item in [
      { label: 'Home',   icon: '🏠', r: 'dashboard'       },
      { label: 'Tasks',  icon: '📋', r: 'tasks.index'     },
      { label: 'Habits', icon: '🔁', r: 'habits.index'    },
      { label: 'Focus',  icon: '⏱',  r: 'focus.index'     },
      { label: 'Stats',  icon: '📊', r: 'analytics.index' },
    ]" :key="item.r"
      :href="safeRoute(item.r)"
      class="flex-1 flex flex-col items-center py-3 gap-0.5 transition-all"
      :class="route().current(item.r) ? 'text-teal-500' : 'text-slate-400'"
    >
      <span class="text-lg leading-none">{{ item.icon }}</span>
      <span class="text-[9px] font-bold uppercase tracking-wider">{{ item.label }}</span>
      <div class="w-1 h-1 rounded-full" :class="route().current(item.r) ? 'bg-teal-500' : 'bg-transparent'" />
    </Link>
  </nav>
</template>