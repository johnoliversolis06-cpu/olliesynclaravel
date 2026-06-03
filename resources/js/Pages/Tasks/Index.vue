<script setup>
/**
 * Tasks/Index.vue
 * FILE PATH: resources/js/Pages/Tasks/Index.vue
 *
 * ✅ Prompts user before redirecting to Focus if timer is running
 * ✅ "Yes done" → redirect with ?task_id= (switches task in timer)
 * ✅ "No keep current" → redirect WITHOUT task_id (timer keeps current task)
 */
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Plus, Trash2, Pin, Play, Search, CheckCheck, X } from 'lucide-vue-next'

const props = defineProps({
  activeTasks:    Array,
  completedTasks: Array,
  stats:          Object,
  categories:     Array,
  activeSession:  Object,  // null | { id, task_id, task_title, status }
})

// ── Focus redirect prompt state ───────────────────────────────
const showFocusPrompt   = ref(false)
const pendingTaskId     = ref(null)
const pendingTaskTitle  = ref('')

const startFocus = (task) => {
  if (props.activeSession) {
    // Timer is running — ask first
    pendingTaskId.value    = task.id
    pendingTaskTitle.value = task.title
    showFocusPrompt.value  = true
  } else {
    // No timer running — go straight to focus with task selected
    router.visit(route('focus.index') + '?task_id=' + task.id)
  }
}

// "Yes, I'm done" → switch task in focus timer
const confirmSwitch = () => {
  showFocusPrompt.value = false
  router.visit(route('focus.index') + '?task_id=' + pendingTaskId.value)
}

// "No, keep going" → go to focus WITHOUT changing the current task
const keepCurrent = () => {
  showFocusPrompt.value = false
  router.visit(route('focus.index'))
}

const cancelPrompt = () => {
  showFocusPrompt.value = false
  pendingTaskId.value   = null
  pendingTaskTitle.value = ''
}

// ── Task actions ──────────────────────────────────────────────
const completeTask = (id) => router.patch(route('tasks.complete', id), {}, { preserveScroll: true })
const pinTask      = (id) => router.patch(route('tasks.pin',      id), {}, { preserveScroll: true })
const deleteTask   = (id) => {
  if (confirm('Delete this task?')) router.delete(route('tasks.destroy', id), { preserveScroll: true })
}

// ── Add task form ─────────────────────────────────────────────
const form = useForm({ title: '', difficulty: 'medium', deadline: '', category: '' })
const submitTask = () => form.post(route('tasks.store'), {
  preserveScroll: true,
  onSuccess: () => form.reset()
})

// ── Filters ───────────────────────────────────────────────────
const search        = ref('')
const filterDiff    = ref('all')
const filterCat     = ref('all')
const showCompleted = ref(false)

const filtered = computed(() => {
  let list = props.activeTasks ?? []
  if (search.value)             list = list.filter(t => t.title.toLowerCase().includes(search.value.toLowerCase()))
  if (filterDiff.value !== 'all') list = list.filter(t => t.difficulty === filterDiff.value)
  if (filterCat.value  !== 'all') list = list.filter(t => t.category   === filterCat.value)
  return list
})

const isOverdue  = (d) => d && new Date(d) < new Date(new Date().setHours(0,0,0,0))
const isDueToday = (d) => d && new Date(d).toDateString() === new Date().toDateString()

const diffColor = { easy: 'text-emerald-500', medium: 'text-amber-500', hard: 'text-red-500' }
</script>

