<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()

const navLinks = [
  { name: 'Dashboard', path: route('dashboard'),   icon: '📊' },
  { name: 'Tasks',     path: route('tasks.index'),   icon: '✅' },
  { name: 'Habits',    path: route('habits.index'),  icon: '🌱' },
  { name: 'Journal',   path: route('journal.index'), icon: '📓' },
  { name: 'Focus Mode',path: route('focus.index'),   icon: '⏱️' },
  { name: 'Analytics', path: route('analytics.index'),   icon: '📈' },
  { name: 'Settings',  path: route('settings.index'),    icon: '⚙️' },
]

function logout() { router.post(route('logout')) }
</script>

<template>
  <div class="flex h-screen bg-gray-100 font-sans">
    
    <!-- Sidebar Setup -->
    <aside class="w-64 bg-slate-900 text-gray-200 shadow-xl flex flex-col hidden md:flex">
      <!-- Title App Area -->
      <div class="h-16 flex items-center px-6 bg-slate-950 font-bold text-xl text-white tracking-widest border-b border-slate-800">
        OLLIE<span class="text-indigo-500">SYNC</span>
      </div>

      <!-- Links Loop Area -->
      <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
        <Link 
          v-for="link in navLinks" :key="link.name" :href="link.path"
          class="flex items-center px-4 py-3 rounded-xl transition-all font-medium border border-transparent hover:bg-slate-800 hover:text-white"
        >
          <span class="mr-4 text-xl">{{ link.icon }}</span>
          {{ link.name }}
        </Link>
      </nav>

      <!-- Account Bottom Setting -->
      <div class="p-4 bg-slate-950 mt-auto flex items-center justify-between">
          <div class="flex items-center text-sm font-bold truncate">
             <span>{{ page.props.auth.user.name }}</span>
          </div>
          <button @click="logout" class="text-sm px-3 py-1 bg-red-600/20 hover:bg-red-500 hover:text-white text-red-500 rounded-lg transition-colors">
              Exit
          </button>
      </div>
    </aside>

    <!-- Page Content (Dynamically Renders Other Files Here!) -->
    <main class="flex-1 overflow-y-auto bg-slate-50 relative">
      <slot /> <!-- Every View File Renders in this Exact Slot! -->
    </main>

  </div>
</template>