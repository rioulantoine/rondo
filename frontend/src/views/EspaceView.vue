<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Thermometer, ListChecks, Tag, TriangleAlert, Check, Plus } from 'lucide-vue-next'
import { api, session, initials } from '../api'
import { num, time, day, monthLabel, shiftMonth, dlcState, daysUntil } from '../format'
import { toast } from '../toast'
import RegisterEntry from '../components/RegisterEntry.vue'

const route = useRoute()
const router = useRouter()
const sp = computed(() => route.params.sp)
const TABS = [
  { id: 'jour', label: 'Jour' },
  { id: 'temp', label: 'Températures' },
  { id: 'taches', label: 'Tâches' },
  { id: 'trace', label: 'Traçabilité' },
]
const tab = computed(() => (TABS.some((t) => t.id === route.query.onglet) ? route.query.onglet : 'jour'))
const setTab = (id) => router.replace({ query: { ...route.query, onglet: id } })

const d = ref(null)
const error = ref('')
const month = ref(null)
const busy = ref(false)

async function load() {
  error.value = ''
  try {
    d.value = await api(`/registres/${sp.value}${month.value ? `?month=${month.value}` : ''}`)
    month.value = d.value.month
  } catch (e) {
    error.value = e.message
  }
}
watch(sp, () => { month.value = null; load() }, { immediate: true })
const isCurrentMonth = computed(() => d.value && d.value.month === d.value.today.slice(0, 7))
function goMonth(delta) {
  month.value = shiftMonth(month.value, delta)
  load()
}

// ---------- températures ----------
const openEq = ref(null)
const tf = reactive({ value: '', negative: false, comment: '', period: '' })
const tempsToday = computed(() => (d.value?.temps || []).map((e, i) => ({ ...e, n: i + 1 })).filter((e) => e.ts && new Date(e.ts).toLocaleDateString('sv-SE', { timeZone: 'Europe/Paris' }) === d.value.today))
const monthTemps = computed(() => (d.value?.temps || []).map((e, i) => ({ ...e, n: i + 1 })).reverse())
const lastToday = (eqId) => [...tempsToday.value].reverse().find((e) => e.equipmentId === eqId)

function openTemp(eq) {
  if (openEq.value === eq.id) { openEq.value = null; return }
  openEq.value = eq.id
  Object.assign(tf, { value: '', negative: eq.max < 0, comment: '', period: d.value.currentPeriod })
}
const tfValue = computed(() => {
  const v = parseFloat(String(tf.value).replace(',', '.'))
  return Number.isNaN(v) ? null : tf.negative ? -Math.abs(v) : v
})
const tfOut = (eq) => tfValue.value !== null && (tfValue.value < eq.min || tfValue.value > eq.max)

