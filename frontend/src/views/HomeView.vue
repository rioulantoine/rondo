<script setup>
import { ref, computed, onMounted } from 'vue'
import { ChevronRight, TriangleAlert, Megaphone, CookingPot, UtensilsCrossed, Wine, CalendarDays, Check, BookMarked } from 'lucide-vue-next'
import { api, session, can } from '../api'
import { num, time, day, dayLong, dlcState } from '../format'
import { toast } from '../toast'

const ICONS = { cuisine: CookingPot, salle: UtensilsCrossed, bar: Wine }
const BLURB = { cuisine: 'Relevés, nettoyage, DLC', salle: 'Mise en place, buffet, DLC', bar: 'Frigos, glaçons, nettoyage' }
const TARGET = { tous: 'Tout le restaurant', cuisine: 'Cuisine', salle: 'Salle', bar: 'Bar' }

const d = ref(null)
const error = ref('')
const firstName = computed(() => (session.person.owner ? '' : ' ' + session.person.name.split(' ')[0]))

async function load() {
  try {
    d.value = await api('/accueil')
  } catch (e) {
    error.value = e.message
  }
}
onMounted(load)

const badge = computed(() => {
  const me = d.value?.me
  if (!me || !me.canBadgeHere) return null
  if (me.status === 'absent') return 'Pointer mon arrivée'
  if (me.status === 'present' || me.status === 'pause') return 'Pointer mon départ'
  return null
})

const todo = computed(() => {
  if (!d.value) return []
  const out = []
  for (const s of d.value.spaces) {
    for (const t of s.tempAlerts) out.push({ key: t.id, sp: s.space, cls: 'alert', title: `${t.equipmentName} hors bornes`, sub: `${s.label} · ${num(t.value)} °C à ${time(t.ts)}` })
    for (const a of s.dlcAlerts) {
      const st = dlcState(a.daysLeft)
      out.push({ key: a.entry.id, sp: s.space, cls: st.cls, title: `${a.entry.product} · ${st.label}`, sub: `${s.label} · DLC ${day(a.entry.date)}` })
    }
  }
  return out.sort((a, b) => (a.cls === 'alert' ? 0 : 1) - (b.cls === 'alert' ? 0 : 1))
})

async function validate(e) {
  try {
    const m = new Date(e.ts).toLocaleDateString('sv-SE', { timeZone: 'Europe/Paris' }).slice(0, 7)
    await api(`/pointage/${e.id}/validation?month=${m}`, { method: 'POST' })
    d.value.owner.pendingBadges = d.value.owner.pendingBadges.filter((x) => x.id !== e.id)
    toast(`Arrivée de ${e.name} validée.`)
  } catch (err) {
    toast(err.message, 'alert')
  }
}
</script>

