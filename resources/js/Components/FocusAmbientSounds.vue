<script setup>
/**
 * FocusAmbientSounds.vue
 * FILE PATH: resources/js/Components/FocusAmbientSounds.vue
 *
 * ✅ Multiple sounds play at the same time (overlapping)
 * ✅ Per-sound volume slider — appears when that sound is ON
 * ✅ Import your own audio file (MP3, WAV, OGG) — loops forever
 */
import { ref, reactive, onUnmounted } from 'vue'

let ctx = null

const initCtx = async () => {
  if (ctx) return
  ctx = new (window.AudioContext || window.webkitAudioContext)()
  await ctx.resume()
}

// active[key] = { nodes: [], gainNode, level: 0.4, name: '' }
const active      = reactive({})
const customList  = ref([])   // user-imported sounds
const fileInput   = ref(null)

// ── GENERATORS ───────────────────────────────────────────────

const genBrownNoise = (dst) => {
  const sp = ctx.createScriptProcessor(4096, 1, 1)
  let last = 0
  sp.onaudioprocess = (e) => {
    const out = e.outputBuffer.getChannelData(0)
    for (let i = 0; i < 4096; i++) {
      const w = Math.random() * 2 - 1
      out[i] = (last + 0.02 * w) / 1.02
      last   = out[i]
      out[i] *= 3.5
    }
  }
  sp.connect(dst)
  return [sp]
}

const genRain = (dst) => {
  const len = ctx.sampleRate * 2
  const buf = ctx.createBuffer(1, len, ctx.sampleRate)
  const d   = buf.getChannelData(0)
  for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1
  const src = ctx.createBufferSource(); src.buffer = buf; src.loop = true
  const lp  = ctx.createBiquadFilter(); lp.type = 'lowpass';  lp.frequency.value = 700
  const hp  = ctx.createBiquadFilter(); hp.type = 'highpass'; hp.frequency.value = 120
  src.connect(lp); lp.connect(hp); hp.connect(dst); src.start()
  return [src, lp, hp]
}

const genBirds = (dst) => {
  const live = []
  let intervalId = null
  const chirp = (t) => {
    const o = ctx.createOscillator(); const g = ctx.createGain()
    o.connect(g); g.connect(dst)
    const f = 1200 + Math.random() * 800
    o.type = 'sine'
    o.frequency.setValueAtTime(f, t); o.frequency.exponentialRampToValueAtTime(f * 1.4, t + 0.08); o.frequency.exponentialRampToValueAtTime(f, t + 0.16)
    g.gain.setValueAtTime(0, t); g.gain.linearRampToValueAtTime(0.12, t + 0.04); g.gain.exponentialRampToValueAtTime(0.001, t + 0.25)
    o.start(t); o.stop(t + 0.3)
    live.push(o, g)
  }
  const sched = () => { const n = ctx.currentTime; for (let i = 0; i < 8; i++) chirp(n + Math.random() * 4) }
  sched()
  intervalId = setInterval(() => { if (!active['birds']) { clearInterval(intervalId); return }; sched() }, 3500)
  return [{ _iv: intervalId }, ...live]
}

const genPurr = (dst) => {
  const osc = ctx.createOscillator(); const lfo = ctx.createOscillator(); const lfoG = ctx.createGain()
  const g   = ctx.createGain(); const lp = ctx.createBiquadFilter()
  lfo.type = 'sine'; lfo.frequency.value = 25; lfoG.gain.value = 0.15
  lfo.connect(lfoG); lfoG.connect(g.gain)
  osc.type = 'sawtooth'; osc.frequency.value = 80; lp.type = 'lowpass'; lp.frequency.value = 200; g.gain.value = 0.2
  osc.connect(lp); lp.connect(g); g.connect(dst); osc.start(); lfo.start()
  return [osc, lfo, lfoG, g, lp]
}