async function saveTemp(eq) {
  busy.value = true
  try {
    const r = await api(`/registres/${sp.value}/temperatures`, { method: 'POST', body: { equipmentId: eq.id, value: tfValue.value, comment: tf.comment, period: tf.period } })
    d.value.temps.push(r.entry)
    openEq.value = null
    toast(r.entry.ok ? `Relevé n° ${String(r.number).padStart(4, '0')} enregistré.` : `${eq.name} à ${num(r.entry.value)} °C : relevé noté hors bornes.`, r.entry.ok ? 'ok' : 'alert')
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}

// ---------- tâches ----------
const taskGroups = computed(() => (d.value?.periods || []).map((p) => ({ period: p, tasks: d.value.tasks.filter((t) => t.moment === p) })).filter((g) => g.tasks.length))
const doneCount = computed(() => d.value?.tasks.filter((t) => d.value.taskLog[t.id]?.done).length ?? 0)

async function toggleTask(t) {
  const was = !!d.value.taskLog[t.id]?.done
  // retour immédiat : on coche avant la réponse, on annule si l'écriture échoue
  d.value.taskLog = { ...d.value.taskLog, [t.id]: was ? undefined : { done: true, ts: new Date().toISOString(), staff: session.person.name } }
  try {
    d.value.taskLog = (await api(`/registres/${sp.value}/taches/${t.id}`, { method: 'POST', body: { done: !was } })).taskLog
  } catch (e) {
    d.value.taskLog = { ...d.value.taskLog, [t.id]: was ? { done: true } : undefined }
    toast(e.message, 'alert')
  }
}

// ---------- traçabilité ----------
const showTraceForm = ref(false)
const trf = reactive({ type: 'dlc', product: '', date: '', lot: '', supplier: '', temp: '' })
const dlcList = computed(() => (d.value?.summary.dlcAlerts || []).map((a) => ({ e: a.entry, left: a.daysLeft })))
const monthTraces = computed(() => (d.value?.traces || []).map((e, i) => ({ ...e, n: i + 1 })).reverse())

async function saveTrace() {
  busy.value = true
  try {
    const r = await api(`/registres/${sp.value}/tracabilite`, { method: 'POST', body: { ...trf } })
    d.value.traces.push(r.entry)
    Object.assign(trf, { product: '', date: '', lot: '', supplier: '', temp: '' })
    showTraceForm.value = false
    toast(`Entrée n° ${String(r.number).padStart(4, '0')} enregistrée.`)
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}

async function removeTrace(e) {
  try {
    const m = new Date(e.ts).toLocaleDateString('sv-SE', { timeZone: 'Europe/Paris' }).slice(0, 7)
    await api(`/registres/${sp.value}/tracabilite/${e.id}/retrait?month=${m}`, { method: 'POST' })
    await load()
    toast(`${e.product} retiré du stock.`)
  } catch (err) {
    toast(err.message, 'alert')
  }
}
</script>

<template>
  <div class="stack">
    <RouterLink to="/registres" class="back"><ChevronLeft :size="18" :stroke-width="1.7" /> Registres</RouterLink>
    <h1>{{ d?.label || '…' }}</h1>

    <div class="tabs" role="tablist" aria-label="Sections du registre">
      <button v-for="t in TABS" :key="t.id" role="tab" class="tab" :class="{ on: tab === t.id }" :aria-selected="tab === t.id" @click="setTab(t.id)">{{ t.label }}</button>
    </div>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    <div v-if="!d && !error" class="skeleton sk" aria-busy="true" />

    <template v-if="d">
      <!-- ================= Aujourd'hui ================= -->
      <section v-if="tab === 'jour'" class="stack">
        <div v-if="d.summary.tempAlerts.length || dlcList.length" class="panel pad">
          <h2 class="h-ico"><TriangleAlert :size="20" :stroke-width="1.7" /> À traiter</h2>
          <ul class="register">
            <RegisterEntry v-for="e in d.summary.tempAlerts" :key="e.id" :title="e.equipmentName" :sub="`${time(e.ts)}, ${e.comment}`" :value="`${num(e.value)}°`" state="alert" :staff="e.staff" />
            <RegisterEntry v-for="x in dlcList" :key="x.e.id" :state="dlcState(x.left).cls" :title="x.e.product" :sub="`DLC ${day(x.e.date)}${x.e.lot ? ', lot ' + x.e.lot : ''}`" :staff="x.e.staff">
              <span class="state" :class="`state-${dlcState(x.left).cls}`">{{ dlcState(x.left).label }}</span>
              <template #end><button v-if="x.left <= 0" class="btn btn-secondary small" @click="removeTrace(x.e)">Retiré</button></template>
            </RegisterEntry>
          </ul>
        </div>
        <p v-else class="state state-ok all-ok"><Check :size="16" :stroke-width="2" /> Rien à signaler pour l'instant.</p>

        <div class="quick">
          <button class="quick-btn panel" @click="setTab('temp')">
            <Thermometer :size="24" :stroke-width="1.7" />
            <span class="q-label">Températures</span>
            <span class="data q-val">{{ tempsToday.length }} relevé{{ tempsToday.length > 1 ? 's' : '' }}</span>
          </button>
          <button class="quick-btn panel" @click="setTab('taches')">
            <ListChecks :size="24" :stroke-width="1.7" />
            <span class="q-label">Tâches</span>
            <span class="data q-val">{{ doneCount }} / {{ d.tasks.length }}</span>
          </button>
          <button class="quick-btn panel" @click="setTab('trace')">
            <Tag :size="24" :stroke-width="1.7" />
            <span class="q-label">Traçabilité</span>
            <span class="data q-val">{{ d.traces.length }} ce mois</span>
          </button>
        </div>
      </section>

      <!-- ================= Températures ================= -->
      <section v-if="tab === 'temp'" class="stack">
        <ul v-if="isCurrentMonth" class="register panel eqs">
          <li v-for="eq in d.equipments" :key="eq.id" :class="lastToday(eq.id) && (lastToday(eq.id).ok ? 'tone-ok' : 'tone-alert')">
            <button class="eq-row" :aria-expanded="openEq === eq.id" @click="openTemp(eq)">
              <span class="txt">
                <span class="eq-name">{{ eq.name }}</span>
                <span class="caption data">Bornes : {{ num(eq.min, 0) }} à {{ num(eq.max, 0) }} °C</span>
              </span>
              <span v-if="lastToday(eq.id)" class="mono last" :class="{ out: !lastToday(eq.id).ok }">{{ num(lastToday(eq.id).value) }}°</span>
              <span v-else class="caption">À relever</span>
            </button>
            <Transition name="open">
            <form v-if="openEq === eq.id" class="temp-form" @submit.prevent="saveTemp(eq)">
              <div class="value-line">
                <button v-if="eq.min < 0" type="button" class="btn btn-secondary sign data" :aria-pressed="tf.negative" @click="tf.negative = !tf.negative">{{ tf.negative ? '−' : '+' }}</button>
                <label class="field grow">
                  <span class="visually-hidden">Température en °C</span>
                  <input v-model="tf.value" class="input data big" inputmode="decimal" placeholder="0,0" autofocus :aria-invalid="tfOut(eq)" />
                </label>
                <span class="unit data">°C</span>
              </div>
              <p v-if="tfOut(eq)" class="state state-alert">Hors bornes : attendu {{ num(eq.min, 0) }} à {{ num(eq.max, 0) }} °C</p>
              <label class="field">
                <span class="label">Moment</span>
                <select v-model="tf.period" class="input"><option v-for="p in d.periods" :key="p">{{ p }}</option></select>
              </label>
              <label class="field">
                <span class="label">{{ tfOut(eq) ? 'Action corrective (obligatoire)' : 'Remarque' }}</span>
                <input v-model="tf.comment" class="input" :placeholder="tfOut(eq) ? 'Ex. porte vérifiée, nouveau relevé dans 30 min' : ''" />
              </label>
              <button class="btn btn-primary btn-block" :disabled="busy || tfValue === null || (tfOut(eq) && !tf.comment.trim())">Enregistrer le relevé</button>
            </form>
            </Transition>
          </li>
        </ul>

        <div class="panel pad">
          <div class="month-nav">
            <button class="btn btn-ghost icon" aria-label="Mois précédent" @click="goMonth(-1)"><ChevronLeft :size="20" :stroke-width="1.7" /></button>
            <h2 class="month">{{ isCurrentMonth ? 'Relevés du jour' : monthLabel(d.month) }}</h2>
            <button class="btn btn-ghost icon" aria-label="Mois suivant" :disabled="isCurrentMonth" @click="goMonth(1)"><ChevronRight :size="20" :stroke-width="1.7" /></button>
          </div>
          <ul class="register">
            <RegisterEntry v-for="e in (isCurrentMonth ? [...tempsToday].reverse() : monthTemps)" :key="e.id" :number="e.n" :title="e.equipmentName"
              :sub="`${isCurrentMonth ? '' : day(e.ts) + ' '}${time(e.ts)}${e.comment ? ', ' + e.comment : ''}`" :value="`${num(e.value)}°`" :state="e.ok ? '' : 'alert'" :staff="e.staff" />
          </ul>
          <p v-if="!(isCurrentMonth ? tempsToday : monthTemps).length" class="muted empty">Aucun relevé {{ isCurrentMonth ? "aujourd'hui" : 'ce mois-là' }}. Touche un point de contrôle pour commencer.</p>
        </div>
      </section>

      <!-- ================= Tâches ================= -->
      <section v-if="tab === 'taches'" class="stack">
        <p class="caption">{{ doneCount }} / {{ d.tasks.length }} faites aujourd'hui</p>
        <div v-for="g in taskGroups" :key="g.period" class="panel pad">
          <h2 class="group">{{ g.period }} <span class="data caption">{{ g.tasks.filter((t) => d.taskLog[t.id]?.done).length }} / {{ g.tasks.length }}</span></h2>
          <ul class="register">
            <li v-for="t in g.tasks" :key="t.id" :class="d.taskLog[t.id]?.done && 'bleed tone-ok'">
              <label class="task">
                <input type="checkbox" :checked="!!d.taskLog[t.id]?.done" @change="toggleTask(t)" />
                <span class="task-label" :class="{ done: d.taskLog[t.id]?.done }">{{ t.label }}</span>
                <span v-if="d.taskLog[t.id]?.done" class="data caption">{{ d.taskLog[t.id].ts ? time(d.taskLog[t.id].ts) : '' }}</span>
                <span v-if="d.taskLog[t.id]?.staff" class="visa">{{ initials(d.taskLog[t.id].staff) }}</span>
              </label>
            </li>
          </ul>
        </div>
      </section>

      <!-- ================= Traçabilité ================= -->
      <section v-if="tab === 'trace'" class="stack">
        <button v-if="!showTraceForm && isCurrentMonth" class="btn btn-primary" @click="showTraceForm = true"><Plus :size="20" :stroke-width="1.7" /> Nouvelle entrée</button>
        <Transition name="open">
        <form v-if="showTraceForm" class="panel pad stack" @submit.prevent="saveTrace">
          <div class="seg" role="radiogroup" aria-label="Type d'entrée">
            <button v-for="(label, id) in d.traceTypes" :key="id" type="button" role="radio" :aria-checked="trf.type === id" class="seg-btn" :class="{ on: trf.type === id }" @click="trf.type = id">{{ label }}</button>
          </div>
          <label class="field"><span class="label">Produit</span><input v-model="trf.product" class="input" required /></label>
          <label v-if="trf.type === 'dlc'" class="field"><span class="label">DLC (à jeter le)</span><input v-model="trf.date" class="input data" type="date" required /></label>
          <label class="field"><span class="label">Numéro de lot</span><input v-model="trf.lot" class="input data" autocapitalize="characters" /></label>
          <template v-if="trf.type === 'reception'">
            <label class="field"><span class="label">Fournisseur</span><input v-model="trf.supplier" class="input" /></label>
            <label class="field"><span class="label">Température à réception (°C)</span><input v-model="trf.temp" class="input data" inputmode="decimal" /></label>
          </template>
          <div class="row-btns">
            <button type="button" class="btn btn-secondary" @click="showTraceForm = false">Annuler</button>
            <button class="btn btn-primary grow" :disabled="busy || !trf.product.trim()">Enregistrer</button>
          </div>
        </form>
        </Transition>

        <div class="panel pad">
          <div class="month-nav">
            <button class="btn btn-ghost icon" aria-label="Mois précédent" @click="goMonth(-1)"><ChevronLeft :size="20" :stroke-width="1.7" /></button>
            <h2 class="month">{{ monthLabel(d.month) }}</h2>
            <button class="btn btn-ghost icon" aria-label="Mois suivant" :disabled="isCurrentMonth" @click="goMonth(1)"><ChevronRight :size="20" :stroke-width="1.7" /></button>
          </div>
          <ul class="register">
            <RegisterEntry v-for="e in monthTraces" :key="e.id" :number="e.n" :title="e.product" :state="!e.removedAt && e.type === 'dlc' && e.date && daysUntil(e.date, d.today) <= 3 ? dlcState(daysUntil(e.date, d.today)).cls : ''"
              :sub="[d.traceTypes[e.type], e.date && 'DLC ' + day(e.date), e.lot && 'lot ' + e.lot, e.supplier, e.temp !== null && e.temp !== undefined && num(e.temp) + ' °C'].filter(Boolean).join(', ')" :staff="e.staff">
              <span v-if="e.removedAt" class="state state-neutral">Retiré</span>
              <span v-else-if="e.type === 'dlc' && e.date && daysUntil(e.date, d.today) <= 3" class="state" :class="`state-${dlcState(daysUntil(e.date, d.today)).cls}`">{{ dlcState(daysUntil(e.date, d.today)).label }}</span>
            </RegisterEntry>
          </ul>
          <p v-if="!monthTraces.length" class="muted empty">Aucun lot enregistré ce mois-ci. Saisis le numéro de lot d'une livraison pour commencer la traçabilité.</p>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.back { display: inline-flex; align-items: center; gap: 4px; color: var(--ink-soft); text-decoration: none; font-size: 14px; margin-bottom: -8px; justify-self: start; min-height: 32px; }
.pad { padding: 4px 16px 8px; overflow: hidden; }
/* une dernière ligne teintée touche le bas du panneau */
.pad:has(> .register > .bleed:last-child) { padding-bottom: 0; }
.pad > h2 { padding-top: 14px; margin-bottom: 8px; }
.h-ico { display: flex; align-items: center; gap: 8px; color: var(--alert); }
.all-ok { justify-self: start; }
.sk { height: 240px; }

.tabs { display: flex; gap: 4px; padding: 4px; background: var(--sand-dark); border-radius: var(--r-m); overflow-x: auto; scrollbar-width: none; }
.tab { flex: 1 0 auto; min-height: 44px; padding: 0 10px; border: none; border-radius: var(--r-s); background: transparent; color: var(--ink-soft); font-weight: 500; font-size: 15px; }
.tab.on { background: var(--sand); color: var(--sea-deep); font-weight: 600; box-shadow: 0 1px 2px rgb(28 42 74 / .08); }

.quick { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.quick-btn { display: grid; justify-items: start; gap: 6px; padding: 16px; color: var(--sea-deep); text-align: left; transition: transform 160ms var(--ease-out); }
.quick-btn:active { transform: scale(.97); }
.q-label { font-weight: 600; color: var(--ink); }
.q-val { font-size: 13px; color: var(--ink-soft); }
@media (max-width: 520px) { .quick { grid-template-columns: 1fr; } .quick-btn { grid-template-columns: auto 1fr auto; align-items: center; } }

.eqs { overflow: hidden; }
.eqs > li { padding: 0 16px; }
.eq-row { width: 100%; display: flex; align-items: center; gap: 12px; min-height: 68px; padding: 10px 0; border: none; background: none; text-align: left; color: var(--ink); }
.txt { flex: 1; display: grid; gap: 2px; }
.eq-name { font-weight: 500; }
.last { font-size: 20px; font-weight: 500; }
.last.out { color: var(--alert); }
.temp-form { display: grid; gap: 14px; padding: 4px 0 18px; }
.value-line { display: flex; align-items: center; gap: 10px; }
.grow { flex: 1; }
.big { font-size: 28px; line-height: 32px; min-height: 60px; }
.sign { width: 60px; min-height: 60px; padding: 0; font-size: 24px; }
.unit { font-size: 20px; color: var(--ink-soft); }

.month-nav { display: flex; align-items: center; gap: 8px; padding-top: 8px; }
.month { flex: 1; padding: 0 !important; }
.icon { width: 44px; padding: 0; }
.empty { padding: 8px 0 16px; }

.group { display: flex; justify-content: space-between; align-items: baseline; font-size: 16px; }
.task { display: flex; align-items: center; gap: 14px; min-height: 60px; padding: 8px 0; cursor: pointer; }
.task input { width: 26px; height: 26px; accent-color: var(--ok); flex: none; margin: 0; }
.task-label { flex: 1; }
.task-label.done { color: var(--ink); }

.seg { display: flex; flex-wrap: wrap; gap: 8px; }
.seg-btn { min-height: 44px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--sand); color: var(--ink); font-size: 14px; font-weight: 500; }
.seg-btn.on { background: var(--tint-strong); border-color: var(--tint-strong); color: var(--accent-deep); }
@media (prefers-color-scheme: dark) { .seg-btn.on { color: var(--sea-deep); } }
.row-btns { display: flex; gap: 10px; }
.small { min-height: 44px; padding: 0 12px; font-size: 14px; }
</style>
