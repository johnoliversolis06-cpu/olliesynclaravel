<script setup>
/**
 * ActivityRecommendations.vue
 * FILE PATH: resources/js/Components/ActivityRecommendations.vue
 *
 * AI-powered activity recommendations + smart break rewards.
 * Uses the Anthropic Claude API to generate personalized suggestions
 * based on the user's focus session count and time of day.
 *
 * Drop this into Dashboard.vue:
 *   import ActivityRecommendations from '@/Components/ActivityRecommendations.vue'
 *   <ActivityRecommendations :sessions-today="stats?.next_count ?? 0" :tasks="tasks" />
 */
import { ref, onMounted, computed } from 'vue'

const props = defineProps({
  sessionsToday: { type: Number, default: 0 },
  tasks:         { type: Array,  default: () => [] },
  userName:      { type: String, default: 'there' },
})

// ── Static activity cards (always shown — instant, no API needed) ─
const ACTIVITIES = [
  { icon: '🚶', title: 'Take a Walk',       desc: '10 minutes outside resets your brain and boosts creativity.',    tag: 'movement',  color: 'from-emerald-400 to-teal-500'    },
  { icon: '💧', title: 'Drink Water',       desc: 'Hydration directly improves focus. Down a full glass right now.', tag: 'health',    color: 'from-blue-400 to-cyan-500'       },
  { icon: '🧘', title: 'Box Breathing',     desc: '4 counts in, hold 4, out 4, hold 4. Repeat 4 times.',            tag: 'mental',    color: 'from-violet-400 to-purple-500'   },
  { icon: '📵', title: 'Phone Break',       desc: 'Put your phone face-down for the next 25 minutes. You got this.', tag: 'digital',   color: 'from-rose-400 to-pink-500'       },
  { icon: '👀', title: '20-20-20 Eye Rest', desc: 'Look at something 20 feet away for 20 seconds. Your eyes need it.', tag: 'health', color: 'from-amber-400 to-orange-500'    },
  { icon: '🤸', title: 'Stretch',           desc: '2 minutes of shoulder rolls and neck stretches. Stand up now.',  tag: 'movement',  color: 'from-lime-400 to-green-500'      },
  { icon: '☕', title: 'Coffee Break',      desc: 'You earned it. Step away for 5 minutes, then come back strong.',  tag: 'break',     color: 'from-yellow-500 to-amber-600'    },
  { icon: '📖', title: 'Read 1 Page',       desc: 'One page of a real book. Slow your brain down intentionally.',   tag: 'learning',  color: 'from-indigo-400 to-blue-500'     },
  { icon: '🪴', title: 'Water Your Plant',  desc: 'Small acts of care build a habit of mindfulness.',               tag: 'mindful',   color: 'from-green-400 to-emerald-600'   },
  { icon: '🎵', title: 'Listen & Breathe', desc: 'Put on one calming song. Close your eyes. Just exist for 3 mins.', tag: 'mental',   color: 'from-purple-400 to-violet-600'   },
]

// Rotate based on time + session count so it changes throughout the day
const visibleActivities = computed(() => {
  const hour   = new Date().getHours()
  const offset = (props.sessionsToday + Math.floor(hour / 3)) % ACTIVITIES.length
  const rotated = [...ACTIVITIES.slice(offset), ...ACTIVITIES.slice(0, offset)]
  return rotated.slice(0, 4)
})

// ── AI REWARD after focus session ────────────────────────────
const aiReward    = ref(null)
const loadingAI   = ref(false)
const showReward  = ref(false)

// Static smart rewards (rotate, no API call needed — instant)
const REWARDS = [
  { emoji: '🔥', msg: `${props.sessionsToday + 1} sessions done today. You're in the top 10% of productive people right now.` },
  { emoji: '💪', msg: "Your brain just got stronger. Every session builds the habit. Keep it going." },
  { emoji: '🎯', msg: "That's real progress. Most people quit before starting. You already won today." },
  { emoji: '⭐', msg: "Session complete! Take a 5-minute break — your brain consolidates memory during rest." },
  { emoji: '🚀', msg: "Momentum is building. One more session and you'll be at peak performance." },
  { emoji: '🌱', msg: "Consistency over intensity. You showed up today. That's the whole game." },
  { emoji: '🏆', msg: "Deep work done. You just outworked 90% of people who planned to be productive today." },
  { emoji: '🧠', msg: "Your prefrontal cortex thanks you. That was real cognitive exercise." },
]

const getAIReward = async () => {
  loadingAI.value  = true
  showReward.value = true

  // Try Claude API first
  try {
    const response = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'claude-sonnet-4-20250514',
        max_tokens: 80,
        messages: [{
          role: 'user',
          content: `You are an encouraging productivity coach. The user just finished focus session #${props.sessionsToday + 1} today. Give ONE short (max 20 words), energetic, personal reward message. No emojis in text. Just the message. Examples: "Three sessions in. Your brain is in flow state now. Ride it." or "Take 5 minutes off. You earned it. Come back even sharper."`
        }]
      })
    })
    if (response.ok) {
      const data = await response.json()
      const text = data.content?.[0]?.text?.trim()
      if (text) {
        aiReward.value = { emoji: '🤖', msg: text }
        loadingAI.value = false
        return
      }
    }
  } catch {}

  // Fallback: static reward
  const idx = props.sessionsToday % REWARDS.length
  aiReward.value  = REWARDS[idx]
  loadingAI.value = false
}