<template>
  <Head title="My Tasks" />

  <!-- ══ FOCUS REDIRECT PROMPT ══ -->
  <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100">
    <div v-if="showFocusPrompt"
      class="fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-[#242526] rounded-3xl p-7 max-w-sm w-full shadow-2xl border border-slate-200 dark:border-slate-700">

        <button @click="cancelPrompt" class="absolute top-5 right-5 text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
          <X class="w-5 h-5" />
        </button>

        <div class="text-4xl text-center mb-4">⏱️</div>
        <h2 class="text-xl font-black text-slate-800 dark:text-white text-center mb-2">Timer is running!</h2>

        <div v-if="activeSession?.task_title" class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-2xl p-3 mb-4 text-center">
          <p class="text-xs font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">Currently working on</p>
          <p class="font-bold text-slate-800 dark:text-white text-sm">📋 {{ activeSession.task_title }}</p>
        </div>

        <p class="text-sm text-slate-500 dark:text-slate-400 text-center leading-relaxed mb-6">
          Are you done with the current task? If yes, the timer will switch to
          <strong class="text-slate-700 dark:text-white">"{{ pendingTaskTitle }}"</strong>.
          If no, your current task stays selected.
        </p>

        <div class="flex gap-3">
          <!-- Yes done — switch task -->
          <button @click="confirmSwitch"
            class="flex-1 py-3.5 bg-teal-500 hover:bg-teal-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest transition-all active:scale-95">
            ✅ Yes, Switch Task
          </button>
          <!-- No — keep current, just go to focus page -->
          <button @click="keepCurrent"
            class="flex-1 py-3.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-2xl font-black text-sm uppercase tracking-widest transition-all active:scale-95">
            ⏱️ Keep Going
          </button>
        </div>
      </div>
    </div>
  </Transition>

  <div class="max-w-6xl mx-auto pb-24 md:pb-10">

    <!-- Stats bar -->
    <div class="flex flex-wrap gap-3 mb-6">
      <div v-for="[label, value, color] in [
        ['Total',     stats?.total     ?? 0, 'text-slate-800 dark:text-white'],
        ['⚠️ Overdue', stats?.overdue  ?? 0, 'text-red-500'],
        ['📅 Today',  stats?.today     ?? 0, 'text-amber-500'],
        ['📌 Pinned', stats?.pinned    ?? 0, 'text-yellow-500'],
        ['✅ Done',   stats?.completed ?? 0, 'text-emerald-500'],
      ]" :key="label"
        class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-2xl px-4 py-2.5 shadow-sm">
        <span class="text-xl font-black" :class="color">{{ value }}</span>
        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5">{{ label }}</p>
      </div>
    </div>

    <!-- Active session banner -->
    <div v-if="activeSession"
      class="mb-4 flex items-center gap-3 bg-teal-50 dark:bg-teal-500/10 border border-teal-200 dark:border-teal-500/30 rounded-2xl px-4 py-3">
      <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse shrink-0" />
      <p class="text-sm font-bold text-teal-700 dark:text-teal-400 flex-1">
        Timer running{{ activeSession.task_title ? `: "${activeSession.task_title}"` : '' }}
      </p>
      <Link :href="route('focus.index')" class="text-xs font-black text-teal-600 dark:text-teal-400 uppercase tracking-widest hover:underline">
        Go to Timer →
      </Link>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 items-start">

      <!-- Task list -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- Search + filters -->
        <div class="flex flex-wrap gap-2 items-center">
          <div class="relative flex-1 min-w-[180px]">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input v-model="search" type="text" placeholder="Search tasks..."
              class="w-full pl-9 pr-4 py-2.5 rounded-xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-medium text-sm transition" />
          </div>
          <select v-model="filterDiff"
            class="py-2.5 px-3 rounded-xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 outline-none">
            <option value="all">All Difficulties</option>
            <option value="easy">Easy</option>
            <option value="medium">Medium</option>
            <option value="hard">Hard</option>
          </select>
          <select v-if="categories?.length" v-model="filterCat"
            class="py-2.5 px-3 rounded-xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 text-sm font-bold text-slate-600 dark:text-slate-300 outline-none">
            <option value="all">All Categories</option>
            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
        </div>

        <!-- Task rows -->
        <TransitionGroup tag="div" class="space-y-2 relative"
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 -translate-x-4"
          enter-to-class="opacity-100 translate-x-0"
          leave-active-class="absolute transition duration-200 ease-in"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0 translate-x-4"
        >
          <div v-for="task in filtered" :key="task.id"
            class="bg-white dark:bg-[#242526] border rounded-2xl p-4 flex gap-3 shadow-sm group hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300"
            :class="task.is_pinned
              ? 'border-yellow-300 dark:border-yellow-700'
              : 'border-slate-200 dark:border-slate-800'"
          >
            <!-- Checkbox -->
            <button @click="completeTask(task.id)"
              class="mt-0.5 w-6 h-6 rounded-lg border-2 border-slate-300 dark:border-slate-600 shrink-0 flex items-center justify-center hover:border-teal-500 hover:bg-teal-50 active:scale-75 transition-all">
              <CheckCheck class="w-3.5 h-3.5 text-teal-500 opacity-0 group-hover:opacity-30 transition" />
            </button>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-bold text-slate-800 dark:text-white truncate"
                :class="{ 'text-red-500 dark:text-red-400': isOverdue(task.deadline) }">
                {{ task.title }}
                <span v-if="task.is_pinned" class="ml-1 text-[9px] px-1.5 py-0.5 bg-yellow-100 text-yellow-600 rounded font-black">📌</span>
              </h3>
              <div class="flex flex-wrap gap-1.5 mt-1.5">
                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded-lg" :class="diffColor[task.difficulty]">{{ task.difficulty }}</span>
                <span v-if="task.category" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-500 rounded-lg">{{ task.category }}</span>
                <span v-if="task.deadline" class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg flex items-center gap-1"
                  :class="isOverdue(task.deadline) ? 'bg-red-100 text-red-600' : isDueToday(task.deadline) ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 dark:bg-slate-800 text-slate-500'">
                  {{ isOverdue(task.deadline) ? '⚠️ Overdue' : isDueToday(task.deadline) ? '📅 Today' : task.deadline }}
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 shrink-0">
              <!-- ▶ START FOCUS — with prompt if timer running -->
              <button @click="startFocus(task)"
                class="w-8 h-8 bg-teal-500 text-white rounded-xl flex items-center justify-center opacity-0 group-hover:opacity-100 hover:bg-teal-600 active:scale-90 transition-all shadow-md shadow-teal-500/20"
                title="Focus on this task">
                <Play class="w-3.5 h-3.5 fill-current" />
              </button>
              <button @click="pinTask(task.id)"
                class="w-8 h-8 rounded-xl hover:bg-yellow-50 dark:hover:bg-yellow-500/10 transition-colors flex items-center justify-center"
                :class="task.is_pinned ? 'text-yellow-500' : 'text-slate-400 hover:text-yellow-500'">
                <Pin class="w-3.5 h-3.5" />
              </button>
              <button @click="deleteTask(task.id)"
                class="w-8 h-8 rounded-xl hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors text-slate-300 hover:text-red-500 flex items-center justify-center">
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </TransitionGroup>

        <!-- Empty -->
        <div v-if="!filtered.length"
          class="py-16 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl flex flex-col items-center gap-3 text-slate-400">
          <span class="text-5xl">🎉</span>
          <p class="font-bold text-lg">{{ search ? 'No tasks match your search.' : 'Nothing left to do!' }}</p>
        </div>

        <!-- Completed tasks toggle -->
        <div v-if="completedTasks?.length" class="pt-4">
          <button @click="showCompleted = !showCompleted"
            class="flex items-center gap-2 text-slate-400 font-bold text-sm hover:text-slate-700 dark:hover:text-white transition">
            <CheckCheck class="w-4 h-4" />
            {{ showCompleted ? 'Hide' : 'Show' }} {{ completedTasks.length }} Completed Tasks
          </button>
          <div v-if="showCompleted" class="mt-3 space-y-2">
            <div v-for="task in completedTasks" :key="task.id"
              class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 rounded-xl opacity-60">
              <CheckCheck class="w-4 h-4 text-teal-500 shrink-0" />
              <span class="text-sm font-medium text-slate-500 line-through flex-1 truncate">{{ task.title }}</span>
              <!-- Undo complete -->
              <button @click="completeTask(task.id)"
                class="text-[9px] font-black text-slate-400 hover:text-teal-500 uppercase tracking-widest transition shrink-0">
                Undo
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add task sidebar -->
      <div class="w-full lg:w-[320px] shrink-0 sticky top-24">
        <form @submit.prevent="submitTask"
          class="bg-white dark:bg-[#242526] p-5 rounded-3xl shadow-xl border border-slate-200 dark:border-slate-800 space-y-3">
          <h2 class="text-xs font-black uppercase tracking-widest text-slate-500">Add New Task</h2>

          <input v-model="form.title" type="text" placeholder="What needs to get done?" required
            class="w-full p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 outline-none focus:border-teal-400 font-bold transition text-sm text-slate-800 dark:text-white" />

          <div class="grid grid-cols-2 gap-2.5">
            <div>
              <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Deadline</label>
              <input v-model="form.deadline" type="date"
                class="w-full p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-200 outline-none cursor-pointer" />
            </div>
            <div>
              <label class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">How Hard?</label>
              <select v-model="form.difficulty"
                class="w-full p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-700 dark:text-slate-200 outline-none">
                <option value="easy">Easy 🟢</option>
                <option value="medium">Medium 🟡</option>
                <option value="hard">Hard 🔴</option>
              </select>
            </div>
          </div>

          <input v-model="form.category" type="text" placeholder="Category (Work, School...)"
            class="w-full p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 outline-none focus:border-teal-400 transition" />

          <button type="submit" :disabled="form.processing"
            class="w-full py-3.5 bg-slate-900 dark:bg-teal-500 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-600 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg">
            <Plus class="w-4 h-4" /> Add Task
          </button>
        </form>
      </div>
    </div>
  </div>
</template>