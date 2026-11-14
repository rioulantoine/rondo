<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronLeft, KeyRound } from 'lucide-vue-next'
import { api, session, setSession, initials } from '../api'
import PinPad from '../components/PinPad.vue'
import RondoLogo from '../components/RondoLogo.vue'

const router = useRouter()
const people = ref(null)
const loadError = ref('')
const chosen = ref(null) // membre, ou 'owner'
const busy = ref(false)
const error = ref('')
const ownerPwd = ref('')

const POSTES = { cuisine: 'Cuisine', salle: 'Salle', bar: 'Bar', ouvfer: 'Ouverture / fermeture' }
const title = computed(() => (chosen.value === 'owner' ? 'Accès responsable' : chosen.value ? `Bonjour ${chosen.value.name.split(' ')[0]}` : 'Qui es-tu ?'))

onMounted(async () => {
  try {
    people.value = await api('/identities')
  } catch (e) {
    loadError.value = e.message
  }
})

function choose(p) {
  chosen.value = p
  error.value = ''
  ownerPwd.value = ''
}

async function identify(body) {
  busy.value = true
  error.value = ''
  try {
    setSession(await api('/identity', { method: 'POST', body }))
    router.replace('/')
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function logout() {
  await api('/logout', { method: 'POST' }).catch(() => {})
  setSession({ restaurant: null, person: null })
  router.replace('/connexion')
}
</script>

<template>
  <main class="who">
    <header class="top">
      <RondoLogo :size="22" />
      <span class="caption">{{ session.restaurant?.name }}</span>
    </header>

    <section class="body">
      <button v-if="chosen" class="btn btn-ghost back" @click="chosen = null"><ChevronLeft :size="20" :stroke-width="1.7" /> Retour</button>
      <div class="intro">
        <h1>{{ title }}</h1>
        <p class="muted">{{ chosen === 'owner' ? 'Ressaisis le mot de passe de l’établissement.' : chosen ? 'Entre ton code PIN à 4 chiffres.' : `Touche ton nom pour ouvrir le registre de ${session.restaurant?.name || 'l’établissement'}.` }}</p>
      </div>

      <template v-if="!chosen">
        <p v-if="loadError" class="error-text" role="alert">{{ loadError }}</p>
        <ul v-else class="people rise-list" :aria-busy="!people">
          <template v-if="!people">
            <li v-for="i in 4" :key="i" class="person skeleton" aria-hidden="true" />
          </template>
          <li v-for="p in people" :key="p.id">
            <button class="person" @click="choose(p)">
              <span class="visa">{{ initials(p.name) }}</span>
              <span class="who-name">{{ p.name }}</span>
              <span class="caption">{{ p.postes.map((x) => POSTES[x] || x).join(' · ') || 'Équipe' }}</span>
            </button>
          </li>
          <li>
            <button class="person owner" @click="choose('owner')">
              <span class="visa"><KeyRound :size="16" :stroke-width="1.7" /></span>
              <span class="who-name">Responsable</span>
              <span class="caption">Mot de passe de l'établissement</span>
            </button>
          </li>
        </ul>
        <p v-if="people && !people.length" class="muted">Aucun membre d'équipe pour l'instant. Entre en responsable pour en ajouter.</p>
      </template>

      <PinPad v-else-if="chosen !== 'owner'" :busy="busy" :error="error" @complete="(pin) => identify({ memberId: chosen.id, pin })" />

      <form v-else class="stack owner-form" @submit.prevent="identify({ owner: true, password: ownerPwd })">
        <label class="field">
          <span class="label">Mot de passe de l'établissement</span>
          <input v-model="ownerPwd" class="input" type="password" autocomplete="current-password" autofocus />
        </label>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <button class="btn btn-primary btn-block" :disabled="busy || !ownerPwd">{{ busy ? 'Un instant…' : 'Entrer' }}</button>
      </form>
    </section>

    <footer class="foot">
      <button class="btn btn-ghost" @click="logout">Changer d'établissement</button>
    </footer>
  </main>
</template>

<style scoped>
.who { min-height: 100svh; display: grid; grid-template-rows: auto 1fr auto; background: var(--sand-dark); }
.top { display: flex; justify-content: space-between; align-items: center; padding: calc(16px + env(safe-area-inset-top)) 20px 16px; }
.body { width: 100%; max-width: 760px; margin: 0 auto; padding: 16px; display: grid; align-content: start; gap: 24px; }
.intro { display: grid; gap: 6px; }
.intro h1 { font-size: clamp(28px, 4vw, 36px); line-height: 1.1; letter-spacing: -0.025em; }
@media (min-width: 900px) { .body { align-content: center; padding-bottom: 8vh; } }
.back { justify-self: start; padding: 0 8px; margin: -8px 0 -16px -8px; font-weight: 500; }
.people { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.person {
  width: 100%; transition: transform var(--dur-press) var(--ease-out), border-color var(--dur-press) ease, box-shadow var(--dur-press) ease; min-height: 128px; display: grid; justify-items: start; align-content: start; gap: 4px;
  padding: 16px; text-align: left; background: var(--sand); color: var(--ink);
  border: 1px solid var(--line); border-radius: var(--r-l);
  transition: transform 160ms var(--ease-out), border-color 160ms var(--ease-out);
}
.person:active { transform: scale(.97); }
@media (hover: hover) and (pointer: fine) { .person:hover { transform: translateY(-2px); box-shadow: var(--shadow); } }
@media (hover: hover) and (pointer: fine) { .person:hover { border-color: var(--sea-deep); } }
.person .visa { width: 40px; height: 40px; font-size: 14px; margin-bottom: 8px; }
.owner .visa { background: var(--tint); color: var(--accent-deep); }
.who-name { font-weight: 600; }
.skeleton { min-height: 128px; border-radius: var(--r-l); background: var(--mist); }
.owner-form { max-width: 360px; width: 100%; }
.foot { display: flex; justify-content: center; padding: 16px 16px calc(16px + env(safe-area-inset-bottom)); }
.foot .btn { font-weight: 500; color: var(--ink-soft); }
</style>
