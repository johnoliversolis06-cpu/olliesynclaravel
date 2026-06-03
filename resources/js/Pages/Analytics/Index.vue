<script setup>
/**
 * Analytics/Index.vue
 * FILE PATH: resources/js/Pages/Analytics/Index.vue
 *
 * ✅ Full 52-week (1 year) GitHub contribution graph
 * ✅ Good vs Bad habits bar chart
 * ✅ Task category pie chart
 * ✅ Weekly focus bar chart
 */
import { Head } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Activity, Clock, CheckCircle2, Flame, Award } from 'lucide-vue-next'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS, Title, Tooltip, Legend,
  BarElement, ArcElement, CategoryScale, LinearScale
} from 'chart.js'
ChartJS.register(Title, Tooltip, Legend, BarElement, ArcElement, CategoryScale, LinearScale)

const props = defineProps({
  dashboardStats:   Object,
  weeklyFocus:      Array,
  habitTypeWeekly:  Array,
  categoryFocus:    Array,
  contributionData: Object,  // { counts: { '2025-06-01': 3, ... } } — 365 days
  monthlyTrend:     Array,
})

// ── 1. Focus bar chart ────────────────────────────────────────
const focusChartData = computed(() => ({
  labels:   props.weeklyFocus?.map(d => d.day) ?? [],
  datasets: [{
    label: 'Focus Minutes',
    backgroundColor: '#0CAF89',
    hoverBackgroundColor: '#6366f1',
    borderRadius: 8,
    borderSkipped: false,
    data: props.weeklyFocus?.map(d => Math.floor(d.seconds / 60)) ?? [],
  }]
}))
const focusChartOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { backgroundColor: '#18191A', bodyColor: '#0CAF89', bodyFont: { weight: 'bold' } } },
  scales: { x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { weight: 'bold' } } }, y: { display: false, beginAtZero: true } }
}

// ── 2. Good vs Bad habits ──────────────────────────────────────
const habitChartData = computed(() => ({
  labels:   props.habitTypeWeekly?.map(d => d.day) ?? [],
  datasets: [
    { label: '✅ Good Habits',      backgroundColor: '#10b981', borderRadius: 6, data: props.habitTypeWeekly?.map(d => d.positive ?? 0) ?? [] },
    { label: '🚫 Bad Habits Resisted', backgroundColor: '#f87171', borderRadius: 6, data: props.habitTypeWeekly?.map(d => d.negative ?? 0) ?? [] },
  ]
}))
const habitChartOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: true, position: 'top', labels: { font: { weight: 'bold', size: 11 }, color: '#6b7280', boxWidth: 10 } } },
  scales: { x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { weight: 'bold' } } }, y: { display: false, beginAtZero: true } }
}

