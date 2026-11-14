<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { LogIn, LogOut, Coffee, CalendarCheck, ChevronLeft, CircleCheck, Tablet } from 'lucide-vue-next'
import { api, session, initials } from '../api'
import { time } from '../format'
import { toast } from '../toast'
import PinPad from '../components/PinPad.vue'
import SignaturePad from '../components/SignaturePad.vue'

const st = ref(null)
const loadError = ref('')
const step = ref('action') // action | person | pin | sign | justif | done
const f = ref({})
const error = ref('')
const busy = ref(false)
const done = ref(null)
const now = ref(new Date())
let clock, resetTimer, geo = null

const STATUS = { present: 'Présent', pause: 'En pause', parti: 'Parti', jour: 'Journée notée', absent: '' }
const self = computed(() => (session.person.owner ? null : st.value?.members.find((m) => m.id === session.person.id)))
const present = computed(() => (st.value?.members || []).filter((m) => m.status === 'present' || m.status === 'pause'))
const hasForfait = computed(() => st.value?.members.some((m) => m.forfaitJour))
const ACTIONS = computed(() => [
  { type: 'arrivee', label: 'Arrivée', icon: LogIn, primary: true },
  { type: 'depart', label: 'Départ', icon: LogOut },
  ...(st.value?.pauseEnabled ? [{ type: 'pause', label: 'Pause', icon: Coffee }] : []),
  ...(hasForfait.value ? [{ type: 'jour_travaille', label: 'Journée travaillée', icon: CalendarCheck }] : []),
])
const actionLabel = computed(() => ACTIONS.value.find((a) => a.type === f.value.type)?.label)
const people = computed(() => (st.value?.members || []).filter((m) => f.value.type !== 'jour_travaille' || m.forfaitJour))

async function load() {
  try {
    st.value = await api('/pointage')
  } catch (e) {
    loadError.value = e.message
  }
}
onMounted(() => {
  load()
  clock = setInterval(() => (now.value = new Date()), 1000 * 15)
})
onBeforeUnmount(() => {
  clearInterval(clock)
  clearTimeout(resetTimer)
})

function reset() {
  step.value = 'action'
  f.value = {}
  error.value = ''
  done.value = null
}

function pick(type) {
  f.value = { type }
  error.value = ''
  // sur le téléphone d'un employé, c'est lui qui pointe ; sur la tablette, on choisit la personne
  if (!st.value.kiosk && self.value) choosePerson(self.value)
  else step.value = 'person'
}
function choosePerson(m) {
  f.value.member = m
  step.value = 'pin'
}
function onPin(pin) {
  f.value.pin = pin
  error.value = ''
  step.value = 'sign'
  captureGeo()
}

/** Position au badgeage : repère pour le responsable, ne bloque jamais (3,5 s max). */
function captureGeo() {
  geo = null
  if (!navigator.geolocation) return
  navigator.geolocation.getCurrentPosition((p) => (geo = { lat: p.coords.latitude, lng: p.coords.longitude }), () => {}, { enableHighAccuracy: true, timeout: 3500, maximumAge: 120000 })
}

