<script setup>
/**
 * Focus/Index.vue
 * FILE PATH: resources/js/Pages/Focus/Index.vue
 *
 * ✅ AI reward shown after every completed session
 * ✅ Overlapping ambient sounds
 * ✅ Switch prompt when selecting new task while timer runs
 * ✅ Auto-selects task from ?task_id= URL param
 * ✅ Selector locked while timer is running
 */
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Play, Pause, Square, ChevronDown, Timer, Coffee, Target, Check, X } from 'lucide-vue-next'
import FocusAmbientSounds from '@/Components/FocusAmbientSounds.vue'

const props = defineProps({
  activeSession: Object,
  sessionState:  Object,
  user:          Object,
  todayStats:    Object,
  tasks:         Array,
  habits:        Array,
})

// ── Timer state ───────────────────────────────────────────────
const sessionType = ref(props.activeSession?.session_type ?? 'pomodoro')
const status      = ref(props.sessionState?.status        ?? 'idle')
const mode        = ref(props.sessionState?.mode          ?? 'focus')
const rawElapsed  = ref(props.sessionState?.elapsed       ?? 0)

// ── Selector state ────────────────────────────────────────────
const selectedTaskId  = ref(props.activeSession?.task_id  ?? null)
const selectedHabitId = ref(props.activeSession?.habit_id ?? null)
const selectorOpen    = ref(false)

// ── Switch prompt ─────────────────────────────────────────────
const showSwitchPrompt = ref(false)
const pendingTaskId    = ref(null)
const pendingHabitId   = ref(null)

// ── AI REWARD state ───────────────────────────────────────────
const showReward   = ref(false)
const rewardMsg    = ref(null)
const rewardLoading= ref(false)
let   rewardTimer  = null

const REWARDS = [
  { emoji: '🔥', msg: 'Session complete. Your brain just got stronger. Take a real break.' },
  { emoji: '💪', msg: "That's real progress. Most people quit before starting. You won today." },
  { emoji: '⭐', msg: 'Deep work done. Step away for 5 minutes — your brain consolidates memory during rest.' },
  { emoji: '🚀', msg: 'Momentum is building. One more session and you will be at peak performance.' },
  { emoji: '🌱', msg: 'Consistency over intensity. You showed up today. That is the whole game.' },
  { emoji: '🧠', msg: 'Flow state achieved. Drink some water and come back in 5.' },
  { emoji: '🏆', msg: 'You just outworked most people who only planned to be productive today.' },
  { emoji: '☕', msg: 'Take a coffee break. You earned it. Come back even sharper.' },
]

const triggerReward = async () => {
  // Clear any existing reward timer
  if (rewardTimer) clearTimeout(rewardTimer)

  showReward.value    = true
  rewardLoading.value = true
  rewardMsg.value     = null

  // Try Claude API for personalized reward
  try {
    const sessionCount = (props.todayStats?.next_count ?? 0) + 1
    const taskName     = selectedLabel.value ?? 'your session'
    const response = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model:      'claude-sonnet-4-20250514',
        max_tokens: 60,
        messages: [{
          role:    'user',
          content: `You are an energetic focus coach. The user just finished focus session #${sessionCount} today on "${taskName}". Write ONE reward message, max 18 words, direct and personal. No emojis in text. Examples: "Three sessions in. You are building real momentum. Time for a 5 minute walk." or "That is ${sessionCount} down. Your brain needs a short break before the next one."`
        }]
      })
    })
    if (response.ok) {
      const data = await response.json()
      const text = data.content?.[0]?.text?.trim()
      if (text) {
        rewardMsg.value     = { emoji: '🤖', msg: text }
        rewardLoading.value = false
        rewardTimer = setTimeout(() => showReward.value = false, 10000)
        return
      }
    }
  } catch {}

  // Fallback to static rewards
  const idx = Math.floor(Math.random() * REWARDS.length)
  rewardMsg.value     = REWARDS[idx]
  rewardLoading.value = false
  rewardTimer = setTimeout(() => showReward.value = false, 8000)
}

// ── Auto-select from URL ──────────────────────────────────────
onMounted(() => {
  const params  = new URLSearchParams(window.location.search)
  const taskId  = params.get('task_id')
  const habitId = params.get('habit_id')
  if (taskId  && !props.activeSession) { selectedTaskId.value  = parseInt(taskId);  selectedHabitId.value = null }
  if (habitId && !props.activeSession) { selectedHabitId.value = parseInt(habitId); selectedTaskId.value  = null }
  if (status.value === 'active') tick()
  if (typeof Notification !== 'undefined' && Notification.permission === 'default') Notification.requestPermission()
})