// ── 3. Category pie ───────────────────────────────────────────
const PIE_COLORS = ['#6366f1','#0CAF89','#f59e0b','#ec4899','#3b82f6','#8b5cf6','#14b8a6']
const pieChartData = computed(() => {
  const data = props.categoryFocus ?? []
  if (!data.length) return { labels: ['No data yet'], datasets: [{ data: [1], backgroundColor: ['#e2e8f0'], borderWidth: 0 }] }
  return {
    labels:   data.map(c => c.category ?? 'General'),
    datasets: [{ data: data.map(c => Math.round(c.total_seconds / 60)), backgroundColor: PIE_COLORS.slice(0, data.length), borderWidth: 0, hoverOffset: 8 }]
  }
})
const pieChartOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { position: 'right', labels: { font: { weight: 'bold', size: 11 }, color: '#6b7280', padding: 12, boxWidth: 10, borderRadius: 4 } }, tooltip: { backgroundColor: '#18191A', callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} min` } } }
}

// ── 4. FULL YEAR GitHub Contribution Graph (52 weeks) ─────────
const WEEKS = 52
const today = new Date()

// Build 364 days (52 weeks × 7) ending today
const allDays = computed(() => {
  const days = []
  // Start from 52 weeks ago (Monday of that week ideally)
  const start = new Date(today)
  start.setDate(start.getDate() - (WEEKS * 7 - 1))
  for (let i = 0; i < WEEKS * 7; i++) {
    const d   = new Date(start); d.setDate(start.getDate() + i)
    const key = d.toISOString().split('T')[0]
    const todayKey = today.toISOString().split('T')[0]
    days.push({ key, count: props.contributionData?.counts?.[key] ?? 0, isToday: key === todayKey, d })
  }
  return days
})

// Group into 52 columns of 7
const weeks = computed(() => {
  const cols = []
  for (let w = 0; w < WEEKS; w++) cols.push(allDays.value.slice(w * 7, w * 7 + 7))
  return cols
})

// Month label for the top — show when month changes between columns
const MONTH_LABELS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
const monthLabels = computed(() => {
  return weeks.value.map((week, wi) => {
    if (!week[0]) return null
    const m    = week[0].d.getMonth()
    const prev = wi > 0 ? weeks.value[wi-1][0]?.d.getMonth() : -1
    return m !== prev ? MONTH_LABELS[m] : null
  })
})

// Day-of-week labels on the left
const DOW_LABELS = ['','Mon','','Wed','','Fri','']

const cellColor = (count, isToday) => {
  if (isToday) return count > 0
    ? 'bg-indigo-500 ring-2 ring-indigo-300 dark:ring-indigo-700'
    : 'bg-white dark:bg-slate-900 ring-2 ring-indigo-400 dark:ring-indigo-600'
  if (!count) return 'bg-slate-100 dark:bg-slate-800'
  if (count <= 1) return 'bg-emerald-200 dark:bg-emerald-900'
  if (count <= 3) return 'bg-emerald-400 dark:bg-emerald-700'
  if (count <= 5) return 'bg-emerald-500 dark:bg-emerald-600'
  return 'bg-emerald-600 dark:bg-emerald-500'
}

const hoveredDay   = ref(null)
const formatFocus  = (sec) => { const m = Math.floor((sec ?? 0) / 60); return m < 60 ? `${m}m` : `${Math.floor(m/60)}h ${m%60}m` }
</script>

<template>
  <Head title="My Stats" />
  <div class="max-w-7xl mx-auto space-y-6 pb-24 md:pb-10">

    <!-- Header -->
    <div class="flex justify-between items-end pb-4 border-b border-slate-200 dark:border-slate-800">
      <div>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-800 dark:text-white flex items-center gap-4">
          My Stats <Activity class="text-indigo-500 w-8 h-8" />
        </h1>
        <p class="text-slate-500 font-medium mt-1 text-sm">See how you are doing over time.</p>
      </div>
    </div>

    <!-- Big numbers -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-teal-500 p-5 rounded-2xl text-white shadow-lg shadow-teal-500/25 flex flex-col">
        <Clock class="w-5 h-5 mb-3 opacity-70" />
        <span class="text-3xl font-black tabular-nums">{{ formatFocus(dashboardStats?.total_focus_seconds_today) }}</span>
        <span class="text-teal-100 text-[10px] font-black uppercase tracking-widest mt-1">Focus Today</span>
      </div>
      <div class="bg-white dark:bg-[#242526] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
        <CheckCircle2 class="w-5 h-5 mb-3 text-slate-300" />
        <span class="text-3xl font-black text-slate-800 dark:text-white tabular-nums">{{ dashboardStats?.tasks_completed_today ?? 0 }}</span>
        <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest mt-1">Tasks Done</span>
      </div>
      <div class="bg-white dark:bg-[#242526] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
        <Flame class="w-5 h-5 mb-3 text-slate-300" />
        <span class="text-3xl font-black text-slate-800 dark:text-white tabular-nums">{{ dashboardStats?.habits_completed_today ?? 0 }}</span>
        <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest mt-1">Habits Done</span>
      </div>
      <div class="bg-white dark:bg-[#242526] p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
        <Award class="w-5 h-5 mb-3 text-slate-300" />
        <span class="text-3xl font-black text-slate-800 dark:text-white tabular-nums">{{ dashboardStats?.pomodoros_today ?? 0 }}</span>
        <span class="text-slate-400 text-[10px] font-black uppercase tracking-widest mt-1">Pomodoros</span>
      </div>
    </div>

    <!-- Charts row 1 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="bg-white dark:bg-[#242526] p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <h3 class="font-black text-slate-700 dark:text-white text-sm mb-1">Focus Minutes This Week</h3>
        <p class="text-[10px] text-slate-400 font-semibold mb-4">Minutes focused per day</p>
        <div class="h-48"><Bar :data="focusChartData" :options="focusChartOptions" /></div>
      </div>
      <div class="bg-white dark:bg-[#242526] p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <h3 class="font-black text-slate-700 dark:text-white text-sm mb-1">Good vs Bad Habits This Week</h3>
        <p class="text-[10px] text-slate-400 font-semibold mb-4">Good built ✅ vs bad resisted 🚫</p>
        <div class="h-48"><Bar :data="habitChartData" :options="habitChartOptions" /></div>
      </div>
    </div>

    <!-- Charts row 2 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="bg-white dark:bg-[#242526] p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
        <h3 class="font-black text-slate-700 dark:text-white text-sm mb-1">What You Focus on Most</h3>
        <p class="text-[10px] text-slate-400 font-semibold mb-4">Focus time by task category</p>
        <div class="h-48"><Doughnut :data="pieChartData" :options="pieChartOptions" /></div>
        <p v-if="!categoryFocus?.length" class="text-center text-slate-400 text-xs mt-3">Start focusing on tasks to see categories here.</p>
      </div>

      <!-- Consistency bar -->
      <div class="bg-white dark:bg-[#242526] p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col">
        <h3 class="font-black text-slate-700 dark:text-white text-sm mb-1">Weekly Consistency</h3>
        <p class="text-[10px] text-slate-400 font-semibold mb-5">Focus minutes per day this week</p>
        <div class="space-y-3 flex-1">
          <div v-for="day in (weeklyFocus ?? []).slice(0,7)" :key="day.day" class="flex items-center gap-3">
            <span class="w-8 text-[10px] font-black text-slate-400">{{ day.day }}</span>
            <div class="flex-1 h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
              <div class="h-full bg-teal-500 rounded-full transition-all duration-700"
                :style="`width:${Math.min(100, Math.floor(day.seconds/36))}%`" />
            </div>
            <span class="text-[10px] font-bold text-slate-400 w-10 text-right">{{ Math.floor(day.seconds/60) }}m</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ FULL YEAR GITHUB CONTRIBUTION GRAPH (52 weeks) ══ -->
    <div class="bg-white dark:bg-[#242526] p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="font-black text-slate-700 dark:text-white text-sm">Activity History — Full Year</h3>
          <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Your focus sessions over the last 12 months · hover for details</p>
        </div>
        <!-- Hovered day tooltip -->
        <div v-if="hoveredDay" class="bg-slate-800 text-white px-3 py-1.5 rounded-xl text-xs font-bold">
          {{ hoveredDay.key }}: {{ hoveredDay.count }} session{{ hoveredDay.count !== 1 ? 's' : '' }}
        </div>
      </div>

      <div class="overflow-x-auto pb-2">
        <div class="inline-flex flex-col gap-0 min-w-max">

          <!-- Month labels row -->
          <div class="flex gap-[3px] mb-1 pl-7">
            <div v-for="(label, wi) in monthLabels" :key="wi" class="w-[13px]">
              <span v-if="label" class="text-[9px] font-bold text-slate-400 whitespace-nowrap">{{ label }}</span>
            </div>
          </div>

          <!-- Graph grid -->
          <div class="flex gap-[3px]">
            <!-- Day-of-week labels on left -->
            <div class="flex flex-col gap-[3px] mr-1 pt-0">
              <div v-for="(label, i) in DOW_LABELS" :key="i" class="h-[13px] flex items-center">
                <span class="text-[9px] text-slate-300 dark:text-slate-700 font-medium w-6 text-right">{{ label }}</span>
              </div>
            </div>

            <!-- Contribution squares — 52 columns × 7 rows -->
            <div v-for="(week, wi) in weeks" :key="wi" class="flex flex-col gap-[3px]">
              <div v-for="(day, di) in week" :key="di"
                @mouseenter="hoveredDay = day"
                @mouseleave="hoveredDay = null"
                :title="`${day.key}: ${day.count} session(s)`"
                class="w-[13px] h-[13px] rounded-sm cursor-pointer transition-opacity hover:opacity-70"
                :class="cellColor(day.count, day.isToday)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex items-center gap-2 mt-4 text-[9px] text-slate-400 font-semibold">
        <span>Less</span>
        <div class="w-3 h-3 rounded-sm bg-slate-100 dark:bg-slate-800" />
        <div class="w-3 h-3 rounded-sm bg-emerald-200" />
        <div class="w-3 h-3 rounded-sm bg-emerald-400" />
        <div class="w-3 h-3 rounded-sm bg-emerald-500" />
        <div class="w-3 h-3 rounded-sm bg-emerald-600" />
        <span>More</span>
        <div class="ml-3 w-3 h-3 rounded-sm bg-indigo-500 ring-1 ring-indigo-300 dark:ring-indigo-700" />
        <span>Today</span>
        <span class="ml-auto">{{ allDays.filter(d => d.count > 0).length }} active days this year</span>
      </div>
    </div>

  </div>
</template>