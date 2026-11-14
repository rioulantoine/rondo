<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ChevronLeft, ChevronRight, Plus, Send, Scale } from 'lucide-vue-next'
import { api, initials } from '../api'
import { todayParis } from '../format'
import { toast } from '../toast'

const SPACES = { cuisine: 'Cuisine', salle: 'Salle', bar: 'Bar' }
const d = ref(null)
const error = ref('')
const busy = ref(false)
const form = ref(null) // créneau en cours d'édition
const week = ref(todayParis())

async function load() {
  error.value = ''
  try {
    d.value = await api(`/planning?semaine=${week.value}`)
    week.value = d.value.week
  } catch (e) {
    error.value = e.message
  }
}
onMounted(load)

function shiftWeek(delta) {
  const t = new Date(week.value + 'T12:00:00Z')
  t.setUTCDate(t.getUTCDate() + 7 * delta)
  week.value = t.toISOString().slice(0, 10)
  form.value = null
  load()
}

const dayLabel = (date) => new Date(date + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })
const weekLabel = computed(() => d.value && `Semaine du ${new Date(d.value.week + 'T12:00:00').toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`)
const byDay = computed(() => (d.value?.days || []).map((date) => ({ date, shifts: d.value.shifts.filter((s) => s.date === date) })))
const hours = computed(() => {
  const out = {}
  for (const s of d.value?.shifts || []) {
    const [a, b] = [s.start, s.end].map((t) => +t.slice(0, 2) * 60 + +t.slice(3))
    out[s.staff] = (out[s.staff] || 0) + (b > a ? b - a : b + 1440 - a)
  }
  return out
})
const fmtH = (min) => `${Math.floor(min / 60)} h${min % 60 ? String(min % 60).padStart(2, '0') : ''}`

function edit(s) {
  if (!d.value.editor) return
  form.value = reactive({ ...s, fromDate: s.date })
}
function add(date) {
  form.value = reactive({ id: null, staff: d.value.team[0]?.name || '', date, start: '09:00', end: '15:00', space: '', note: '' })
}

