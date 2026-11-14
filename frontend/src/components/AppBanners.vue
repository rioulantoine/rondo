<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { WifiOff, RefreshCw } from 'lucide-vue-next'

const offline = ref(!navigator.onLine)
const waiting = ref(null) // service worker de la nouvelle version, en attente
const sync = () => (offline.value = !navigator.onLine)

onMounted(async () => {
  window.addEventListener('online', sync)
  window.addEventListener('offline', sync)
  if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return
  const reg = await navigator.serviceWorker.register('/sw.js').catch(() => null)
  if (!reg) return
  const watch = (sw) => sw?.addEventListener('statechange', () => {
    if (sw.state === 'installed' && navigator.serviceWorker.controller) waiting.value = sw
  })
  if (reg.waiting && navigator.serviceWorker.controller) waiting.value = reg.waiting
  reg.addEventListener('updatefound', () => watch(reg.installing))
  let reloaded = false
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (!reloaded) { reloaded = true; location.reload() }
  })
})
onBeforeUnmount(() => {
  window.removeEventListener('online', sync)
  window.removeEventListener('offline', sync)
})
</script>

<template>
  <div v-if="offline" class="banner warn" role="status"><WifiOff :size="18" :stroke-width="1.7" /> Pas de réseau. Ce que tu saisis ne sera pas enregistré tant que la connexion n'est pas revenue.</div>
  <div v-else-if="waiting" class="banner info" role="status">
    <RefreshCw :size="18" :stroke-width="1.7" /> Nouvelle version de Rondo disponible.
    <button class="btn btn-primary small" @click="waiting.postMessage('SKIP_WAITING')">Mettre à jour</button>
  </div>
</template>

<style scoped>
.banner { position: sticky; top: 0; z-index: 30; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: calc(8px + env(safe-area-inset-top)) 16px 8px; font-size: 14px; line-height: 20px; font-weight: 500; }
.banner svg { flex: none; }
.warn { background: var(--warn-bg); color: var(--warn); }
.info { background: var(--tint); color: var(--accent-deep); }
.small { min-height: 36px; padding: 0 12px; font-size: 14px; margin-left: auto; }
</style>
