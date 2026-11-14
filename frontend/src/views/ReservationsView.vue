<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ChevronLeft, ChevronRight, Plus, Link2, Users } from 'lucide-vue-next'
import { api, session } from '../api'
import { todayParis } from '../format'
import { toast } from '../toast'

const d = ref(null)
const error = ref('')
const date = ref(todayParis())
const form = ref(null)
const busy = ref(false)
const publicUrl = computed(() => `${location.origin}/r/${session.restaurant.code}`)
const STATE = { confirmee: 'neutral', arrivee: 'ok', noshow: 'alert', annulee: 'warn' }

async function load() {
  try {
    d.value = await api(`/reservations?date=${date.value}`)
    if (d.value.unseen) api('/reservations/vues', { method: 'POST' }).catch(() => {})
  } catch (e) {
    error.value = e.message
  }
}
onMounted(load)

function shiftDay(n) {
  const t = new Date(date.value + 'T12:00:00Z')
  t.setUTCDate(t.getUTCDate() + n)
  date.value = t.toISOString().slice(0, 10)
  load()
}
const dayLabel = computed(() => new Date(date.value + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))
const active = computed(() => (d.value?.reservations || []).filter((r) => r.status !== 'annulee'))
const covers = computed(() => active.value.reduce((n, r) => n + r.party, 0))

async function setStatus(r, status) {
  const before = r.status
  r.status = status
  try {
    await api(`/reservations/${r.id}`, { method: 'PATCH', body: { status } })
  } catch (e) {
    r.status = before
    toast(e.message, 'alert')
  }
}

async function addManual() {
  busy.value = true
  try {
    await api('/reservations', { method: 'POST', body: { ...form.value, date: date.value } })
    form.value = null
    toast('Réservation ajoutée.')
    load()
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}
async function copyLink() {
  try {
    await navigator.clipboard.writeText(publicUrl.value)
    toast('Lien de réservation copié.')
  } catch {
    toast(publicUrl.value)
  }
}
</script>

<template>
  <div class="stack">
    <div class="head">
      <h1>Réservations</h1>
      <button class="btn btn-secondary" @click="copyLink"><Link2 :size="18" :stroke-width="1.7" /> Lien client</button>
    </div>
    <div class="day-nav">
      <button class="btn btn-ghost icon" aria-label="Jour précédent" @click="shiftDay(-1)"><ChevronLeft :size="20" :stroke-width="1.7" /></button>
      <span class="day">{{ dayLabel }}</span>
      <button class="btn btn-ghost icon" aria-label="Jour suivant" @click="shiftDay(1)"><ChevronRight :size="20" :stroke-width="1.7" /></button>
    </div>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>

    <template v-if="d">
      <div class="sum">
        <span><Users :size="18" :stroke-width="1.7" /> <span class="data">{{ covers }}</span> couverts · <span class="data">{{ active.length }}</span> tables</span>
        <button v-if="!form" class="btn btn-primary small" @click="form = reactive({ slot: d.slots[0], party: 2, firstName: '', lastName: '', phone: '', notes: '' })"><Plus :size="18" :stroke-width="1.7" /> Ajouter</button>
      </div>

      <form v-if="form" class="panel pad stack" @submit.prevent="addManual">
        <h2>Réservation au téléphone</h2>
        <div class="grid">
          <label class="field"><span class="label">Heure</span><select v-model="form.slot" class="input data"><option v-for="s in d.slots" :key="s">{{ s }}</option></select></label>
          <label class="field"><span class="label">Personnes</span><input v-model.number="form.party" type="number" min="1" max="60" class="input data" /></label>
          <label class="field"><span class="label">Prénom</span><input v-model="form.firstName" class="input" required /></label>
          <label class="field"><span class="label">Nom</span><input v-model="form.lastName" class="input" required /></label>
          <label class="field"><span class="label">Téléphone</span><input v-model="form.phone" type="tel" class="input" required /></label>
          <label class="field"><span class="label">Remarque</span><input v-model="form.notes" class="input" /></label>
        </div>
        <div class="row-btns">
          <span class="grow" />
          <button type="button" class="btn btn-secondary" @click="form = null">Annuler</button>
          <button class="btn btn-primary" :disabled="busy">Enregistrer</button>
        </div>
      </form>

      <ul class="register panel list">
        <li v-for="r in d.reservations" :key="r.id" class="resa" :class="{ off: r.status === 'annulee' }">
          <span class="data t">{{ r.slot }}</span>
          <span class="grow txt">
            <span class="name">{{ r.name }} <span class="data caption">· {{ r.party }} pers.</span></span>
            <span class="caption">{{ [r.phone, r.notes].filter(Boolean).join(' · ') }}</span>
            <span class="state" :class="`state-${STATE[r.status]}`">{{ d.statuses[r.status] }}</span>
          </span>
          <span v-if="r.status === 'confirmee'" class="acts">
            <button class="btn btn-secondary small" @click="setStatus(r, 'arrivee')">Arrivée</button>
            <button class="btn btn-ghost small" @click="setStatus(r, 'noshow')">No-show</button>
          </span>
          <button v-else class="btn btn-ghost small" @click="setStatus(r, 'confirmee')">Rétablir</button>
        </li>
        <li v-if="!d.reservations.length" class="muted empty">Aucune réservation ce jour-là. Partage le lien client pour en recevoir en ligne.</li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.day-nav { display: flex; align-items: center; gap: 4px; }
.day { flex: 1; text-align: center; font-weight: 600; }
.day::first-letter { text-transform: uppercase; }
.icon { width: 44px; padding: 0; }
.sum { display: flex; justify-content: space-between; align-items: center; color: var(--ink-soft); }
.sum > span { display: flex; align-items: center; gap: 6px; }
.small { min-height: 44px; font-size: 14px; padding: 0 12px; }
.pad { padding: 16px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.row-btns { display: flex; gap: 10px; }
.grow { flex: 1; min-width: 0; }
.list > li { padding: 0 16px; }
.resa { display: flex; align-items: center; gap: 14px; padding: 12px 0 !important; flex-wrap: wrap; }
.resa.off .name { text-decoration: line-through; color: var(--ink-soft); }
.t { font-size: 18px; font-weight: 500; width: 3.2em; }
.txt { display: grid; gap: 2px; }
.txt .state { justify-self: start; margin-top: 4px; }
.name { font-weight: 600; }
.acts { display: flex; gap: 4px; }
.empty { padding: 16px; }
@media (max-width: 560px) { .grid { grid-template-columns: 1fr; } .acts { width: 100%; padding-left: calc(3.2em + 14px); } }
</style>