async function save() {
  busy.value = true
  try {
    const f = form.value
    if (f.id) await api(`/planning/creneaux/${f.id}`, { method: 'PUT', body: f })
    else await api('/planning/creneaux', { method: 'POST', body: f })
    form.value = null
    await load()
    toast('Créneau enregistré.')
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}
async function remove() {
  busy.value = true
  try {
    await api(`/planning/creneaux/${form.value.id}?date=${form.value.fromDate}`, { method: 'DELETE' })
    form.value = null
    await load()
    toast('Créneau supprimé.')
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}
async function publish() {
  busy.value = true
  try {
    d.value.status = (await api(`/planning/publication?semaine=${d.value.week}`, { method: 'POST' })).status
    toast("Planning publié. L'équipe le voit dans son espace.")
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="stack">
    <div class="head">
      <h1>{{ d && !d.editor ? 'Mon planning' : 'Planning' }}</h1>
      <div class="week-nav">
        <button class="btn btn-ghost icon" aria-label="Semaine précédente" @click="shiftWeek(-1)"><ChevronLeft :size="20" :stroke-width="1.7" /></button>
        <span class="wk">{{ weekLabel }}</span>
        <button class="btn btn-ghost icon" aria-label="Semaine suivante" @click="shiftWeek(1)"><ChevronRight :size="20" :stroke-width="1.7" /></button>
      </div>
    </div>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>

    <template v-if="d">
      <div v-if="d.editor" class="pub">
        <span v-if="!d.status.published" class="state state-warn">Brouillon, pas encore publié</span>
        <span v-else-if="!d.status.upToDate" class="state state-warn">Modifié depuis la publication</span>
        <span v-else class="state state-ok">Publié · à jour</span>
        <button v-if="!d.status.upToDate" class="btn btn-primary small" :disabled="busy" @click="publish"><Send :size="18" :stroke-width="1.7" /> Publier la semaine</button>
      </div>
      <p v-else-if="!d.status.published" class="muted">Cette semaine n'est pas encore publiée.</p>

      <ul v-if="d.editor && d.alerts.length" class="panel legal">
        <li v-for="(a, i) in d.alerts" :key="i"><Scale :size="18" :stroke-width="1.7" /> {{ a.message }}</li>
      </ul>

      <!-- formulaire de créneau -->
      <form v-if="form" class="panel pad stack" @submit.prevent="save">
        <h2>{{ form.id ? 'Modifier le créneau' : 'Nouveau créneau' }}</h2>
        <div class="grid2">
          <label class="field"><span class="label">Personne</span>
            <select v-model="form.staff" class="input"><option v-for="m in d.team" :key="m.id">{{ m.name }}</option></select>
          </label>
          <label class="field"><span class="label">Jour</span><input v-model="form.date" type="date" class="input data" /></label>
          <label class="field"><span class="label">Début</span><input v-model="form.start" type="time" class="input data" /></label>
          <label class="field"><span class="label">Fin</span><input v-model="form.end" type="time" class="input data" /></label>
          <label class="field"><span class="label">Espace</span>
            <select v-model="form.space" class="input"><option value="">—</option><option v-for="(l, k) in SPACES" :key="k" :value="k">{{ l }}</option></select>
          </label>
          <label class="field"><span class="label">Note</span><input v-model="form.note" class="input" maxlength="200" /></label>
        </div>
        <div class="row-btns">
          <button v-if="form.id" type="button" class="btn btn-danger" :disabled="busy" @click="remove">Supprimer</button>
          <span class="grow" />
          <button type="button" class="btn btn-secondary" @click="form = null">Annuler</button>
          <button class="btn btn-primary" :disabled="busy">Enregistrer</button>
        </div>
      </form>

      <div class="days">
        <section v-for="day in byDay" :key="day.date" class="day" :class="{ today: day.date === todayParis() }">
          <h2 class="day-h">{{ dayLabel(day.date) }}</h2>
          <ul class="shifts">
            <li v-for="s in day.shifts" :key="s.id">
              <component :is="d.editor ? 'button' : 'div'" class="shift" @click="edit(s)">
                <span class="data t">{{ s.start }} – {{ s.end }}</span>
                <span class="who"><span class="visa sm">{{ initials(s.staff) }}</span>{{ s.staff }}</span>
                <span v-if="s.space || s.note" class="caption">{{ [SPACES[s.space], s.note].filter(Boolean).join(' · ') }}</span>
              </component>
            </li>
          </ul>
          <p v-if="!day.shifts.length && !d.editor" class="caption rest">Repos</p>
          <button v-if="d.editor" class="btn btn-ghost add" @click="add(day.date)"><Plus :size="18" :stroke-width="1.7" /> Ajouter</button>
        </section>
      </div>

      <section v-if="d.editor" class="panel pad">
        <h2 class="sub">Heures planifiées</h2>
        <ul class="register">
          <li v-for="m in d.team" :key="m.id" class="tot">
            <span class="grow">{{ m.name }}</span>
            <span class="data" :class="{ over: m.weeklyHours && (hours[m.name] || 0) > m.weeklyHours * 60 }">{{ fmtH(hours[m.name] || 0) }}</span>
            <span v-if="m.weeklyHours" class="caption data">/ {{ m.weeklyHours }} h</span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; }
.week-nav { display: flex; align-items: center; gap: 4px; }
.wk { font-weight: 500; min-width: 11em; text-align: center; }
.icon { width: 44px; padding: 0; }
.pub { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.small { min-height: 44px; font-size: 15px; }
.legal { list-style: none; margin: 0; padding: 8px 16px; background: var(--warn-bg); border-color: transparent; color: var(--warn); }
.legal li { display: flex; gap: 8px; align-items: flex-start; padding: 6px 0; font-size: 14px; line-height: 20px; }
.legal svg { flex: none; margin-top: 1px; }
.pad { padding: 16px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.row-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.grow { flex: 1; }

.days { display: grid; gap: 12px; }
.day { border: 1px solid var(--line); border-radius: var(--r-l); padding: 12px; background: var(--sand); }
.day.today { border-color: var(--sea-deep); }
.day-h { font-size: 15px; line-height: 20px; margin-bottom: 8px; }
.day-h::first-letter { text-transform: uppercase; }
.today .day-h { color: var(--accent-deep); }
.shifts { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.shift { width: 100%; display: grid; gap: 4px; padding: 10px 12px; text-align: left; color: var(--ink); background: var(--sand-dark); border: 1px solid transparent; border-radius: var(--r-m); }
button.shift:active { background: var(--tint); }
@media (hover: hover) and (pointer: fine) { button.shift:hover { border-color: var(--line); } }
.t { font-weight: 500; font-size: 14px; white-space: nowrap; }
.who { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.visa.sm { width: 24px; height: 24px; font-size: 10px; }
.add { width: 100%; min-height: 40px; margin-top: 6px; font-size: 14px; font-weight: 500; color: var(--ink-soft); }
.rest { padding: 2px 0; }
.sub { font-size: 16px; margin-bottom: 4px; }
.tot { display: flex; gap: 8px; align-items: baseline; padding: 10px 0; }
.over { color: var(--alert); }
@media (min-width: 1100px) {
  .days { grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
  .day { padding: 10px 8px; }
}
@media (max-width: 480px) { .grid2 { grid-template-columns: 1fr; } }
</style>
