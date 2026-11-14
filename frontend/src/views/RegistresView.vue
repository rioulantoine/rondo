<script setup>
import { ref, onMounted } from 'vue'
import { ChevronRight, CookingPot, Wine, UtensilsCrossed } from 'lucide-vue-next'
import { api } from '../api'

const ICONS = { cuisine: CookingPot, salle: UtensilsCrossed, bar: Wine }
const spaces = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    spaces.value = await api('/registres')
  } catch (e) {
    error.value = e.message
  }
})

function alertLine(s) {
  const n = s.tempAlerts.length + s.dlcAlerts.filter((d) => d.daysLeft <= 0).length
  if (n) return { cls: 'alert', text: `${n} à traiter` }
  if (s.dlcAlerts.length) return { cls: 'warn', text: `${s.dlcAlerts.length} DLC proche${s.dlcAlerts.length > 1 ? 's' : ''}` }
  return null
}
</script>

<template>
  <div class="stack">
    <h1>Registres</h1>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <ul class="register spaces panel" :aria-busy="!spaces">
      <template v-if="!spaces"><li v-for="i in 3" :key="i" class="sk" aria-hidden="true" /></template>
      <li v-for="s in spaces" :key="s.space">
        <RouterLink :to="`/registres/${s.space}`" class="row">
          <span class="ico"><component :is="ICONS[s.space]" :size="22" :stroke-width="1.7" /></span>
          <span class="txt">
            <span class="name">{{ s.label }}</span>
            <span class="caption">{{ s.tempCount }} relevé{{ s.tempCount > 1 ? 's' : '' }} aujourd'hui</span>
            <span v-if="alertLine(s)" class="state" :class="`state-${alertLine(s).cls}`">{{ alertLine(s).text }}</span>
          </span>
          <span class="data count" :class="{ done: s.tasksDone === s.tasksTotal }">{{ s.tasksDone }} / {{ s.tasksTotal }}</span>
          <ChevronRight :size="20" :stroke-width="1.7" class="chev" />
        </RouterLink>
      </li>
    </ul>
    <p v-if="spaces && !spaces.length" class="muted">Aucun espace ne t'est ouvert. Demande au responsable de t'en donner l'accès.</p>
  </div>
</template>

<style scoped>
.spaces > li { padding: 0 16px; }
.row { display: flex; align-items: center; gap: 14px; min-height: 76px; color: var(--ink); text-decoration: none; }
.ico { width: 44px; height: 44px; border-radius: var(--r-m); background: var(--mist); color: var(--sea-deep); display: grid; place-items: center; flex: none; }
.txt { flex: 1; display: grid; min-width: 0; }
.name { font-weight: 600; }
.txt .state { justify-self: start; margin-top: 4px; }
.count { color: var(--ink-soft); }
.count.done { color: var(--ok); }
.chev { color: var(--ink-soft); flex: none; }
.sk { height: 76px; margin: 0 -16px; background: linear-gradient(90deg, var(--sand) 0, var(--sand-dark) 50%, var(--sand) 100%); }
</style>