// ── Labels ────────────────────────────────────────────────────
const selectedLabel = computed(() => {
  if (selectedTaskId.value)  { const t = props.tasks?.find(t => t.id === selectedTaskId.value);  return t ? `📋 ${t.title}` : null }
  if (selectedHabitId.value) { const h = props.habits?.find(h => h.id === selectedHabitId.value); return h ? `🔁 ${h.title}` : null }
  return null
})

const pendingLabel = computed(() => {
  if (pendingTaskId.value)  return props.tasks?.find(t  => t.id === pendingTaskId.value)?.title  ?? 'another task'
  if (pendingHabitId.value) return props.habits?.find(h => h.id === pendingHabitId.value)?.title ?? 'another habit'
  return 'another item'
})

const focusItems = computed(() => [
  ...(props.tasks  ?? []).filter(t =>  t.is_pinned).map(t => ({ ...t, _type: 'task'  })),
  ...(props.tasks  ?? []).filter(t => !t.is_pinned).map(t => ({ ...t, _type: 'task'  })),
  ...(props.habits ?? []).map(h => ({ ...h, _type: 'habit' })),
])

// ── Select item — prompt if timer running ─────────────────────
const selectItem = (item) => {
  if (status.value === 'active' || status.value === 'paused') {
    pendingTaskId.value    = item._type === 'task'  ? item.id : null
    pendingHabitId.value   = item._type === 'habit' ? item.id : null
    selectorOpen.value     = false
    showSwitchPrompt.value = true
    return
  }
  selectedTaskId.value  = item._type === 'task'  ? item.id : null
  selectedHabitId.value = item._type === 'habit' ? item.id : null
  selectorOpen.value    = false
}

const confirmSwitch = () => {
  stopTimer()
  selectedTaskId.value   = pendingTaskId.value
  selectedHabitId.value  = pendingHabitId.value
  pendingTaskId.value    = null
  pendingHabitId.value   = null
  showSwitchPrompt.value = false
}

const cancelSwitch = () => { pendingTaskId.value = null; pendingHabitId.value = null; showSwitchPrompt.value = false }
const clearSelection = () => { if (status.value !== 'idle') return; selectedTaskId.value = null; selectedHabitId.value = null }

// ── Timer math ────────────────────────────────────────────────
const plannedSeconds = computed(() => {
  if (sessionType.value === 'open') return Infinity
  if (mode.value === 'focus')       return (props.user?.focus_interval      ?? 25) * 60
  if (mode.value === 'long_break')  return (props.user?.long_break_interval  ?? 15) * 60
  return                                   (props.user?.break_interval       ?? 5)  * 60
})

const displayTime = computed(() => {
  const c = sessionType.value === 'open' ? rawElapsed.value : Math.max(0, plannedSeconds.value - rawElapsed.value)
  return `${String(Math.floor(c / 60)).padStart(2,'0')}:${String(c % 60).padStart(2,'0')}`
})

const progress = computed(() =>
  sessionType.value === 'open' ? 0 : Math.min(100, (rawElapsed.value / plannedSeconds.value) * 100)
)

// ── Alarm ─────────────────────────────────────────────────────
const playAlarm = () => {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    const p = (f, s, d) => {
      const o = ctx.createOscillator(), g = ctx.createGain()
      o.connect(g); g.connect(ctx.destination)
      o.type = 'sine'; o.frequency.value = f
      g.gain.setValueAtTime(0.5, ctx.currentTime + s)
      g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + s + d)
      o.start(ctx.currentTime + s); o.stop(ctx.currentTime + s + d)
    }
    p(523.25, 0.0, 0.4); p(659.25, 0.4, 0.4); p(783.99, 0.8, 0.6)
  } catch {}
}

const sendNotification = (title, body) => {
  if (typeof window !== 'undefined' && Notification.permission === 'granted') new Notification(title, { body, icon: '/favicon.ico' })
}

