<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ChevronRight, CookingPot, Wine, UtensilsCrossed, FileDown } from 'lucide-vue-next'
import { api, session } from '../api'
import { todayParis } from '../format'
import { toast } from '../toast'

const ICONS = { cuisine: CookingPot, salle: UtensilsCrossed, bar: Wine }
const spaces = ref(null)
const error = ref('')
const report = reactive({ du: todayParis().slice(0, 8) + '01', au: todayParis(), busy: false })

async function exportReport() {
  report.busy = true
  try {
    const [data, { haccpReport }] = await Promise.all([api(`/rapport-haccp?du=${report.du}&au=${report.au}`), import('../report.js')])
    haccpReport(data)
    toast('Rapport PDF téléchargé.')
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    report.busy = false
  }
}

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
    <section v-if="session.person.owner" class="panel report">
      <h2>Rapport pour le contrôle sanitaire</h2>
      <p class="caption">Cuisine, salle et bar réunis : relevés numérotés, traçabilité, nettoyage. Trois mois au plus.</p>
      <div class="dates">
        <label class="field"><span class="label">Du</span><input v-model="report.du" type="date" class="input data" /></label>
        <label class="field"><span class="label">Au</span><input v-model="report.au" type="date" class="input data" /></label>
      </div>
      <button class="btn btn-secondary" :disabled="report.busy" @click="exportReport"><FileDown :size="20" :stroke-width="1.7" /> {{ report.busy ? 'Préparation…' : 'Télécharger le PDF' }}</button>
    </section>
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
.report { padding: 16px; display: grid; gap: 12px; justify-items: start; }
.dates { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; max-width: 420px; }
.count.done { color: var(--ok); }
.chev { color: var(--ink-soft); flex: none; }
.sk { height: 76px; margin: 0 -16px; background: linear-gradient(90deg, var(--sand) 0, var(--sand-dark) 50%, var(--sand) 100%); }
</style>