const genLofi = (dst) => {
  const bpm = 70; const beat = 60 / bpm
  const sb = (t) => {
    const ko = ctx.createOscillator(); const kg = ctx.createGain()
    ko.type = 'sine'; ko.frequency.setValueAtTime(150, t); ko.frequency.exponentialRampToValueAtTime(50, t + 0.2)
    kg.gain.setValueAtTime(0.7, t); kg.gain.exponentialRampToValueAtTime(0.001, t + 0.3)
    ko.connect(kg); kg.connect(dst); ko.start(t); ko.stop(t + 0.35)
    const hb = ctx.createBuffer(1, ctx.sampleRate * 0.05, ctx.sampleRate)
    const hd = hb.getChannelData(0); for (let i = 0; i < hd.length; i++) hd[i] = Math.random() * 2 - 1
    const hs = ctx.createBufferSource(); const hf = ctx.createBiquadFilter(); const hg = ctx.createGain()
    hs.buffer = hb; hf.type = 'highpass'; hf.frequency.value = 8000
    hg.gain.setValueAtTime(0.2, t + beat * 0.5); hg.gain.exponentialRampToValueAtTime(0.001, t + beat * 0.5 + 0.05)
    hs.connect(hf); hf.connect(hg); hg.connect(dst); hs.start(t + beat * 0.5)
  }
  const n = ctx.currentTime; for (let i = 0; i < 8; i++) sb(n + i * beat)
  const iv = setInterval(() => { if (!active['lofi']) { clearInterval(iv); return }; const t = ctx.currentTime; for (let i = 0; i < 8; i++) sb(t + i * beat) }, beat * 8 * 1000 - 100)
  return [{ _iv: iv }]
}

// ── TOGGLE ONE SOUND (others keep playing) ───────────────────
const toggle = async (key) => {
  await initCtx()
  if (active[key]) {
    active[key].nodes.forEach(n => { if (n._iv) clearInterval(n._iv); try { n.stop?.(); n.disconnect?.() } catch {} })
    try { active[key].gainNode.disconnect() } catch {}
    delete active[key]
    return
  }
  const g = ctx.createGain(); g.gain.value = 0.4; g.connect(ctx.destination)
  const GENS = { brown: genBrownNoise, rain: genRain, birds: genBirds, purr: genPurr, lofi: genLofi }
  const nodes = GENS[key]?.(g) ?? []
  active[key] = { nodes, gainNode: g, level: 0.4 }
}

// ── PER-SOUND VOLUME ─────────────────────────────────────────
const setVol = (key, v) => {
  if (!active[key]) return
  active[key].level = parseFloat(v)
  active[key].gainNode.gain.setTargetAtTime(active[key].level, ctx.currentTime, 0.05)
}

// ── IMPORT YOUR OWN FILE ─────────────────────────────────────
const importFile = async (e) => {
  const file = e.target.files[0]; if (!file) return
  await initCtx()
  try {
    const ab  = await file.arrayBuffer()
    const buf = await ctx.decodeAudioData(ab)
    const key = 'custom_' + Date.now()
    const g   = ctx.createGain(); g.gain.value = 0.4; g.connect(ctx.destination)
    const src = ctx.createBufferSource(); src.buffer = buf; src.loop = true
    src.connect(g); src.start()
    active[key] = { nodes: [src], gainNode: g, level: 0.4 }
    customList.value.push({ key, label: file.name.replace(/\.[^.]+$/, '') })
  } catch { alert('Could not play this file. Try MP3 or WAV.') }
  e.target.value = ''
}

const stopCustom = (key) => {
  if (!active[key]) return
  active[key].nodes.forEach(n => { try { n.stop?.(); n.disconnect?.() } catch {} })
  try { active[key].gainNode.disconnect() } catch {}
  delete active[key]
  customList.value = customList.value.filter(s => s.key !== key)
}