// ── Tick ──────────────────────────────────────────────────────
let timerLoop = null
const tick = () => {
  if (timerLoop) clearInterval(timerLoop)
  const syncStart = Date.now() - rawElapsed.value * 1000
  timerLoop = setInterval(() => {
    if (status.value !== 'active') { clearInterval(timerLoop); return }
    rawElapsed.value = Math.floor((Date.now() - syncStart) / 1000)
    if (sessionType.value === 'pomodoro' && rawElapsed.value >= plannedSeconds.value) finishSession()
  }, 250)
}

// ── Actions ───────────────────────────────────────────────────
const startTimer = () => {
  router.post(route('focus.start'), {
    session_type: sessionType.value, mode: mode.value,
    task_id: selectedTaskId.value, habit_id: selectedHabitId.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      rawElapsed.value = 0; status.value = 'active'; tick()
      sendNotification('Focus started! 🎯', selectedLabel.value ?? 'Stay focused!')
    }
  })
}

const pauseTimer = () => {
  status.value = 'paused'; if (timerLoop) clearInterval(timerLoop)
  if (props.activeSession) router.patch(route('focus.pause', props.activeSession.id), {}, { preserveScroll: true })
}

const resumeTimer = () => {
  status.value = 'active'
  if (props.activeSession) router.patch(route('focus.resume', props.activeSession.id), {}, { preserveScroll: true, onSuccess: tick })
}

const finishSession = () => {
  if (timerLoop) clearInterval(timerLoop)
  if (!props.activeSession) { status.value = 'idle'; rawElapsed.value = 0; triggerReward(); return }
  playAlarm()
  sendNotification('Session done! ⏰', mode.value === 'focus' ? 'Great work! Take a break.' : 'Break over — back to focus!')
  router.patch(route('focus.complete', props.activeSession.id), {}, {
    onSuccess: () => {
      status.value = 'idle'
      rawElapsed.value = 0
      triggerReward()   // ← AI REWARD fires here
    }
  })
}

const stopTimer = () => {
  if (timerLoop) clearInterval(timerLoop)
  if (!props.activeSession) { status.value = 'idle'; rawElapsed.value = 0; return }
  router.patch(route('focus.abandon', props.activeSession.id), {}, {
    preserveScroll: true,
    onSuccess: () => { status.value = 'idle'; rawElapsed.value = 0 }
  })
}

onUnmounted(() => {
  if (timerLoop) clearInterval(timerLoop)
  if (rewardTimer) clearTimeout(rewardTimer)
})
</script>

