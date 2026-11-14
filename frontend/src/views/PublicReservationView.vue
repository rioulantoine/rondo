<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Minus, Plus, CircleCheck } from 'lucide-vue-next'
import { api } from '../api'
import { todayParis } from '../format'
import RondoLogo from '../components/RondoLogo.vue'

const route = useRoute()
const code = route.params.code
const resto = ref(null)
const error = ref('')
const step = ref(1) // 1 date+convives, 2 coordonnées, 3 confirmé
const f = reactive({ date: todayParis(), party: 2, slot: '', firstName: '', lastName: '', phone: '', email: '', notes: '', website: '', consent: false })
const slots = ref(null)
const busy = ref(false)
const done = ref(null)
const cancel = ref(null)

const longDate = (iso) => new Date(iso + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const groups = computed(() => {
  const g = { Midi: [], Soir: [] }
  for (const s of slots.value || []) (s.slot < '16:00' ? g.Midi : g.Soir).push(s)
  return Object.entries(g).filter(([, l]) => l.length)
})
const cancelUrl = computed(() => done.value && `${location.origin}/r/${code}?annuler=${done.value.cancelToken}`)

onMounted(async () => {
  try {
    resto.value = await api(`/public/${code}`)
    document.title = `Réserver · ${resto.value.name}`
    const t = route.query.annuler
    if (t) {
      try {
        cancel.value = await api(`/public/${code}/annulation`, { method: 'POST', body: { token: t } })
      } catch (e) {
        cancel.value = { error: e.message }
      }
    }
  } catch (e) {
    error.value = e.message
  }
})

async function loadSlots() {
  slots.value = null
  f.slot = ''
  try {
    slots.value = (await api(`/public/${code}/disponibilites?date=${f.date}&party=${f.party}`)).slots
  } catch (e) {
    error.value = e.message
  }
}
watch(() => [f.date, f.party, resto.value], () => resto.value && !cancel.value && loadSlots(), { immediate: true })

async function book() {
  busy.value = true
  error.value = ''
  try {
    done.value = await api(`/public/${code}/reservations`, { method: 'POST', body: { ...f } })
    step.value = 3
  } catch (e) {
    error.value = e.message
    if (e.status === 409) { step.value = 1; loadSlots() }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="pub">
    <header class="top">
      <h1>{{ resto?.name || ' ' }}</h1>
      <p class="muted">Réserver une table</p>
    </header>

    <p v-if="error && !resto" class="error-text card" role="alert">{{ error }}</p>

    <!-- annulation via le lien reçu -->
    <section v-if="cancel" class="card stack center" role="status">
      <template v-if="cancel.error"><h2>Annulation impossible</h2><p class="muted">{{ cancel.error }}</p></template>
      <template v-else>
        <CircleCheck :size="44" :stroke-width="1.7" class="ok" />
        <h2>{{ cancel.alreadyCancelled ? 'Déjà annulée' : 'Réservation annulée' }}</h2>
        <p class="muted">{{ longDate(cancel.date) }} à <span class="data">{{ cancel.slot }}</span>. Merci de nous avoir prévenus.</p>
      </template>
    </section>

    <template v-else-if="resto">
      <!-- étape 1 : date, convives, créneau -->
      <section v-if="step === 1" class="card stack">
        <div class="row2">
          <label class="field"><span class="label">Date</span><input v-model="f.date" type="date" class="input" :min="resto.today" /></label>
          <div class="field">
            <span class="label" id="party-l">Personnes</span>
            <div class="stepper" role="group" aria-labelledby="party-l">
              <button type="button" class="btn btn-secondary sq" aria-label="Une personne de moins" :disabled="f.party <= 1" @click="f.party--"><Minus :size="18" /></button>
              <span class="data n" aria-live="polite">{{ f.party }}</span>
              <button type="button" class="btn btn-secondary sq" aria-label="Une personne de plus" :disabled="f.party >= resto.maxPartySize" @click="f.party++"><Plus :size="18" /></button>
            </div>
          </div>
        </div>
        <p v-if="f.party >= resto.maxPartySize" class="caption">Au-delà de {{ resto.maxPartySize }} personnes, appelle directement le restaurant.</p>

        <div v-if="!slots" class="skeleton sk" aria-busy="true" />
        <template v-else>
          <div v-for="[label, list] in groups" :key="label" class="slot-group">
            <h2 class="g">{{ label }}</h2>
            <div class="slots rise-list">
              <button v-for="s in list" :key="s.slot" type="button" class="slot data" :class="{ on: f.slot === s.slot }" :disabled="s.count < 1" :aria-pressed="f.slot === s.slot" @click="f.slot = s.slot">{{ s.slot }}</button>
            </div>
          </div>
          <p v-if="!groups.length" class="muted">Plus de créneau ce jour-là. Essaie une autre date.</p>
        </template>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <button class="btn btn-primary btn-block" :disabled="!f.slot" @click="step = 2; error = ''">Continuer</button>
      </section>

      <!-- étape 2 : coordonnées -->
      <form v-if="step === 2" class="card stack" @submit.prevent="book">
        <p class="recap">{{ longDate(f.date) }} · <span class="data">{{ f.slot }}</span> · {{ f.party }} pers.</p>
        <div class="row2">
          <label class="field"><span class="label">Prénom</span><input v-model="f.firstName" class="input" autocomplete="given-name" required /></label>
          <label class="field"><span class="label">Nom</span><input v-model="f.lastName" class="input" autocomplete="family-name" required /></label>
        </div>
        <label class="field"><span class="label">Téléphone</span><input v-model="f.phone" class="input" type="tel" autocomplete="tel" required /></label>
        <label class="field"><span class="label">E-mail</span><input v-model="f.email" class="input" type="email" autocomplete="email" required /></label>
        <label class="field"><span class="label">Remarque (allergie, occasion…)</span><textarea v-model="f.notes" class="input" rows="2" maxlength="500" /></label>
        <label class="trap" aria-hidden="true">Site web <input v-model="f.website" tabindex="-1" autocomplete="off" /></label>
        <p class="caption">Tes coordonnées servent uniquement à gérer cette réservation. Tu peux demander leur suppression au restaurant.</p>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <div class="row-btns">
          <button type="button" class="btn btn-secondary" @click="step = 1">Retour</button>
          <button class="btn btn-primary grow" :disabled="busy">{{ busy ? 'Un instant…' : 'Confirmer la réservation' }}</button>
        </div>
      </form>

      <!-- étape 3 : confirmé -->
      <section v-if="step === 3" class="card stack center" role="status">
        <CircleCheck :size="44" :stroke-width="1.7" class="ok" />
        <h2>Ta réservation est confirmée</h2>
        <p>{{ f.party }} personne{{ f.party > 1 ? 's' : '' }}, {{ longDate(done.date) }} à <span class="data">{{ done.slot }}</span>.</p>
        <p class="caption">Garde ce lien pour annuler si besoin :</p>
        <a :href="cancelUrl" class="cancel-link">{{ cancelUrl }}</a>
      </section>
    </template>

    <footer class="foot caption">Réservé avec <RondoLogo :size="14" /></footer>
  </main>
</template>

<style scoped>
.pub { min-height: 100svh; display: grid; align-content: start; justify-items: center; gap: 20px; padding: calc(32px + env(safe-area-inset-top)) 16px calc(24px + env(safe-area-inset-bottom)); background: var(--sand-dark); }
.top { text-align: center; display: grid; gap: 4px; }
.card { width: 100%; max-width: 520px; background: var(--sand); border: 1px solid var(--line); border-radius: var(--r-l); padding: 24px 20px; box-shadow: var(--shadow); }
.center { justify-items: center; text-align: center; }
.ok { color: var(--ok); }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.stepper { display: flex; align-items: center; gap: 8px; }
.sq { width: 48px; padding: 0; }
.n { min-width: 2ch; text-align: center; font-size: 20px; }
.sk { height: 120px; }
.g { font-size: 15px; margin-bottom: 8px; }
.slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 8px; }
.slot { min-height: 44px; border-radius: var(--r-m); border: 1px solid var(--line); background: var(--sand); color: var(--ink); font-size: 15px; }
.slot.on { background: var(--sea-deep); border-color: var(--sea-deep); color: var(--on-sea); }
.slot:disabled { color: var(--ink-soft); text-decoration: line-through; opacity: .5; }
.recap { font-weight: 600; color: var(--sea-deep); }
.recap::first-letter { text-transform: uppercase; }
.trap { position: absolute; left: -9999px; }
.row-btns { display: flex; gap: 10px; }
.grow { flex: 1; }
.cancel-link { overflow-wrap: anywhere; font-size: 14px; }
.foot { display: flex; align-items: center; gap: 6px; }
@media (max-width: 420px) { .row2 { grid-template-columns: 1fr; } }
</style>
