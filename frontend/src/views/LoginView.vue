<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { api, setSession } from '../api'
import RondoLogo from '../components/RondoLogo.vue'

const router = useRouter()
const mode = ref('login') // login | create | reset
const busy = ref(false)
const error = ref('')
const info = ref('')
const f = reactive({ code: '', password: '', remember: true, name: '', email: '', password2: '', cgu: false })

function switchMode(m) {
  mode.value = m
  error.value = ''
  info.value = ''
}

async function submit() {
  error.value = ''
  if (mode.value === 'create' && f.password !== f.password2) {
    error.value = 'Les deux mots de passe ne sont pas identiques.'
    return
  }
  busy.value = true
  try {
    if (mode.value === 'login') {
      setSession(await api('/login', { method: 'POST', body: { code: f.code, password: f.password, remember: f.remember } }))
      router.replace('/qui')
    } else if (mode.value === 'create') {
      setSession(await api('/restaurants', { method: 'POST', body: { name: f.name, code: f.code || f.name, email: f.email, password: f.password, cgu: f.cgu } }))
      router.replace('/')
    } else {
      await api('/password-reset', { method: 'POST', body: { code: f.code } })
      info.value = "Si ce code existe, un lien de réinitialisation part vers l'e-mail de récupération."
    }
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="auth">
    <RondoLogo :size="32" class="brand" />

    <form class="card stack" @submit.prevent="submit" novalidate>
      <div class="intro">
        <h1 v-if="mode === 'login'">Ouvre le registre</h1>
        <h1 v-else-if="mode === 'create'">Crée ton établissement</h1>
        <h1 v-else>Mot de passe oublié</h1>
        <p class="muted" v-if="mode === 'login'">Code et mot de passe de l'établissement. Chacun choisit ensuite son nom.</p>
        <p class="muted" v-else-if="mode === 'create'">14 jours d'essai, sans carte bancaire.</p>
        <p class="muted" v-else>Indique le code de l'établissement.</p>
      </div>

      <label v-if="mode === 'create'" class="field">
        <span class="label">Nom de l'établissement</span>
        <input v-model="f.name" class="input" autocomplete="organization" required placeholder="Sumo Beach" />
      </label>

      <label class="field">
        <span class="label">Code de l'établissement</span>
        <input v-model="f.code" class="input" autocapitalize="none" autocomplete="username" spellcheck="false"
          :placeholder="mode === 'create' ? 'Laisse vide pour le déduire du nom' : 'sumo-beach'" />
      </label>

      <label v-if="mode === 'create'" class="field">
        <span class="label">E-mail de récupération</span>
        <input v-model="f.email" class="input" type="email" autocomplete="email" inputmode="email" required />
      </label>

      <label v-if="mode !== 'reset'" class="field">
        <span class="label">Mot de passe</span>
        <input v-model="f.password" class="input" type="password" required
          :autocomplete="mode === 'create' ? 'new-password' : 'current-password'" />
      </label>

      <label v-if="mode === 'create'" class="field">
        <span class="label">Confirme le mot de passe</span>
        <input v-model="f.password2" class="input" type="password" autocomplete="new-password" required />
      </label>

      <label v-if="mode === 'login'" class="check">
        <input v-model="f.remember" type="checkbox" /> Rester connecté sur cet appareil
      </label>
      <label v-if="mode === 'create'" class="check">
        <input v-model="f.cgu" type="checkbox" /> J'accepte les conditions générales d'utilisation et de vente
      </label>

      <p v-if="error" class="error-text" role="alert">{{ error }}</p>
      <p v-if="info" class="state state-ok" role="status">{{ info }}</p>

      <button class="btn btn-primary btn-block" :disabled="busy">
        {{ busy ? 'Un instant…' : mode === 'login' ? 'Entrer' : mode === 'create' ? "Créer l'établissement" : 'Envoyer le lien' }}
      </button>

      <div class="links">
        <template v-if="mode === 'login'">
          <button type="button" class="btn btn-ghost" @click="switchMode('reset')">Mot de passe oublié</button>
          <button type="button" class="btn btn-ghost" @click="switchMode('create')">Créer un établissement</button>
        </template>
        <button v-else type="button" class="btn btn-ghost" @click="switchMode('login')">J'ai déjà un compte</button>
      </div>
    </form>
  </main>
</template>

<style scoped>
.auth {
  min-height: 100svh; display: grid; align-content: center; justify-items: center; gap: 32px;
  padding: calc(32px + env(safe-area-inset-top)) 16px calc(32px + env(safe-area-inset-bottom));
  background: var(--sand-dark);
}
.card { width: 100%; max-width: 400px; background: var(--sand); border: 1px solid var(--line); border-radius: var(--r-l); padding: 28px 24px; box-shadow: var(--shadow); }
.intro { display: grid; gap: 8px; margin-bottom: 8px; }
.links { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px; margin-top: -4px; }
.links .btn { font-weight: 500; font-size: 15px; padding: 0 12px; }
</style>
