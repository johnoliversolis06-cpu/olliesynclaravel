<script setup>
/**
 * AuthenticatedLayout.vue
 * FILE PATH: resources/js/Layouts/AuthenticatedLayout.vue
 */
import { ref, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const page = usePage()
const user = page.props.auth?.user

// ── Dark / Light Mode ─────────────────────────────────────────
const isDark = ref(false)

const applyTheme = (dark) => {
  if (dark) document.documentElement.classList.add('dark')
  else       document.documentElement.classList.remove('dark')
  localStorage.setItem('olliesync-theme', dark ? 'dark' : 'light')
}

const toggleTheme = () => {
  isDark.value = !isDark.value
  applyTheme(isDark.value)
  router.patch(route('settings.theme'), { theme: isDark.value ? 'dark' : 'light' }, {
    preserveScroll: true,
    preserveState:  true,
  })
}

// ── Mobile nav ────────────────────────────────────────────────
const mobileOpen = ref(false)

// ── Nav items — correct route names, simple labels ────────────
const navItems = [
  { label: 'Home',     emoji: '🏠', routeName: 'dashboard'       },
  { label: 'Tasks',    emoji: '📋', routeName: 'tasks.index'     },
  { label: 'Habits',   emoji: '🔁', routeName: 'habits.index'    },
  { label: 'Focus',    emoji: '⏱',  routeName: 'focus.index'     },
  { label: 'Journal',  emoji: '📓', routeName: 'journal.index'   },
  { label: 'Stats',    emoji: '📊', routeName: 'analytics.index' },
  { label: 'Settings', emoji: '⚙️',  routeName: 'settings.index'  },
]

// Safe helper — never crashes if a route doesn't exist yet
const safeRoute = (name) => {
  try { return route(name) } catch { return '#' }
}

const isActive = (name) => {
  try { return route().current(name) } catch { return false }
}

const logout = () => router.post(route('logout'))

// ── Service Worker + Push ─────────────────────────────────────
const registerSW = async () => {
  if (!('serviceWorker' in navigator)) return
  try {
    await navigator.serviceWorker.register('/sw.js')
  } catch {}
}

const subscribeToPush = async (reg) => {
  if (!reg || !('PushManager' in window)) return
  if (Notification.permission !== 'granted') return
  try {
    const existing = await reg.pushManager.getSubscription()
    if (existing) {
      const d = existing.toJSON()
      await axios.post(safeRoute('notifications.subscribe'), { endpoint: d.endpoint, keys: d.keys })
    }
  } catch {}
}

onMounted(async () => {
  // Apply saved theme instantly
  const saved = localStorage.getItem('olliesync-theme') ?? user?.theme
  isDark.value = saved === 'dark'
  applyTheme(isDark.value)

  // Register service worker silently
  const reg = await registerSW()
  if (Notification.permission === 'granted') await subscribeToPush(reg)
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-[#18191A] text-slate-800 dark:text-white font-sans transition-colors duration-200">

    <!-- ══ TOP NAV ══ -->
    <nav class="sticky top-0 z-50 bg-white/80 dark:bg-[#242526]/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center h-16 gap-3">

          <!-- Logo -->
          <Link :href="safeRoute('dashboard')" class="flex items-center gap-2 shrink-0 mr-2">
            <span class="w-8 h-8 bg-teal-500 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md">O</span>
            <span class="font-black text-lg hidden sm:block tracking-tight">OllieSync</span>
          </Link>

          <!-- Desktop nav links -->
          <div class="hidden md:flex items-center gap-0.5 flex-1">
            <Link
              v-for="item in navItems"
              :key="item.routeName"
              :href="safeRoute(item.routeName)"
              class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-bold transition-all"
              :class="isActive(item.routeName)
                ? 'bg-teal-500 text-white shadow-md shadow-teal-500/20'
                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'"
            >
              <span>{{ item.emoji }}</span> {{ item.label }}
            </Link>
          </div>

          <!-- Right controls -->
          <div class="flex items-center gap-2 ml-auto">

            <!-- Dark/Light toggle -->
            <button @click="toggleTheme"
              class="w-9 h-9 rounded-xl flex items-center justify-center border border-slate-200 dark:border-slate-700 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
              :title="isDark ? 'Light mode' : 'Dark mode'">
              <span>{{ isDark ? '☀️' : '🌙' }}</span>
            </button>

            <!-- User + logout (desktop) -->
            <div class="hidden md:flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-slate-700 ml-1">
              <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-teal-500 flex items-center justify-center text-white font-black text-xs shadow">
                {{ user?.name?.[0]?.toUpperCase() ?? 'U' }}
              </div>
              <span class="text-sm font-bold text-slate-600 dark:text-slate-300 hidden lg:block">{{ user?.name }}</span>
              <button @click="logout"
                class="text-xs font-bold text-slate-400 hover:text-red-500 px-2 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-500/10 transition-all ml-1">
                Logout
              </button>
            </div>

            <!-- Mobile hamburger -->
            <button @click="mobileOpen = !mobileOpen"
              class="md:hidden w-9 h-9 rounded-xl flex items-center justify-center border border-slate-200 dark:border-slate-700 text-slate-500 text-xl">
              {{ mobileOpen ? '✕' : '☰' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile dropdown -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
      >
        <div v-if="mobileOpen"
          class="md:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-[#242526] px-4 py-4 space-y-1 shadow-xl">
          <Link
            v-for="item in navItems"
            :key="item.routeName"
            :href="safeRoute(item.routeName)"
            @click="mobileOpen = false"
            class="flex items-center gap-3 px-4 py-3.5 rounded-2xl font-bold text-sm transition-all"
            :class="isActive(item.routeName)
              ? 'bg-teal-500 text-white'
              : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'"
          >
            <span class="text-lg">{{ item.emoji }}</span> {{ item.label }}
          </Link>
          <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-3 px-4 py-3">
              <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-teal-500 flex items-center justify-center text-white font-black text-sm shadow">
                {{ user?.name?.[0]?.toUpperCase() ?? 'U' }}
              </div>
              <div>
                <p class="font-bold text-slate-800 dark:text-white text-sm">{{ user?.name }}</p>
                <p class="text-xs text-slate-400">{{ user?.email }}</p>
              </div>
            </div>
            <button @click="logout"
              class="flex items-center gap-3 px-4 py-3.5 w-full rounded-2xl font-bold text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all">
              🚪 Log Out
            </button>
          </div>
        </div>
      </Transition>
    </nav>

    <!-- ══ PAGE CONTENT ══ -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
      <slot />
    </main>

    <!-- ══ FOOTER ══ -->
    <footer class="border-t border-slate-200 dark:border-slate-800 py-6 text-center">
      <p class="text-xs text-slate-400 font-bold tracking-widest uppercase">
        OllieSync © {{ new Date().getFullYear() }} — Built by Ollie 🚀
      </p>
    </footer>
  </div>
</template>