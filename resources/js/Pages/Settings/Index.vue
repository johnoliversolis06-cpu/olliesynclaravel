<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import { Bell, Moon, Sun, Timer, Scissors, User, Save, Check } from 'lucide-vue-next'

const props = defineProps({
  user:       Object,
  notifPrefs: Object,  // User's notification preferences from DB
})

// ── Theme Form ───────────────────────────────────────────────
const isDark = ref(props.user?.theme === 'dark')

const toggleTheme = () => {
  isDark.value = !isDark.value
  router.patch(route('settings.theme'), { theme: isDark.value ? 'dark' : 'light' }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (isDark.value) document.documentElement.classList.add('dark')
      else document.documentElement.classList.remove('dark')
      localStorage.setItem('olliesync-theme', isDark.value ? 'dark' : 'light')
    }
  })
}

// ── Focus Timer Settings ─────────────────────────────────────
const timerForm = useForm({
  focus_interval:              props.user?.focus_interval              ?? 25,
  break_interval:              props.user?.break_interval              ?? 5,
  long_break_interval:         props.user?.long_break_interval         ?? 15,
  pomodoros_before_long_break: props.user?.pomodoros_before_long_break ?? 4,
  auto_cutoff_duration:        props.user?.auto_cutoff_duration        ?? 30,
})

const saveTimer = () => {
  timerForm.patch(route('settings.timer'), { preserveScroll: true })
}

// ── Notification Preferences ─────────────────────────────────
const notifForm = useForm({
  notify_task_due:         props.notifPrefs?.notify_task_due         ?? true,
  notify_habit_reminder:   props.notifPrefs?.notify_habit_reminder   ?? true,
  notify_focus_complete:   props.notifPrefs?.notify_focus_complete   ?? true,
  notify_daily_summary:    props.notifPrefs?.notify_daily_summary    ?? false,
  notify_streak_warning:   props.notifPrefs?.notify_streak_warning   ?? true,
  habit_reminder_time:     props.notifPrefs?.habit_reminder_time     ?? '08:00',
  task_reminder_time:      props.notifPrefs?.task_reminder_time      ?? '07:30',
  daily_summary_time:      props.notifPrefs?.daily_summary_time      ?? '21:00',
})

const saveNotifications = () => {
  notifForm.patch(route('settings.notifications'), {
    preserveScroll: true,
    onSuccess: () => requestBrowserNotifications()
  })
}

// ── Request browser notification permission ──────────────────
const notifPermission = ref('default')

onMounted(() => {
  if (typeof window !== 'undefined' && 'Notification' in window) {
    notifPermission.value = Notification.permission
  }
})

const requestBrowserNotifications = async () => {
  if (typeof window === 'undefined' || !('Notification' in window)) return
  if (Notification.permission === 'granted') {
    notifPermission.value = 'granted'
    return
  }
  const perm = await Notification.requestPermission()
  notifPermission.value = perm
  if (perm === 'granted') {
    new Notification('Notifications enabled! 🔔', {
      body: "OllieSync will remind you about your tasks, habits, and streaks.",
    })
  }
}

// ── Profile ──────────────────────────────────────────────────
const profileForm = useForm({
  name:  props.user?.name  ?? '',
  email: props.user?.email ?? '',
})

const saveProfile = () => {
  profileForm.patch(route('profile.update'), { preserveScroll: true })
}

// ── Success Flash ─────────────────────────────────────────────
const saved = ref(false)
const showSaved = () => { saved.value = true; setTimeout(() => saved.value = false, 2500) }
</script>