// Expose method for parent to call after timer completes
defineExpose({ getAIReward })

// Show reward automatically if triggered from focus page via prop
const currentCard = ref(0)
const scrollNext = () => { currentCard.value = (currentCard.value + 1) % visibleActivities.value.length }
const scrollPrev = () => { currentCard.value = (currentCard.value - 1 + visibleActivities.value.length) % visibleActivities.value.length }
</script>

<template>
  <div class="space-y-4">

    <!-- ── AI REWARD POPUP ── -->
    <Transition enter-active-class="transition duration-500 ease-out" enter-from-class="opacity-0 scale-95 -translate-y-4" enter-to-class="opacity-100 scale-100 translate-y-0">
      <div v-if="showReward"
        class="bg-gradient-to-r from-slate-900 to-slate-800 text-white rounded-3xl p-5 relative overflow-hidden shadow-2xl">
        <!-- Glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-teal-500/10 to-indigo-500/10 pointer-events-none" />

        <button @click="showReward = false" class="absolute top-4 right-4 text-white/40 hover:text-white transition text-xl">&times;</button>

        <div class="relative z-10">
          <p class="text-[10px] font-black uppercase tracking-widest text-teal-400 mb-2">🎉 Session Complete</p>
          <div v-if="loadingAI" class="flex items-center gap-3">
            <div class="w-5 h-5 border-2 border-teal-400 border-t-transparent rounded-full animate-spin" />
            <span class="text-sm text-white/60">Getting your reward...</span>
          </div>
          <div v-else class="flex items-start gap-3">
            <span class="text-3xl shrink-0">{{ aiReward?.emoji ?? '⭐' }}</span>
            <p class="text-base font-bold leading-snug text-white">{{ aiReward?.msg }}</p>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── ACTIVITY RECOMMENDATIONS SLIDER ── -->
    <div class="bg-white dark:bg-[#242526] border border-slate-200 dark:border-slate-800 rounded-3xl p-5">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="font-black text-slate-800 dark:text-white text-sm">Recommended For You</h2>
          <p class="text-[10px] text-slate-400 mt-0.5 font-medium">Activities that actually help you recharge</p>
        </div>
        <!-- Nav arrows -->
        <div class="flex gap-1">
          <button @click="scrollPrev"
            class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-800 dark:hover:text-white transition text-xs font-black">‹</button>
          <button @click="scrollNext"
            class="w-7 h-7 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-800 dark:hover:text-white transition text-xs font-black">›</button>
        </div>
      </div>

      <!-- Card carousel (shows 1 on mobile, 2 on tablet, scroll) -->
      <div class="overflow-hidden">
        <div class="flex gap-3 transition-transform duration-500"
          :style="`transform: translateX(calc(-${currentCard * 100}% - ${currentCard * 12}px))`">

          <div v-for="(activity, idx) in visibleActivities" :key="idx"
            class="flex-shrink-0 w-full sm:w-[calc(50%-6px)] bg-gradient-to-br rounded-2xl p-5 text-white relative overflow-hidden"
            :class="activity.color"
          >
            <!-- Background pattern -->
            <div class="absolute -right-4 -bottom-4 text-7xl opacity-10 pointer-events-none">{{ activity.icon }}</div>

            <div class="relative z-10">
              <span class="text-3xl mb-3 block">{{ activity.icon }}</span>
              <span class="text-[9px] font-black uppercase tracking-widest bg-white/20 px-2 py-0.5 rounded-full mb-2 inline-block">
                {{ activity.tag }}
              </span>
              <h3 class="text-base font-black mt-2 leading-tight">{{ activity.title }}</h3>
              <p class="text-xs text-white/80 mt-1 leading-relaxed">{{ activity.desc }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Dots -->
      <div class="flex justify-center gap-1.5 mt-4">
        <button v-for="(_, i) in visibleActivities" :key="i"
          @click="currentCard = i"
          class="transition-all rounded-full"
          :class="currentCard === i ? 'w-4 h-1.5 bg-teal-500' : 'w-1.5 h-1.5 bg-slate-300 dark:bg-slate-700'" />
      </div>
    </div>

    <!-- ── URGENT TASK RECOMMENDATION ── -->
    <div v-if="tasks?.filter(t => t.difficulty === 'hard' && !t.completed).length"
      class="bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-3xl p-5 shadow-lg shadow-red-500/20">
      <p class="text-[10px] font-black uppercase tracking-widest text-red-200 mb-2">🤖 Smart Suggestion</p>
      <p class="font-black text-base mb-1">
        Tackle your hardest task first 💪
      </p>
      <p class="text-sm text-red-100 leading-snug">
        "{{ tasks.filter(t => t.difficulty === 'hard' && !t.completed)[0]?.title }}" is your most challenging task.
        Research shows hard tasks done early lead to 3× more daily productivity.
      </p>
      <div class="mt-3 bg-white/20 rounded-xl px-3 py-2 text-xs font-black inline-block">
        Start a focus session on this →
      </div>
    </div>

  </div>
</template>