onUnmounted(() => {
  Object.keys(active).forEach(k => {
    active[k].nodes.forEach(n => { if (n._iv) clearInterval(n._iv); try { n.stop?.(); n.disconnect?.() } catch {} })
    try { active[k].gainNode.disconnect() } catch {}
  })
})

const SOUNDS = [
  { key: 'rain',  label: 'Rain',        emoji: '🌧️' },
  { key: 'birds', label: 'Birds',       emoji: '🐦' },
  { key: 'purr',  label: 'Cat Purr',    emoji: '🐱' },
  { key: 'brown', label: 'Brown Noise', emoji: '🌊' },
  { key: 'lofi',  label: 'Lo-Fi',       emoji: '🎵' },
]
</script>

<template>
  <div class="bg-white/40 dark:bg-black/30 backdrop-blur-xl rounded-3xl p-4 border border-white/30 dark:border-white/5 space-y-2.5">
    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3">🎧 Sounds — mix any combo</p>

    <!-- Built-in sounds -->
    <div v-for="s in SOUNDS" :key="s.key" class="space-y-1.5">
      <div class="flex items-center gap-3">
        <button @click="toggle(s.key)"
          class="flex items-center gap-2 px-3 py-2 rounded-2xl font-bold text-xs transition-all active:scale-90 w-[115px] shrink-0"
          :class="active[s.key] ? 'bg-teal-500 text-white shadow-md shadow-teal-500/25' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 hover:bg-slate-200'">
          <div class="flex items-end gap-px h-3 w-5">
            <template v-if="active[s.key]">
              <div v-for="n in 3" :key="n" class="w-1 bg-white rounded-full animate-bounce"
                :style="`height:${[50,100,35][n-1]}%;animation-delay:${n*0.12}s;animation-duration:0.55s`"/>
            </template>
            <span v-else class="text-sm leading-none">{{ s.emoji }}</span>
          </div>
          {{ s.label }}
        </button>

        <!-- Volume — only shows when ON -->
        <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100">
          <div v-if="active[s.key]" class="flex-1 flex items-center gap-1.5">
            <span class="text-[9px] text-slate-400 shrink-0">🔈</span>
            <input type="range" min="0" max="1" step="0.02" :value="active[s.key].level"
              @input="setVol(s.key, $event.target.value)"
              class="flex-1 h-1 rounded-full accent-teal-500 cursor-pointer" />
            <span class="text-[9px] text-slate-400 shrink-0">🔊</span>
          </div>
        </Transition>
      </div>
    </div>

    <!-- User imported sounds -->
    <div v-if="customList.length" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-slate-800">
      <p class="text-[9px] font-black uppercase tracking-widest text-slate-400">My Files</p>
      <div v-for="s in customList" :key="s.key" class="flex items-center gap-2">
        <span class="text-xs font-bold text-slate-600 dark:text-slate-300 truncate flex-1 bg-indigo-50 dark:bg-indigo-500/10 px-3 py-1.5 rounded-xl">
          🎧 {{ s.label }}
        </span>
        <div v-if="active[s.key]" class="flex-1 flex items-center gap-1">
          <input type="range" min="0" max="1" step="0.02" :value="active[s.key].level"
            @input="setVol(s.key, $event.target.value)"
            class="flex-1 h-1 rounded-full accent-indigo-500 cursor-pointer" />
        </div>
        <button @click="stopCustom(s.key)" class="text-slate-400 hover:text-red-500 transition text-lg leading-none shrink-0">&times;</button>
      </div>
    </div>

    <!-- Import -->
    <button @click="fileInput?.click()"
      class="w-full py-2.5 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 text-slate-400 hover:border-teal-400 hover:text-teal-500 font-bold text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2 mt-1">
      📁 Import Audio File
    </button>
    <input ref="fileInput" type="file" accept="audio/*" @change="importFile" class="hidden" />
    <p class="text-[9px] text-center text-slate-400">MP3, WAV, OGG · Loops forever · Mixes with other sounds</p>
  </div>
</template>