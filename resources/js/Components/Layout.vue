<script setup>
import { ref, onMounted, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { LayoutDashboard, CheckSquare, Repeat, Timer, Wallet, BarChart3, Settings, LogOut, Sun, Moon, BookOpen, Menu, X } from 'lucide-vue-next'
import { useDark, useToggle } from '@vueuse/core'

// Replace Context API with Inertia props globally
const page = usePage()
const user = page.props.auth.user

// VueUse perfectly handles dark mode via localStorage & HTML classes (better than Firebase DB sync)
const isDark = useDark()
const toggleDark = useToggle(isDark)

const sidebarOpen = ref(false)

// Replicate your Exact Nav structure
const navItems = [
  { path: route('dashboard'), icon: LayoutDashboard, label: 'Dashboard', routePrefix: 'dashboard' },
  { path: route('journal.index'), icon: BookOpen, label: 'Journal', routePrefix: 'journal' },
  { path: route('tasks.index'), icon: CheckSquare, label: 'Tasks', routePrefix: 'tasks' },
  { path: route('habits.index'), icon: Repeat, label: 'Habits', routePrefix: 'habits' },
  { path: route('focus.index'), icon: Timer, label: 'Focus', routePrefix: 'focus' },
  { path: route('analytics.index'), icon: BarChart3, label: 'Analytics', routePrefix: 'analytics' },
  { path: route('settings.index'), icon: Settings, label: 'Settings', routePrefix: 'settings' },
]

// Automatically close mobile menu on navigation
watch(() => page.url, () => {
    sidebarOpen.value = false
})

const logout = () => { router.post(route('logout')) }
</script>

<template>
  <div class="flex h-screen h-[100dvh] bg-[#F2F4F7] dark:bg-[#18191A] text-slate-900 dark:text-gray-100 transition-colors duration-300 overflow-hidden relative font-sans">
    
    <!-- Mobile Overlay using Vue Transition -->
    <Transition
      enter-active-class="transition-opacity ease-linear duration-300"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity ease-linear duration-300"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="sidebarOpen" class="fixed inset-0 bg-black/50 z-40 lg:hidden" @click="sidebarOpen = false" />
    </Transition>

    <!-- Sidebar Wrapper -->
    <aside 
      class="fixed inset-y-0 left-0 z-50 w-64 border-r border-slate-300 dark:border-[#3E4042] flex flex-col p-6 gap-8 bg-white dark:bg-[#242526] transform transition-transform duration-300 lg:relative lg:translate-x-0 shadow-2xl lg:shadow-none"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Logo header -->
      <div class="flex items-center justify-between px-2">
        <div class="flex items-center gap-3 group cursor-default">
          <div class="w-10 h-10 bg-[#0CAF89] rounded-xl flex items-center justify-center text-white shadow-lg shadow-teal-500/20 transform group-hover:rotate-12 transition-transform duration-300">
            <Timer :size="24" />
          </div>
          <h1 class="text-xl font-bold tracking-tight">Ollie<span class="text-[#0CAF89] text-opacity-80">Sync</span></h1>
        </div>
        <button class="lg:hidden text-slate-500 hover:text-slate-800 dark:hover:text-gray-200 transition" @click="sidebarOpen = false">
          <X :size="24" />
        </button>
      </div>

      <!-- Navigation Map -->
      <nav class="flex flex-col gap-2 flex-1 overflow-y-auto pr-2 custom-scrollbar">
        <Link
          v-for="item in navItems"
          :key="item.path"
          :href="item.path"
          class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group overflow-hidden relative"
          :class="page.url.startsWith('/' + item.routePrefix) || (page.url === '/' && item.routePrefix === 'dashboard')
            ? 'bg-[#0CAF89]/10 text-[#0CAF89] dark:bg-[#0CAF89]/10 font-bold' 
            : 'hover:bg-slate-100 dark:hover:bg-[#3E4042] text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          <!-- Animated hover slider logic without framer motion! -->
          <div class="absolute left-0 w-1 h-8 rounded-r bg-[#0CAF89] transform transition-transform origin-left duration-200"
               :class="page.url.startsWith('/' + item.routePrefix) || (page.url === '/' && item.routePrefix === 'dashboard') ? 'scale-x-100' : 'scale-x-0'">
          </div>
          
          <component :is="item.icon" :size="20" class="relative z-10 transition-transform duration-200 group-hover:scale-110" />
          <span class="relative z-10">{{ item.label }}</span>
        </Link>
      </nav>

      <!-- Account / Bottom Settings -->
      <div class="flex flex-col gap-2 mt-auto">
        <button 
          @click="toggleDark()"
          class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium hover:bg-slate-100 dark:hover:bg-[#3E4042] text-slate-500 dark:text-slate-400 transition-all group w-full text-left"
        >
          <div class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-[#18191A] flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
            <Sun v-if="isDark" :size="18" class="text-yellow-500" />
            <Moon v-else :size="18" class="text-indigo-500" />
          </div>
          {{ isDark ? 'Light Mode' : 'Dark Mode' }}
        </button>

        <button 
          @click="logout"
          class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all w-full text-left"
        >
          <LogOut :size="20" />
          Sign Out
        </button>
      </div>
    </aside>

    <!-- Main Right Content Wrapper -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden relative">
      <!-- Top Mobile Navigation -->
      <div class="lg:hidden flex items-center justify-between p-4 bg-white dark:bg-[#242526] border-b border-slate-300 dark:border-[#3E4042] shrink-0 sticky top-0 z-30 shadow-sm">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-[#0CAF89] rounded-lg flex items-center justify-center text-white">
            <Timer :size="18" />
          </div>
          <h1 class="text-lg font-bold tracking-tight">Ollie<span class="text-[#0CAF89]">Sync</span></h1>
        </div>
        <button @click="sidebarOpen = true" class="p-2 text-slate-600 dark:text-slate-300 focus:outline-none hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
          <Menu :size="24" />
        </button>
      </div>

      <!-- Inertia Slot (Page Content renders here) -->
      <main class="flex-1 overflow-y-auto p-4 md:p-8 w-full custom-scrollbar pb-24">
        <!-- Vue Page Transitions! Every Inertia load gets animated -->
        <Transition
          mode="out-in"
          enter-active-class="transition duration-300 ease-out"
          enter-from-class="opacity-0 translate-y-8"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-200 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-8"
        >
            <div :key="page.url" class="max-w-6xl mx-auto h-full">
              <slot />
            </div>
        </Transition>
      </main>
    </div>

  </div>
</template>

<style scoped>
/* A beautiful modern scrollbar replacement for main content and menus */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.3);
  border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(75, 85, 99, 0.4);
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.5);
}
</style>