<template>
  <Head title="Focus Timer" />

  <!-- ══ AI REWARD OVERLAY ══ -->
  <Transition enter-active-class="transition duration-500 ease-out" enter-from-class="opacity-0 scale-95 translate-y-4" enter-to-class="opacity-100 scale-100 translate-y-0">
    <div v-if="showReward"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-full max-w-sm px-4">
      <div class="bg-slate-900 text-white rounded-3xl p-5 shadow-2xl border border-white/10 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-teal-500/10 to-indigo-500/10 pointer-events-none" />
        <button @click="showReward = false" class="absolute top-4 right-4 text-white/40 hover:text-white transition">
          <X class="w-4 h-4" />
        </button>
        <div class="relative z-10">
          <p class="text-[10px] font-black uppercase tracking-widest text-teal-400 mb-3">🎉 Session Complete!</p>
          <div v-if="rewardLoading" class="flex items-center gap-3">
            <div class="w-4 h-4 border-2 border-teal-400 border-t-transparent rounded-full animate-spin shrink-0" />
            <span class="text-sm text-white/60">Getting your reward...</span>
          </div>
          <div v-else class="flex items-start gap-3">
            <span class="text-2xl shrink-0">{{ rewardMsg?.emoji ?? '⭐' }}</span>
            <p class="text-sm font-bold leading-snug">{{ rewardMsg?.msg }}</p>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ══ SWITCH PROMPT ══ -->
  <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
    <div v-if="showSwitchPrompt" class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-[#242526] rounded-3xl p-7 max-w-sm w-full shadow-2xl border border-slate-200 dark:border-slate-700">
        <div class="text-4xl text-center mb-4">⚠️</div>
        <h2 class="text-xl font-black text-slate-800 dark:text-white text-center mb-2">Timer is running!</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 text-center leading-relaxed mb-6">
          You are on <strong class="text-slate-700 dark:text-white">"{{ selectedLabel ?? 'your session' }}"</strong>.
          Stop it and switch to <strong class="text-slate-700 dark:text-white">"{{ pendingLabel }}"</strong>?
        </p>
        <div class="flex gap-3">
          <button @click="confirmSwitch" class="flex-1 py-3.5 bg-red-500 hover:bg-red-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest transition-all active:scale-95">Stop &amp; Switch</button>
          <button @click="cancelSwitch"  class="flex-1 py-3.5 bg-teal-500 hover:bg-teal-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest transition-all active:scale-95">Keep Going</button>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ══ MAIN PAGE ══ -->
  <div class="flex flex-col items-center justify-center p-4 max-w-lg mx-auto w-full pt-8 pb-28 md:pb-12">
    <div class="absolute w-96 h-96 rounded-full blur-[120px] -z-10 pointer-events-none transition-colors duration-1000"
      :class="mode === 'focus' ? 'bg-teal-400/15' : 'bg-purple-500/15'" />

    <!-- Status -->
    <div class="flex items-center gap-2 mb-4">
      <span class="w-2 h-2 rounded-full" :class="status === 'active' ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'" />
      <span class="text-xs font-black uppercase tracking-widest text-slate-400">
        {{ status === 'idle' ? 'Ready to focus' : (mode === 'focus' ? '🎯 Focus Mode' : '☕ Break Time') }}
      </span>
    </div>

    <!-- TIMER -->
    <h1 class="text-[7rem] sm:text-[9rem] font-black tracking-tighter tabular-nums select-none leading-none mb-3 transition-colors"
      :class="mode === 'focus' ? 'text-slate-800 dark:text-white' : 'text-purple-500'">
      {{ displayTime }}
    </h1>

    <div v-if="sessionType === 'pomodoro'" class="w-full max-w-xs h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full mb-6 overflow-hidden">
      <div class="h-full bg-teal-500 rounded-full transition-all duration-1000" :style="`width:${progress}%`" />
    </div>
    <div v-else class="mb-6 text-xs text-slate-400 font-bold uppercase tracking-widest">Open timer — no time limit</div>

    <!-- PANEL -->
    <div class="w-full bg-white/50 dark:bg-[#18191A]/70 backdrop-blur-3xl rounded-[2rem] p-5 border border-white/40 dark:border-white/5 shadow-2xl space-y-4">

      <!-- Mode toggle -->
      <div class="flex gap-2 p-1.5 bg-slate-100 dark:bg-black/40 rounded-2xl text-sm">
        <button @click="sessionType='pomodoro'" :disabled="status!=='idle'"
          :class="sessionType==='pomodoro' ? 'bg-white dark:bg-slate-800 shadow text-slate-900 dark:text-white' : 'text-slate-500'"
          class="flex-1 py-2.5 rounded-xl font-black transition-all flex items-center justify-center gap-1.5 disabled:opacity-50">
          <Timer class="w-4 h-4" /> Pomodoro
        </button>
        <button @click="sessionType='open'" :disabled="status!=='idle'"
          :class="sessionType==='open' ? 'bg-white dark:bg-slate-800 shadow text-slate-900 dark:text-white' : 'text-slate-500'"
          class="flex-1 py-2.5 rounded-xl font-black transition-all flex items-center justify-center gap-1.5 disabled:opacity-50">
          <Coffee class="w-4 h-4" /> Free Timer
        </button>
      </div>

      <!-- TASK SELECTOR -->
      <div class="relative">
        <!-- Locked when running -->
        <div v-if="status === 'active' || status === 'paused'"
          class="w-full flex items-center justify-between p-4 bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 rounded-2xl opacity-80 cursor-not-allowed">
          <span class="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300 truncate">
            <Target class="w-4 h-4 text-teal-500 shrink-0" />
            <span class="truncate">{{ selectedLabel ?? 'No item selected' }}</span>
          </span>
          <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest shrink-0 ml-2">🔒 Running</span>
        </div>
        <!-- Open when idle -->
        <button v-else @click="selectorOpen = !selectorOpen"
          class="w-full flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl font-bold text-sm text-left hover:border-teal-400 transition">
          <span class="flex items-center gap-2 truncate">
            <Target class="w-4 h-4 text-teal-500 shrink-0" />
            <span :class="selectedLabel ? 'text-slate-800 dark:text-white' : 'text-slate-400'" class="truncate">{{ selectedLabel ?? 'Choose what to focus on...' }}</span>
          </span>
          <div class="flex items-center gap-2 shrink-0">
            <button v-if="selectedTaskId || selectedHabitId" @click.stop="clearSelection" class="text-slate-400 hover:text-red-500 text-lg">&times;</button>
            <ChevronDown class="w-4 h-4 text-slate-400 transition" :class="selectorOpen ? 'rotate-180' : ''" />
          </div>
        </button>
        <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
          <div v-if="selectorOpen" class="absolute top-full mt-2 left-0 right-0 bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 rounded-2xl shadow-2xl z-50 max-h-64 overflow-y-auto">
            <p class="px-4 pt-4 pb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">📋 Tasks</p>
            <div v-for="item in focusItems.filter(i=>i._type==='task')" :key="'t'+item.id"
              @click="selectItem(item)" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition" :class="selectedTaskId===item.id?'bg-teal-50 dark:bg-teal-500/10':''">
              <span v-if="item.is_pinned" class="shrink-0">📌</span>
              <span class="font-medium text-sm text-slate-700 dark:text-slate-200 truncate">{{ item.title }}</span>
              <Check v-if="selectedTaskId===item.id" class="w-4 h-4 text-teal-500 ml-auto shrink-0" />
            </div>
            <p v-if="!focusItems.filter(i=>i._type==='task').length" class="px-4 py-3 text-sm text-slate-400 italic">No tasks</p>
            <div class="border-t border-slate-100 dark:border-slate-800" />
            <p class="px-4 pt-3 pb-2 text-[10px] font-black uppercase tracking-widest text-slate-400">🔁 Habits</p>
            <div v-for="item in focusItems.filter(i=>i._type==='habit')" :key="'h'+item.id"
              @click="selectItem(item)" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition" :class="selectedHabitId===item.id?'bg-indigo-50 dark:bg-indigo-500/10':''">
              <span class="font-medium text-sm text-slate-700 dark:text-slate-200 truncate">{{ item.title }}</span>
              <Check v-if="selectedHabitId===item.id" class="w-4 h-4 text-indigo-500 ml-auto shrink-0" />
            </div>
            <p v-if="!focusItems.filter(i=>i._type==='habit').length" class="px-4 py-3 text-sm text-slate-400 italic">No habits</p>
            <div class="h-2" />
          </div>
        </Transition>
      </div>

      <!-- AMBIENT SOUNDS (overlapping, per-sound volume, file import) -->
      <FocusAmbientSounds />

      <!-- CONTROLS -->
      <div class="flex gap-3">
        <button v-if="status==='idle'"   @click="startTimer"  class="flex-1 py-5 bg-teal-500 text-white rounded-[1.5rem] font-black text-lg uppercase tracking-widest shadow-xl shadow-teal-500/30 hover:bg-teal-600 active:scale-95 transition-all flex items-center justify-center gap-2"><Play class="w-5 h-5 fill-current" /> Start</button>
        <button v-if="status==='active'" @click="pauseTimer"  class="flex-1 py-5 bg-amber-400 text-white rounded-[1.5rem] font-black text-lg uppercase tracking-widest shadow-lg hover:bg-amber-500 active:scale-95 transition-all flex items-center justify-center gap-2"><Pause class="w-5 h-5 fill-current" /> Pause</button>
        <button v-if="status==='paused'" @click="resumeTimer" class="flex-1 py-5 bg-teal-500 text-white rounded-[1.5rem] font-black text-lg uppercase tracking-widest shadow-lg hover:bg-teal-600 active:scale-95 transition-all flex items-center justify-center gap-2"><Play class="w-5 h-5 fill-current" /> Resume</button>
        <button v-if="status!=='idle'" @click="finishSession" class="py-5 px-5 bg-slate-900 dark:bg-black text-white rounded-[1.5rem] font-black text-sm uppercase tracking-widest hover:bg-slate-800 active:scale-95 transition-all">Done ✓</button>
      </div>
      <button v-if="status!=='idle'" @click="stopTimer" class="w-full py-2.5 text-slate-400 hover:text-red-500 font-bold text-xs uppercase tracking-widest transition flex items-center justify-center gap-2 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-2xl">
        <Square class="w-3.5 h-3.5" /> Stop Timer
      </button>
    </div>

    <p class="mt-5 text-xs font-bold text-slate-400 uppercase tracking-widest">
      {{ todayStats?.next_count ?? 0 }} sessions completed today
    </p>
  </div>
</template>