<template>
  <div class="stack home">
    <div class="hello">
      <p class="caption date">{{ dayLong() }}</p>
      <h1>Bonjour{{ firstName }}</h1>
      <p v-if="d?.me?.today.length" class="muted">Tu es de service <span class="data">{{ d.me.today.map((s) => `${s.start} – ${s.end || '…'}`).join(', ') }}</span></p>
    </div>

    <RouterLink v-if="badge" to="/pointer" class="btn btn-primary btn-block big">{{ badge }}</RouterLink>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <div v-if="!d && !error" class="skeleton sk" aria-busy="true" />

    <template v-if="d">
      <!-- messages du responsable -->
      <ul v-if="d.alerts.length" class="register panel pad rise-list">
        <li v-for="a in d.alerts" :key="a.id" class="row">
          <Megaphone :size="20" :stroke-width="1.7" class="ico-info" />
          <span class="grow"><span class="title">{{ a.message }}</span><span class="caption">{{ TARGET[a.target] || a.target }} · {{ a.createdBy }}</span></span>
        </li>
      </ul>

      <!-- à traiter -->
      <ul v-if="todo.length" class="register panel pad rise-list">
        <li v-for="t in todo" :key="t.key">
          <RouterLink :to="`/registres/${t.sp}`" class="row link">
            <TriangleAlert :size="20" :stroke-width="1.7" :class="`ico-${t.cls}`" />
            <span class="grow"><span class="title">{{ t.title }}</span><span class="caption">{{ t.sub }}</span></span>
            <ChevronRight :size="18" :stroke-width="1.7" class="chev" />
          </RouterLink>
        </li>
      </ul>

      <!-- pointages à valider (responsable) -->
      <section v-if="d.owner?.pendingBadges.length" class="panel pad">
        <h2 class="sub">Arrivées hors planning à valider</h2>
        <ul class="register">
          <li v-for="e in d.owner.pendingBadges" :key="e.id" class="row">
            <span class="grow"><span class="title">{{ e.name }} · <span class="data">{{ day(e.ts) }} {{ time(e.ts) }}</span></span><span class="caption">{{ e.justification }}</span></span>
            <button class="btn btn-secondary small" @click="validate(e)">Valider</button>
          </li>
        </ul>
      </section>

      <p v-if="!todo.length && !d.owner?.pendingBadges.length" class="state state-ok calm"><Check :size="16" :stroke-width="2" /> Rien à traiter pour l'instant.</p>

      <!-- espaces -->
      <ul class="register panel pad rise-list">
        <li v-for="s in d.spaces" :key="s.space">
          <RouterLink :to="`/registres/${s.space}`" class="row link space">
            <span class="ico"><component :is="ICONS[s.space]" :size="22" :stroke-width="1.7" /></span>
            <span class="grow"><span class="title">{{ s.label }}</span><span class="caption">{{ BLURB[s.space] }}</span></span>
            <span class="data count" :class="{ done: s.tasksDone === s.tasksTotal }">{{ s.tasksDone }} / {{ s.tasksTotal }}</span>
          </RouterLink>
        </li>
        <li v-if="can('reservation')">
          <RouterLink to="/reservations" class="row link space">
            <span class="ico"><BookMarked :size="22" :stroke-width="1.7" /></span>
            <span class="grow"><span class="title">Réservations</span><span class="caption">Service du jour, ajout au téléphone</span></span>
            <ChevronRight :size="18" :stroke-width="1.7" class="chev" />
          </RouterLink>
        </li>
      </ul>

      <section v-if="d.me" class="panel pad plan">
        <CalendarDays :size="22" :stroke-width="1.7" class="ico-plan" />
        <span class="grow">
          <span class="title">Planning</span>
          <span v-if="d.me.next" class="caption">Prochain service : <span class="data">{{ dayLong(new Date(d.me.next.date + 'T12:00:00')) }}, {{ d.me.next.start }} – {{ d.me.next.end }}</span></span>
          <span v-else class="caption">Aucun autre service publié pour les deux semaines à venir.</span>
        </span>
      </section>
    </template>
  </div>
</template>

<style scoped>
.hello { display: grid; gap: 4px; }
.date::first-letter { text-transform: uppercase; }
.big { min-height: 56px; font-size: 17px; }
.sk { height: 280px; }
.pad { padding: 0 16px; }
.row { display: flex; align-items: center; gap: 12px; min-height: 64px; padding: 10px 0; }
.link { color: var(--ink); text-decoration: none; }
.grow { flex: 1; min-width: 0; display: grid; gap: 2px; }
.title { font-weight: 500; }
.chev { color: var(--ink-soft); flex: none; }
.ico-alert { color: var(--alert); flex: none; }
.ico-warn { color: var(--warn); flex: none; }
.ico-info { color: var(--accent-deep); flex: none; }
.calm { justify-self: start; }
.sub { font-size: 16px; padding-top: 14px; }
.small { min-height: 44px; font-size: 14px; padding: 0 14px; }
.ico { width: 44px; height: 44px; border-radius: var(--r-m); background: var(--mist); color: var(--sea-deep); display: grid; place-items: center; flex: none; }
.space .title { font-weight: 600; }
.count { color: var(--ink-soft); }
.count.done { color: var(--ok); }
.plan { display: flex; gap: 12px; align-items: center; padding: 14px 16px; }
.ico-plan { color: var(--sea-deep); flex: none; }
</style>
