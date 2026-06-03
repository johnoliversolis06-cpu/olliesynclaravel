<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  Flame, Check, Trash2, Plus, Search, Bell, BellOff, X, Sparkles
} from 'lucide-vue-next'

const props = defineProps({ habits: Array })

// ─────────────────────────────────────────────────────────────
// REMINDER SYSTEM STATE
// ─────────────────────────────────────────────────────────────
const openReminderFor = ref(null)   // habit.id whose popup is open, or null
const reminderTime    = ref('08:00') // the time input value

const openReminder = (habit) => {
  // Toggle: if already open for this habit, close it
  if (openReminderFor.value === habit.id) {
    openReminderFor.value = null
    return
  }
  openReminderFor.value = habit.id
  // Pre-fill with existing reminder time (strip seconds if present)
  reminderTime.value = habit.reminder_time
    ? habit.reminder_time.substring(0, 5)  // "08:00:00" → "08:00"
    : '08:00'
}

const saveReminder = (habitId) => {
  router.patch(route('habits.setReminder', habitId), {
    reminder_time: reminderTime.value
  }, {
    preserveScroll: true,
    onSuccess: () => { openReminderFor.value = null }
  })
}

const removeReminder = (habitId) => {
  router.delete(route('habits.removeReminder', habitId), {
    preserveScroll: true,
    onSuccess: () => { openReminderFor.value = null }
  })
}

// Close reminder popup when clicking anywhere outside
const handleGlobalClick = () => { openReminderFor.value = null }
onMounted(()  => document.addEventListener('click', handleGlobalClick))
onUnmounted(() => document.removeEventListener('click', handleGlobalClick))

// ─────────────────────────────────────────────────────────────
// MARK DONE / DELETE
// ─────────────────────────────────────────────────────────────
const markDone = (id) => router.patch(route('habits.complete', id), {}, { preserveScroll: true })

const deleteHabit = (id) => {
  if (confirm('Delete this habit for good?')) {
    router.delete(route('habits.destroy', id), { preserveScroll: true })
  }
}

// ─────────────────────────────────────────────────────────────
// SEARCH + FILTER
// ─────────────────────────────────────────────────────────────
const search     = ref('')
const filterType = ref('all')   // 'all' | 'positive' | 'negative'

const filtered = computed(() => {
  let list = props.habits ?? []
  if (search.value)              list = list.filter(h => h.title.toLowerCase().includes(search.value.toLowerCase()))
  if (filterType.value !== 'all') list = list.filter(h => h.habit_type === filterType.value)
  return list
})

// ─────────────────────────────────────────────────────────────
// ADD HABIT FORM
// ─────────────────────────────────────────────────────────────
const showAddForm = ref(false)

const form = useForm({
  title:       '',
  description: '',
  category:    'health',
  frequency:   'daily',
  habit_type:  'positive',   // 'positive' (good) | 'negative' (bad to break)
  difficulty:  'easy',
  color:       '#0CAF89',
})

const submitHabit = () => {
  form.post(route('habits.store'), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); showAddForm.value = false }
  })
}

// ─────────────────────────────────────────────────────────────
// SUGGESTED HABITS (constants - common ones)
// ─────────────────────────────────────────────────────────────
const SUGGESTED = [
  { title: 'Morning Exercise',          habit_type: 'positive', category: 'health',    difficulty: 'medium', defaultTime: '06:30' },
  { title: 'Drink 8 Glasses of Water',  habit_type: 'positive', category: 'health',    difficulty: 'easy',   defaultTime: '08:00' },
  { title: 'Read for 20 Minutes',       habit_type: 'positive', category: 'learning',  difficulty: 'easy',   defaultTime: '21:00' },
  { title: 'Meditate 10 Minutes',       habit_type: 'positive', category: 'mental',    difficulty: 'easy',   defaultTime: '07:00' },
  { title: 'Write in My Journal',       habit_type: 'positive', category: 'mental',    difficulty: 'easy',   defaultTime: '22:00' },
  { title: 'Sleep Before Midnight',     habit_type: 'positive', category: 'health',    difficulty: 'medium', defaultTime: '23:00' },
  { title: 'Daily Walk (30 min)',        habit_type: 'positive', category: 'health',    difficulty: 'easy',   defaultTime: '17:00' },
  { title: 'Cold Shower',               habit_type: 'positive', category: 'health',    difficulty: 'hard',   defaultTime: '07:00' },
  { title: 'No Social Media Before Noon', habit_type: 'negative', category: 'digital', difficulty: 'hard',   defaultTime: '08:00' },
  { title: 'No Junk Food',              habit_type: 'negative', category: 'health',    difficulty: 'hard',   defaultTime: '12:00' },
  { title: 'No Late Night Screen Time', habit_type: 'negative', category: 'digital',   difficulty: 'medium', defaultTime: '22:00' },
  { title: 'Gratitude Journal',         habit_type: 'positive', category: 'mental',    difficulty: 'easy',   defaultTime: '21:30' },
]

