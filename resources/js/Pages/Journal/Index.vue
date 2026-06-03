<script setup>
/**
 * Journal/Index.vue
 * FILE PATH: resources/js/Pages/Journal/Index.vue
 */
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { Plus, Search, Trash2, Edit3, X, BookOpen, TrendingUp } from 'lucide-vue-next'

const props = defineProps({
  entries: Object,   // paginated
  stats:   Object,
  filters: Object,
})

// ── Mood config ───────────────────────────────────────────────
const MOOD = {
  great:    { emoji: '😄', label: 'Great',    color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' },
  good:     { emoji: '🙂', label: 'Good',     color: 'bg-teal-100 text-teal-700 dark:bg-teal-500/20 dark:text-teal-400' },
  okay:     { emoji: '😐', label: 'Okay',     color: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' },
  bad:      { emoji: '😕', label: 'Bad',      color: 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' },
  terrible: { emoji: '😢', label: 'Terrible', color: 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' },
}

// ── Filters ───────────────────────────────────────────────────
const search = ref(props.filters?.search ?? '')
const mood   = ref(props.filters?.mood   ?? '')
const sort   = ref(props.filters?.sort   ?? 'newest')

const applyFilter = () => {
  router.get(route('journal.index'), {
    search: search.value || undefined,
    mood:   mood.value   || undefined,
    sort:   sort.value,
  }, { preserveState: true, replace: true })
}

// ── New Entry Form ────────────────────────────────────────────
const showForm  = ref(false)
const editEntry = ref(null)

const form = useForm({
  title:   '',
  content: '',
  mood:    'good',
  tags:    '',
})

const openNew = () => {
  editEntry.value = null
  form.reset()
  form.mood = 'good'
  showForm.value = true
}

const openEdit = (entry) => {
  editEntry.value = entry
  form.title   = entry.title
  form.content = entry.content ?? ''
  form.mood    = entry.mood
  form.tags    = entry.tags ?? ''
  showForm.value = true
}

const submitForm = () => {
  if (editEntry.value) {
    form.patch(route('journal.update', editEntry.value.id), {
      preserveScroll: true,
      onSuccess: () => { showForm.value = false; form.reset() }
    })
  } else {
    form.post(route('journal.store'), {
      preserveScroll: true,
      onSuccess: () => { showForm.value = false; form.reset() }
    })
  }
}

const deleteEntry = (id) => {
  if (!confirm('Delete this journal entry? This cannot be undone.')) return
  router.delete(route('journal.destroy', id), { preserveScroll: true })
}

// ── Preview: first 120 chars ──────────────────────────────────
const preview = (content) => {
  if (!content) return 'No content yet...'
  return content.length > 120 ? content.slice(0, 120) + '…' : content
}

const formatDate = (d) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
</script>

<template>
  <Head title="Journal" />
  <div class="max-w-5xl mx-auto pb-20 md:pb-10 space-y-6 px-1">

    <!-- ── Header ── -->
    <div class="bg-gradient-to-tr from-violet-700 via-indigo-700 to-slate-900 rounded-3xl p-8 md:p-12 text-white relative overflow-hidden border border-white/10">
      <BookOpen class="absolute right-8 top-8 w-20 h-20 text-white/10 pointer-events-none" />
      <h1 class="text-4xl font-black tracking-tight">My Journal</h1>
      <p class="text-indigo-200 mt-2 text-sm font-medium">Write freely. Reflect daily. Grow over time.</p>

      <!-- Stats -->
      <div class="flex flex-wrap gap-4 mt-6">
        <div v-for="[val, label] in [
          [stats?.total ?? 0, 'Total Entries'],
          [stats?.positive ?? 0, '😄 Good Days'],
          [stats?.negative ?? 0, '😕 Hard Days'],
          [stats?.streak ?? 0, '🔥 Day Streak'],
        ]" :key="label" class="bg-white/10 backdrop-blur-sm rounded-2xl px-4 py-2.5">
          <p class="text-2xl font-black">{{ val }}</p>
          <p class="text-[10px] text-white/60 font-black uppercase tracking-widest mt-0.5">{{ label }}</p>
        </div>
      </div>
    </div>

    <!-- ── Controls ── -->
    <div class="flex flex-col sm:flex-row gap-3 items-center">
      <!-- Search -->
      <div class="relative flex-1 w-full">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
        <input v-model="search" @input="applyFilter" type="text" placeholder="Search entries..."
          class="w-full pl-10 pr-4 py-2.5 rounded-2xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 outline-none focus:border-violet-400 font-medium text-sm transition" />
      </div>

      <!-- Mood filter -->
      <select v-model="mood" @change="applyFilter"
        class="py-2.5 px-3 rounded-2xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 outline-none text-sm font-bold text-slate-600 dark:text-slate-300">
        <option value="">All Moods</option>
        <option v-for="(m, key) in MOOD" :key="key" :value="key">{{ m.emoji }} {{ m.label }}</option>
      </select>

      <!-- Sort -->
      <select v-model="sort" @change="applyFilter"
        class="py-2.5 px-3 rounded-2xl bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 outline-none text-sm font-bold text-slate-600 dark:text-slate-300">
        <option value="newest">Newest First</option>
        <option value="oldest">Oldest First</option>
      </select>

      <!-- New Entry button -->
      <button @click="openNew"
        class="flex items-center gap-2 px-5 py-2.5 bg-violet-600 text-white rounded-2xl font-bold shadow-lg shadow-violet-500/30 hover:bg-violet-700 active:scale-95 transition-all text-sm shrink-0">
        <Plus class="w-4 h-4" /> New Entry
      </button>
    </div>

    <!-- ── Write Form ── -->
    <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-4" enter-to-class="opacity-100 translate-y-0">
      <div v-if="showForm"
        class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 rounded-3xl p-6 shadow-xl">

        <div class="flex items-center justify-between mb-5">
          <h2 class="font-black text-slate-800 dark:text-white text-lg">
            {{ editEntry ? 'Edit Entry' : 'New Journal Entry' }}
          </h2>
          <button @click="showForm = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition">
            <X class="w-5 h-5" />
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <!-- Title -->
          <input v-model="form.title" type="text" placeholder="Title..." required
            class="w-full p-3.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl font-bold text-slate-800 dark:text-white outline-none focus:border-violet-500 transition text-base" />

          <!-- Mood selector -->
          <div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">How are you feeling?</p>
            <div class="flex gap-2 flex-wrap">
              <button v-for="(m, key) in MOOD" :key="key" type="button"
                @click="form.mood = key"
                class="px-4 py-2 rounded-2xl font-bold text-sm transition-all"
                :class="form.mood === key ? m.color + ' ring-2 ring-current' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200'">
                {{ m.emoji }} {{ m.label }}
              </button>
            </div>
          </div>

          <!-- Content -->
          <textarea v-model="form.content" rows="8"
            placeholder="Write your thoughts freely... What happened today? How do you feel? What are you grateful for?"
            class="w-full p-4 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl font-medium text-slate-700 dark:text-slate-200 outline-none focus:border-violet-500 transition resize-none leading-relaxed" />

          <!-- Tags -->
          <input v-model="form.tags" type="text" placeholder="Tags (comma separated): work, health, goals..."
            class="w-full p-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-sm font-medium text-slate-700 dark:text-slate-200 outline-none focus:border-violet-500 transition" />

          <!-- Buttons -->
          <div class="flex gap-3">
            <button type="submit" :disabled="form.processing"
              class="flex-1 py-3.5 bg-violet-600 text-white rounded-2xl font-black uppercase tracking-widest text-sm hover:bg-violet-700 active:scale-95 transition-all shadow-lg">
              {{ editEntry ? 'Save Changes' : 'Save Entry' }}
            </button>
            <button type="button" @click="showForm = false"
              class="px-6 py-3.5 border border-slate-200 dark:border-slate-700 text-slate-500 rounded-2xl font-bold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all text-sm">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </Transition>

    <!-- ── Entries Grid ── -->
    <div v-if="entries?.data?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="entry in entries.data" :key="entry.id"
        class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group flex flex-col">

        <!-- Top: date + mood -->
        <div class="flex items-center justify-between mb-3">
          <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ formatDate(entry.created_at) }}</span>
          <span class="text-xs font-bold px-2.5 py-1 rounded-full" :class="MOOD[entry.mood]?.color ?? 'bg-slate-100 text-slate-500'">
            {{ MOOD[entry.mood]?.emoji }} {{ MOOD[entry.mood]?.label }}
          </span>
        </div>

        <!-- Title -->
        <h3 class="font-black text-slate-800 dark:text-white text-base mb-2 leading-snug line-clamp-2">{{ entry.title }}</h3>

        <!-- Preview -->
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed flex-1 line-clamp-3">{{ preview(entry.content) }}</p>

        <!-- Tags -->
        <div v-if="entry.tags" class="flex flex-wrap gap-1 mt-3">
          <span v-for="tag in entry.tags.split(',')" :key="tag"
            class="text-[10px] font-bold px-2 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-full">
            #{{ tag.trim() }}
          </span>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
          <button @click="openEdit(entry)"
            class="flex-1 py-2 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-500 hover:bg-violet-50 hover:text-violet-600 dark:hover:bg-violet-500/10 font-bold text-xs transition-all flex items-center justify-center gap-1.5">
            <Edit3 class="w-3.5 h-3.5" /> Edit
          </button>
          <button @click="deleteEntry(entry.id)"
            class="py-2 px-4 rounded-xl bg-slate-50 dark:bg-slate-800 text-slate-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-500/10 transition-all">
            <Trash2 class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="py-24 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-3xl flex flex-col items-center gap-4 text-slate-400">
      <span class="text-6xl">📓</span>
      <p class="font-bold text-xl">No entries yet.</p>
      <button @click="openNew" class="px-6 py-3 bg-violet-600 text-white rounded-2xl font-bold hover:bg-violet-700 transition">Write your first entry</button>
    </div>

    <!-- Pagination -->
    <div v-if="entries?.last_page > 1" class="flex justify-center gap-2">
      <a v-for="page in entries.last_page" :key="page"
        :href="entries.links[page]?.url ?? '#'"
        class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm transition-all"
        :class="page === entries.current_page
          ? 'bg-violet-600 text-white'
          : 'bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-700 text-slate-500 hover:border-violet-400'">
        {{ page }}
      </a>
    </div>

  </div>
</template>