<template>
  <Head title="Settings" />
  <div class="max-w-3xl mx-auto space-y-8 pb-16">

    <h1 class="text-4xl font-black text-slate-800 dark:text-white tracking-tight">Settings</h1>

    <!-- ═══════════════ APPEARANCE ═══════════════ -->
    <section class="bg-white dark:bg-[#242526] rounded-3xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-500/20 rounded-xl flex items-center justify-center">
          <Moon class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
        </div>
        <h2 class="text-lg font-black text-slate-800 dark:text-white">Appearance</h2>
      </div>

      <div class="flex items-center justify-between">
        <div>
          <p class="font-bold text-slate-700 dark:text-white">Dark Mode</p>
          <p class="text-sm text-slate-400 mt-0.5">Switch between light and dark theme</p>
        </div>
        <!-- Toggle Switch -->
        <button @click="toggleTheme"
          class="relative w-14 h-7 rounded-full transition-colors duration-200 focus:outline-none"
          :class="isDark ? 'bg-indigo-600' : 'bg-slate-200'">
          <span class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow transition-transform duration-200"
            :class="isDark ? 'translate-x-7' : 'translate-x-0'" />
          <Sun  v-if="!isDark" class="absolute right-1.5 top-1.5 w-3.5 h-3.5 text-yellow-500" />
          <Moon v-if="isDark"  class="absolute left-1.5 top-1.5 w-3.5 h-3.5 text-white" />
        </button>
      </div>
    </section>

    <!-- ═══════════════ FOCUS TIMER ═══════════════ -->
    <section class="bg-white dark:bg-[#242526] rounded-3xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-teal-100 dark:bg-teal-500/20 rounded-xl flex items-center justify-center">
          <Timer class="w-5 h-5 text-teal-600 dark:text-teal-400" />
        </div>
        <h2 class="text-lg font-black text-slate-800 dark:text-white">Focus Timer</h2>
      </div>

      <form @submit.prevent="saveTimer" class="space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Focus Time (minutes)</label>
            <input v-model.number="timerForm.focus_interval" type="number" min="1" max="120"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Short Break (minutes)</label>
            <input v-model.number="timerForm.break_interval" type="number" min="1" max="60"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Long Break (minutes)</label>
            <input v-model.number="timerForm.long_break_interval" type="number" min="1" max="120"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
          <div>
            <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Rounds Before Long Break</label>
            <input v-model.number="timerForm.pomodoros_before_long_break" type="number" min="1" max="10"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
        </div>

        <!-- Auto Cutoff -->
        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-3 mb-2">
            <Scissors class="w-4 h-4 text-slate-400" />
            <label class="text-xs font-black uppercase tracking-widest text-slate-400">Auto Stop Time (minutes)</label>
          </div>
          <p class="text-sm text-slate-400 mb-3">In free timer mode, automatically stop after this many minutes and save the time to your stats.</p>
          <input v-model.number="timerForm.auto_cutoff_duration" type="number" min="5" max="240"
            class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
        </div>

        <button type="submit" :disabled="timerForm.processing"
          class="flex items-center gap-2 px-6 py-3 bg-teal-500 text-white rounded-2xl font-black text-sm shadow-md hover:bg-teal-600 active:scale-95 transition-all">
          <Save class="w-4 h-4" /> Save Timer Settings
        </button>
      </form>
    </section>

    <!-- ═══════════════ NOTIFICATIONS / ALARMS ═══════════════ -->
    <section class="bg-white dark:bg-[#242526] rounded-3xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 bg-amber-100 dark:bg-amber-500/20 rounded-xl flex items-center justify-center">
          <Bell class="w-5 h-5 text-amber-600 dark:text-amber-400" />
        </div>
        <h2 class="text-lg font-black text-slate-800 dark:text-white">Reminders & Alarms</h2>
      </div>
      <p class="text-sm text-slate-400 mb-6">Get reminded about your tasks, habits, and streaks right in your browser.</p>

      <!-- Browser Permission Banner -->
      <div v-if="notifPermission !== 'granted'"
        class="flex items-center justify-between bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-2xl p-4 mb-6">
        <div>
          <p class="font-bold text-amber-700 dark:text-amber-400 text-sm">Browser notifications are off</p>
          <p class="text-xs text-amber-600 dark:text-amber-400/70 mt-0.5">Enable them to receive alarms and reminders.</p>
        </div>
        <button @click="requestBrowserNotifications"
          class="px-4 py-2 bg-amber-500 text-white rounded-xl font-bold text-sm hover:bg-amber-600 transition">
          Turn On
        </button>
      </div>
      <div v-else class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-2xl p-4 mb-6">
        <Check class="w-4 h-4 text-emerald-600" />
        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">Browser notifications are enabled ✓</span>
      </div>

      <form @submit.prevent="saveNotifications" class="space-y-5">
        <!-- Toggles -->
        <div class="space-y-4">
          <div v-for="[key, label, desc] in [
            ['notify_task_due',       '📋 Task Reminders',     'Remind me about tasks due today'],
            ['notify_habit_reminder', '🔁 Habit Reminders',    'Daily reminder to complete my habits'],
            ['notify_focus_complete', '⏰ Focus Alarm',         'Alarm when a focus session finishes'],
            ['notify_streak_warning', '🔥 Streak Warnings',    'Alert me if a streak is about to break'],
            ['notify_daily_summary',  '📊 Daily Summary',      'End-of-day recap of what I did'],
          ]" :key="key"
            class="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-800 last:border-0">
            <div>
              <p class="font-bold text-slate-700 dark:text-white text-sm">{{ label }}</p>
              <p class="text-xs text-slate-400 mt-0.5">{{ desc }}</p>
            </div>
            <button type="button" @click="notifForm[key] = !notifForm[key]"
              class="relative w-12 h-6 rounded-full transition-colors duration-200"
              :class="notifForm[key] ? 'bg-teal-500' : 'bg-slate-200 dark:bg-slate-700'">
              <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"
                :class="notifForm[key] ? 'translate-x-6' : 'translate-x-0'" />
            </button>
          </div>
        </div>

        <!-- Reminder Times -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
          <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Habit Reminder At</label>
            <input v-model="notifForm.habit_reminder_time" type="time"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
          <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Task Reminder At</label>
            <input v-model="notifForm.task_reminder_time" type="time"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
          <div>
            <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-2">Daily Summary At</label>
            <input v-model="notifForm.daily_summary_time" type="time"
              class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold text-slate-800 dark:text-white" />
          </div>
        </div>

        <button type="submit" :disabled="notifForm.processing"
          class="flex items-center gap-2 px-6 py-3 bg-amber-500 text-white rounded-2xl font-black text-sm shadow-md hover:bg-amber-600 active:scale-95 transition-all">
          <Save class="w-4 h-4" /> Save Notification Settings
        </button>
      </form>
    </section>

    <!-- ═══════════════ PROFILE ═══════════════ -->
    <section class="bg-white dark:bg-[#242526] rounded-3xl p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-500/20 rounded-xl flex items-center justify-center">
          <User class="w-5 h-5 text-purple-600 dark:text-purple-400" />
        </div>
        <h2 class="text-lg font-black text-slate-800 dark:text-white">My Profile</h2>
      </div>

      <form @submit.prevent="saveProfile" class="space-y-4">
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Your Name</label>
          <input v-model="profileForm.name" type="text"
            class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-purple-400 font-bold text-slate-800 dark:text-white" />
        </div>
        <div>
          <label class="block text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Email</label>
          <input v-model="profileForm.email" type="email"
            class="w-full p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-purple-400 font-bold text-slate-800 dark:text-white" />
        </div>
        <button type="submit" :disabled="profileForm.processing"
          class="flex items-center gap-2 px-6 py-3 bg-purple-600 text-white rounded-2xl font-black text-sm shadow-md hover:bg-purple-700 active:scale-95 transition-all">
          <Save class="w-4 h-4" /> Update Profile
        </button>
      </form>
    </section>

  </div>
</template>