const fillFromSuggestion = (s) => {
  form.title      = s.title
  form.habit_type = s.habit_type
  form.category   = s.category
  form.difficulty = s.difficulty
  showAddForm.value = true
}

// ─────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────
const formatTime = (t) => {
  if (!t) return null
  const [h, m] = t.substring(0, 5).split(':')
  const hour = parseInt(h)
  return `${hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour)}:${m} ${hour >= 12 ? 'PM' : 'AM'}`
}

const diffColor = { easy: 'text-emerald-500', medium: 'text-amber-500', hard: 'text-red-500' }
</script>

<template>
  <Head title="My Habits" />
  <div class="max-w-7xl mx-auto space-y-8 pb-16">

    <!-- ═══════════════════ HERO HEADER ═══════════════════ -->
    <div class="bg-gradient-to-tr from-violet-700 via-indigo-700 to-slate-900 rounded-3xl p-10 md:p-14 shadow-2xl relative overflow-hidden border border-white/10">
      <Sparkles class="absolute top-8 right-8 text-white/10 w-20 h-20 pointer-events-none" />
      <h1 class="text-4xl md:text-5xl font-black text-white tracking-tight">
        <span class="opacity-50">Build</span> Better Habits
      </h1>
      <p class="mt-3 text-indigo-200 text-lg font-medium max-w-xl leading-relaxed">
        Track the habits you want to keep — and the bad ones you want to quit.
        Set daily reminders so you never forget. 🔔
      </p>

      <!-- Quick stats -->
      <div class="mt-8 flex flex-wrap gap-4">
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-5 py-3 text-white">
          <span class="text-2xl font-black">{{ habits?.filter(h => h.habit_type === 'positive').length ?? 0 }}</span>
          <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mt-0.5">Good Habits</p>
        </div>
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-5 py-3 text-white">
          <span class="text-2xl font-black">{{ habits?.filter(h => h.habit_type === 'negative').length ?? 0 }}</span>
          <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mt-0.5">Bad to Break</p>
        </div>
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-5 py-3 text-white">
          <span class="text-2xl font-black">{{ habits?.filter(h => h.completed_today).length ?? 0 }}</span>
          <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mt-0.5">Done Today</p>
        </div>
        <div class="bg-white/10 backdrop-blur-sm rounded-2xl px-5 py-3 text-white">
          <span class="text-2xl font-black">{{ habits?.filter(h => h.reminder_time).length ?? 0 }}</span>
          <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mt-0.5">🔔 With Alarms</p>
        </div>
      </div>
    </div>

    <!-- ═══════════════════ CONTROLS ROW ═══════════════════ -->
    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">

      <!-- Search -->
      <div class="relative w-full sm:w-72">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <input v-model="search" type="text" placeholder="Search habits..."
          class="w-full pl-10 pr-4 py-3 rounded-2xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 outline-none focus:border-indigo-400 font-medium text-sm text-slate-700 dark:text-white transition shadow-sm" />
      </div>

      <!-- Filter tabs: All / Good / Bad -->
      <div class="flex gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl text-sm">
        <button v-for="[key, label] in [['all','All'],['positive','✅ Good'],['negative','🚫 Bad']]"
          :key="key" @click="filterType = key"
          :class="filterType === key
            ? 'bg-white dark:bg-slate-700 shadow text-slate-900 dark:text-white'
            : 'text-slate-500 hover:text-slate-700 dark:hover:text-white'"
          class="px-4 py-2 rounded-xl font-bold transition-all">
          {{ label }}
        </button>
      </div>

      <!-- Add Habit button -->
      <button @click="showAddForm = !showAddForm"
        class="flex items-center gap-2 px-5 py-3 bg-indigo-600 text-white rounded-2xl font-bold shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 active:scale-95 transition-all shrink-0 text-sm">
        <Plus class="w-4 h-4" /> Add Habit
      </button>
    </div>

    <!-- ═══════════════════ ADD HABIT FORM ═══════════════════ -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-4"
    >
      <div v-if="showAddForm"
        class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 rounded-3xl p-8 shadow-xl">

        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-black text-slate-800 dark:text-white">Create a New Habit</h2>
          <button @click="showAddForm = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitHabit" class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Habit name -->
          <div class="sm:col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Habit Name *</label>
            <input v-model="form.title" type="text" required placeholder="e.g. Morning Run, No Social Media..."
              class="w-full p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold outline-none focus:border-indigo-500 transition text-slate-800 dark:text-white" />
          </div>

          <!-- Description -->
          <div class="sm:col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Why does this matter? (optional)</label>
            <input v-model="form.description" type="text" placeholder="Your reason / motivation..."
              class="w-full p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-medium outline-none focus:border-indigo-500 transition text-slate-700 dark:text-slate-200" />
          </div>

          <!-- GOOD vs BAD HABIT TOGGLE -->
          <div class="sm:col-span-2">
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">Is this a Good or Bad Habit?</label>
            <div class="flex gap-3">
              <button type="button" @click="form.habit_type = 'positive'"
                :class="form.habit_type === 'positive'
                  ? 'bg-emerald-500 text-white ring-2 ring-emerald-300 dark:ring-emerald-700'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200'"
                class="flex-1 py-4 rounded-2xl font-black text-sm transition-all">
                ✅ Good Habit (build it)
              </button>
              <button type="button" @click="form.habit_type = 'negative'"
                :class="form.habit_type === 'negative'
                  ? 'bg-red-500 text-white ring-2 ring-red-300 dark:ring-red-700'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200'"
                class="flex-1 py-4 rounded-2xl font-black text-sm transition-all">
                🚫 Bad Habit (break it)
              </button>
            </div>
          </div>

          <!-- Difficulty -->
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">How Hard Is It?</label>
            <select v-model="form.difficulty"
              class="w-full p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold outline-none text-slate-700 dark:text-slate-200">
              <option value="easy">Easy 🟢</option>
              <option value="medium">Medium 🟡</option>
              <option value="hard">Hard 🔴</option>
            </select>
          </div>

          <!-- Category -->
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Category</label>
            <select v-model="form.category"
              class="w-full p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold outline-none text-slate-700 dark:text-slate-200">
              <option value="health">🏃 Health</option>
              <option value="mental">🧠 Mental</option>
              <option value="learning">📚 Learning</option>
              <option value="digital">📵 Digital</option>
              <option value="social">👥 Social</option>
              <option value="finance">💰 Finance</option>
              <option value="other">✨ Other</option>
            </select>
          </div>

          <!-- Color -->
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Color</label>
            <input v-model="form.color" type="color"
              class="w-full h-14 p-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl cursor-pointer" />
          </div>

          <!-- Frequency -->
          <div>
            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">How Often?</label>
            <select v-model="form.frequency"
              class="w-full p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold outline-none text-slate-700 dark:text-slate-200">
              <option value="daily">Every Day</option>
              <option value="weekly">Once a Week</option>
              <option value="monthly">Once a Month</option>
            </select>
          </div>

          <!-- Buttons -->
          <div class="sm:col-span-2 flex gap-3">
            <button type="submit" :disabled="form.processing"
              class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg hover:bg-indigo-700 active:scale-95 transition-all flex items-center justify-center gap-2">
              <Plus class="w-4 h-4" /> Save Habit
            </button>
            <button type="button" @click="showAddForm = false"
              class="px-6 py-4 border border-slate-200 dark:border-slate-700 text-slate-500 rounded-2xl font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all text-sm">
              Cancel
            </button>
          </div>
        </form>

        <!-- Suggested Habits -->
        <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
          <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-4">
            💡 Common Habits — Click to Use
          </p>
          <div class="flex flex-wrap gap-2">
            <button v-for="s in SUGGESTED" :key="s.title"
              @click="fillFromSuggestion(s)"
              class="px-3 py-1.5 rounded-full text-xs font-bold border transition-all"
              :class="s.habit_type === 'negative'
                ? 'border-red-200 text-red-600 dark:border-red-500/30 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10'
                : 'border-emerald-200 text-emerald-700 dark:border-emerald-500/30 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10'">
              {{ s.title }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ═══════════════════ HABITS GRID ═══════════════════ -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 auto-rows-fr">
      <TransitionGroup
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="opacity-0 scale-95 translate-y-6"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        appear
      >
        <div v-for="habit in filtered" :key="habit.id"
          class="bg-white dark:bg-[#242526] rounded-3xl relative overflow-hidden group shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 flex flex-col border border-l-4"
          :class="[
            habit.completed_today
              ? 'border-indigo-200 dark:border-indigo-800'
              : 'border-slate-200 dark:border-slate-800',
            habit.habit_type === 'negative'
              ? 'border-l-red-400'
              : 'border-l-emerald-400'
          ]"
        >
          <!-- ── Card Top: Type badge + Bell + Delete ── -->
          <div class="flex items-start justify-between p-6 pb-3">
            <!-- Habit type badge -->
            <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-full"
              :class="habit.habit_type === 'negative'
                ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400'
                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'">
              {{ habit.habit_type === 'negative' ? '🚫 Break It' : '✅ Build It' }}
            </span>

            <!-- Right actions: Bell + Delete -->
            <div class="flex items-center gap-1" @click.stop>

              <!-- 🔔 REMINDER BELL — the main feature -->
              <div class="relative">
                <button @click.stop="openReminder(habit)"
                  :title="habit.reminder_time ? `Alarm set: ${formatTime(habit.reminder_time)}` : 'Set a daily reminder'"
                  class="p-2 rounded-xl transition-all"
                  :class="habit.reminder_time
                    ? 'text-amber-500 bg-amber-100 dark:bg-amber-500/15'
                    : 'text-slate-300 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-500/10'">
                  <Bell class="w-4 h-4" />
                </button>

                <!-- ── REMINDER POPUP ── -->
                <Transition
                  enter-active-class="transition duration-150 ease-out"
                  enter-from-class="opacity-0 scale-95 translate-y-1"
                  enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                  <div v-if="openReminderFor === habit.id"
                    class="absolute top-full right-0 mt-2 z-50 bg-white dark:bg-[#1c1d1e] border border-slate-200 dark:border-slate-700 shadow-2xl rounded-2xl p-4 w-56"
                    @click.stop
                  >
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                      <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                        🔔 Daily Reminder
                      </p>
                      <button @click.stop="openReminderFor = null" class="text-slate-300 hover:text-slate-600 dark:hover:text-white transition">
                        <X class="w-3.5 h-3.5" />
                      </button>
                    </div>

                    <!-- Current alarm display -->
                    <div v-if="habit.reminder_time" class="mb-3 bg-amber-50 dark:bg-amber-500/10 rounded-xl p-2.5 flex items-center gap-2">
                      <Bell class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                      <span class="text-xs font-black text-amber-700 dark:text-amber-400">
                        Currently: {{ formatTime(habit.reminder_time) }}
                      </span>
                    </div>

                    <!-- Time picker -->
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">
                      Set alarm at:
                    </label>
                    <input v-model="reminderTime" type="time"
                      class="w-full p-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl font-bold text-slate-800 dark:text-white outline-none focus:border-amber-400 transition text-sm mb-3" />

                    <!-- What the reminder will say -->
                    <p class="text-[10px] text-slate-400 mb-3 leading-relaxed">
                      You'll get a browser notification at this time every day reminding you to
                      <strong>"{{ habit.title }}"</strong>
                    </p>

                    <!-- Buttons -->
                    <div class="flex gap-2">
                      <button @click.stop="saveReminder(habit.id)"
                        class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-xs uppercase tracking-widest transition active:scale-95">
                        Set Alarm 🔔
                      </button>
                      <button v-if="habit.reminder_time"
                        @click.stop="removeReminder(habit.id)"
                        class="py-2.5 px-3 bg-red-50 hover:bg-red-100 text-red-500 dark:bg-red-500/10 dark:hover:bg-red-500/20 rounded-xl font-bold text-xs transition active:scale-95"
                        title="Remove reminder">
                        <BellOff class="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                </Transition>
              </div>

              <!-- Delete button -->
              <button @click="deleteHabit(habit.id)"
                class="p-2 rounded-xl opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- ── Card Body ── -->
          <div class="px-6 flex-1 flex flex-col">
            <!-- Title -->
            <h2 class="text-xl font-bold text-slate-800 dark:text-white leading-snug mb-2 pr-2"
              :class="{ 'line-through opacity-50': habit.completed_today }">
              {{ habit.title }}
            </h2>

            <!-- Description -->
            <p v-if="habit.description" class="text-sm text-slate-400 dark:text-slate-500 mb-3 line-clamp-2">
              {{ habit.description }}
            </p>

            <!-- Reminder tag (shows when set) -->
            <div v-if="habit.reminder_time" class="flex items-center gap-1.5 mb-3">
              <Bell class="w-3 h-3 text-amber-500" />
              <span class="text-xs font-bold text-amber-600 dark:text-amber-400">
                Reminder at {{ formatTime(habit.reminder_time) }}
              </span>
            </div>

            <!-- Meta: frequency + difficulty -->
            <div class="flex items-center gap-3 mb-4 text-[11px] font-black uppercase tracking-widest">
              <span class="text-slate-400">{{ habit.frequency === 'daily' ? 'Every day' : habit.frequency }}</span>
              <span class="text-slate-300 dark:text-slate-700">·</span>
              <span :class="diffColor[habit.difficulty]">{{ habit.difficulty }}</span>
            </div>

            <!-- Streak -->
            <div class="flex items-center gap-5 border-y border-slate-100 dark:border-slate-800 py-4 mb-4">
              <div>
                <p class="text-[10px] uppercase font-black tracking-widest text-slate-400">Streak</p>
                <div class="flex items-center gap-1 mt-1 font-black text-2xl text-slate-800 dark:text-white">
                  {{ habit.current_streak ?? 0 }}
                  <Flame class="w-5 h-5 text-orange-400 ml-1" />
                </div>
              </div>
              <div>
                <p class="text-[10px] uppercase font-black tracking-widest text-slate-400">Category</p>
                <p class="mt-1 font-bold text-sm text-slate-600 dark:text-slate-300 capitalize">{{ habit.category }}</p>
              </div>
            </div>

            <!-- 7-day heatmap mini blocks -->
            <div class="flex gap-1.5 mb-5 relative">
              <div class="absolute right-0 w-6 h-full bg-gradient-to-l from-white dark:from-[#242526] to-transparent pointer-events-none z-10" />
              <!-- Today's block -->
              <div class="w-7 h-7 rounded-lg shrink-0 border border-black/5 transition-all"
                :class="habit.completed_today
                  ? (habit.habit_type === 'negative' ? 'bg-red-400 ring-2 ring-red-200 dark:ring-red-700' : 'bg-indigo-500 ring-2 ring-indigo-200 dark:ring-indigo-700')
                  : 'bg-slate-100 dark:bg-slate-800'" />
              <!-- Past 6 days (cosmetic placeholders) -->
              <div v-for="n in 6" :key="n"
                class="w-7 h-7 rounded-lg shrink-0 border border-black/5 bg-slate-100/60 dark:bg-slate-800/50"
                :style="`opacity: ${1 - n * 0.13}`" />
            </div>
          </div>

          <!-- ── MARK DONE BUTTON (simple words!) ── -->
          <div class="p-6 pt-0">
            <button @click="markDone(habit.id)"
              class="w-full py-4 rounded-2xl flex items-center justify-center font-black text-sm uppercase tracking-widest transition-all active:scale-90"
              :class="habit.completed_today
                ? 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500'
                : (habit.habit_type === 'negative'
                  ? 'bg-red-500 text-white shadow-lg shadow-red-500/25 hover:bg-red-600'
                  : 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/25 hover:bg-indigo-700')"
            >
              <Check v-if="habit.completed_today" class="mr-2 w-4 h-4 stroke-[3px]" />
              {{
                habit.completed_today
                  ? 'Done Today ✓  (click to undo)'
                  : (habit.habit_type === 'negative' ? 'I Resisted Today! 💪' : 'Mark as Done')
              }}
            </button>
          </div>
        </div>
      </TransitionGroup>

      <!-- Empty state -->
      <div v-if="filtered.length === 0"
        class="md:col-span-3 py-24 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-3xl flex flex-col items-center gap-4 text-slate-400">
        <span class="text-6xl">🌱</span>
        <p class="font-bold text-xl">{{ search ? 'No habits match your search.' : 'No habits yet!' }}</p>
        <p class="text-sm">Click <strong>Add Habit</strong> above to start building your routine.</p>
      </div>
    </div>
  </div>
</template>