async function submit() {
  busy.value = true
  error.value = ''
  try {
    const r = await api('/pointage', {
      method: 'POST',
      body: { memberId: f.value.member.id, pin: f.value.pin, type: f.value.type, signature: f.value.signature, justification: f.value.justification, ...(geo || {}) },
    })
    done.value = { label: r.label, at: time(r.event.ts), name: f.value.member.name.split(' ')[0], offSchedule: r.event.offSchedule }
    step.value = 'done'
    load()
    resetTimer = setTimeout(reset, 6000)
  } catch (e) {
    error.value = e.message
    if (e.code === 'pin') step.value = 'pin'
    else if (e.code === 'hors_planning') step.value = 'justif'
    else if (e.status !== 422) toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}

async function declareTablet() {
  try {
    await api('/pointage/tablette', { method: 'POST' })
    toast('Cet appareil est maintenant la tablette du restaurant.')
    load()
  } catch (e) {
    toast(e.message, 'alert')
  }
}
</script>

<template>
  <div class="stack">
    <div class="head">
      <h1>Pointer</h1>
      <p class="clock data" aria-live="off">{{ now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Paris' }) }}</p>
    </div>
    <p v-if="loadError" class="error-text" role="alert">{{ loadError }}</p>

    <template v-if="st">
      <!-- 1. action -->
      <section v-if="step === 'action'" class="stack">
        <p v-if="!st.kiosk && session.person.owner" class="panel pad note">
          <Tablet :size="20" :stroke-width="1.7" />
          <span>Pour que toute l'équipe pointe ici, déclare cet appareil comme tablette du restaurant.
            <button class="btn btn-secondary small" @click="declareTablet">Déclarer la tablette</button></span>
        </p>
        <p v-else-if="!st.kiosk" class="caption">Tu pointes depuis ton téléphone. L'heure retenue est celle du serveur.</p>
        <div class="actions">
          <button v-for="a in ACTIONS" :key="a.type" class="action" :class="{ primary: a.primary }" @click="pick(a.type)">
            <component :is="a.icon" :size="28" :stroke-width="1.7" />
            <span>{{ a.label }}</span>
          </button>
        </div>
      </section>

      <!-- 2. personne (tablette) -->
      <section v-else-if="step === 'person'" class="stack">
        <button class="btn btn-ghost back" @click="reset"><ChevronLeft :size="20" :stroke-width="1.7" /> Retour</button>
        <h2>{{ actionLabel }} : qui es-tu ?</h2>
        <ul class="people">
          <li v-for="m in people" :key="m.id">
            <button class="person" @click="choosePerson(m)">
              <span class="visa">{{ initials(m.name) }}</span>
              <span class="p-name">{{ m.name }}</span>
              <span v-if="STATUS[m.status]" class="caption">{{ STATUS[m.status] }}{{ m.since ? ' · ' + time(m.since) : '' }}</span>
            </button>
          </li>
        </ul>
      </section>

      <!-- 3. PIN -->
      <section v-else-if="step === 'pin'" class="stack center">
        <button class="btn btn-ghost back" @click="st.kiosk || !self ? (step = 'person') : reset()"><ChevronLeft :size="20" :stroke-width="1.7" /> Retour</button>
        <h2>{{ actionLabel }} · {{ f.member.name }}</h2>
        <PinPad :error="error" :busy="busy" @complete="onPin" />
      </section>

      <!-- 4. signature -->
      <section v-else-if="step === 'sign'" class="stack">
        <button class="btn btn-ghost back" @click="step = 'pin'"><ChevronLeft :size="20" :stroke-width="1.7" /> Retour</button>
        <h2>Signe pour confirmer ton {{ actionLabel?.toLowerCase() }}</h2>
        <SignaturePad @change="(s) => (f.signature = s)" />
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <button class="btn btn-primary btn-block" :disabled="busy || !f.signature" @click="submit">{{ busy ? 'Un instant…' : `Valider mon ${actionLabel?.toLowerCase()}` }}</button>
      </section>

      <!-- 5. justification hors planning -->
      <section v-else-if="step === 'justif'" class="stack">
        <h2>Arrivée hors planning</h2>
        <p class="state state-warn wrap">{{ error }}</p>
        <label class="field">
          <span class="label">Justification</span>
          <textarea v-model="f.justification" class="input" rows="3" placeholder="Ex. livraison à réceptionner, remplacement de Tom" />
        </label>
        <div class="row-btns">
          <button class="btn btn-secondary" @click="reset">Annuler</button>
          <button class="btn btn-primary grow" :disabled="busy || !f.justification?.trim()" @click="submit">Enregistrer l'arrivée</button>
        </div>
      </section>

      <!-- 6. confirmation -->
      <section v-else-if="step === 'done'" class="done panel" role="status">
        <CircleCheck :size="48" :stroke-width="1.7" class="ok-ico" />
        <p class="done-title">{{ done.label }} notée à <span class="data">{{ done.at }}</span></p>
        <p class="muted">{{ done.offSchedule ? 'En attente de validation par le responsable.' : `Bon service, ${done.name}.` }}</p>
        <button class="btn btn-secondary" @click="reset">Terminé</button>
      </section>

      <section v-if="step === 'action'" class="panel pad">
        <h2 class="sub">Présents <span class="data caption">{{ present.length }}</span></h2>
        <ul class="register">
          <li v-for="m in present" :key="m.id" class="pres">
            <span class="visa">{{ initials(m.name) }}</span>
            <span class="grow">{{ m.name }}</span>
            <span v-if="m.status === 'pause'" class="state state-warn">En pause</span>
            <span class="data caption">depuis {{ time(m.arrivedAt || m.since) }}</span>
          </li>
        </ul>
        <p v-if="!present.length" class="muted empty">Personne n'a encore pointé aujourd'hui.</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: baseline; }
.clock { font-size: 28px; line-height: 32px; color: var(--sea-deep); }
.actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
/* geste répété cent fois par jour : pas d'animation, juste l'état pressé */
.action {
  min-height: 120px; display: grid; place-items: center; align-content: center; gap: 8px; padding: 16px;
  border-radius: var(--r-l); border: 1px solid var(--line); background: var(--sand); color: var(--sea-deep);
  font-size: 18px; font-weight: 600;
}
.action.primary { background: var(--sea-deep); color: var(--on-sea); border-color: var(--sea-deep); }
.action:active { background: var(--tint); }
.action.primary:active { background: var(--sea); }
.pad { padding: 4px 16px 8px; }
.note { display: flex; gap: 12px; padding: 14px 16px; color: var(--ink); }
.note > svg { flex: none; color: var(--sea-deep); }
.note > span { display: grid; gap: 10px; justify-items: start; }
.small { min-height: 44px; font-size: 14px; }
.back { justify-self: start; padding: 0 8px; margin: -8px 0 -8px -8px; font-weight: 500; }
.center { justify-items: center; text-align: center; }
.center .back { justify-self: start; }
.people { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.person { width: 100%; min-height: 112px; display: grid; justify-items: start; align-content: start; gap: 4px; padding: 14px; text-align: left; background: var(--sand); color: var(--ink); border: 1px solid var(--line); border-radius: var(--r-l); }
.person:active { background: var(--tint); }
.person .visa { width: 40px; height: 40px; font-size: 14px; margin-bottom: 6px; }
.p-name { font-weight: 600; }
.wrap { white-space: normal; border-radius: var(--r-m); padding: 10px 14px; line-height: 20px; }
.row-btns { display: flex; gap: 10px; }
.grow { flex: 1; }
.done { display: grid; justify-items: center; gap: 10px; padding: 32px 20px; text-align: center; }
.ok-ico { color: var(--ok); }
.done-title { font-size: 20px; line-height: 26px; font-weight: 600; color: var(--sea-deep); }
.sub { display: flex; justify-content: space-between; align-items: baseline; padding-top: 14px; font-size: 16px; }
.pres { display: flex; align-items: center; gap: 12px; min-height: 56px; }
.empty { padding: 8px 0 16px; }
@media (min-width: 700px) { .actions { grid-template-columns: repeat(4, 1fr); } }
</style>
