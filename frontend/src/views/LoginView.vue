<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Eye, EyeOff } from 'lucide-vue-next'
import { api, setSession } from '../api'
import { slugify } from '../format'
import { OFFRES, TRIAL_DAYS } from '../offres'
import { LIENS } from '../legal'
import RondoLogo from '../components/RondoLogo.vue'

const router = useRouter()
const route = useRoute()
const mode = ref(route.query.mode === 'create' ? 'create' : 'login') // login | create | reset
const busy = ref(false)
const error = ref('')
const info = ref('')
const showPwd = ref(false)
const capsLock = ref(false)
const touched = reactive({})
const f = reactive({
  code: '', password: '', remember: true, name: '', email: '', password2: '', cgu: false,
  offre: OFFRES.some((o) => o.id === route.query.offre) ? route.query.offre : 'equipe',
})

// garde l'URL en phase avec l'écran (retour arrière, lien partagé)
watch(mode, (m) => {
  error.value = ''
  info.value = ''
  router.replace({ query: { ...route.query, mode: m === 'login' ? undefined : m } })
})

const derivedCode = computed(() => slugify(f.code || f.name))
const TITLE = { login: 'Connexion', create: 'Créer un établissement', reset: 'Mot de passe oublié' }

/** Erreurs de saisie, affichées sous le groupe de champs une fois le champ quitté. */
const errs = computed(() => {
  const e = {}
  if (mode.value === 'create') {
    if (!f.name.trim()) e.name = 'Indique le nom du restaurant.'
    else if (!derivedCode.value) e.code = 'Le code doit contenir des lettres ou des chiffres.'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) e.email = 'Adresse e-mail invalide.'
    if (f.password.length < 6) e.password = 'Le mot de passe fait au moins 6 caractères.'
    else if (f.password2 !== f.password) e.password2 = 'Les deux mots de passe sont différents.'
  } else {
    if (!f.code.trim()) e.code = 'Indique le code de ton établissement.'
    if (mode.value === 'login' && !f.password) e.password = 'Indique le mot de passe.'
  }
  return e
})
const shown = computed(() => Object.entries(errs.value).filter(([k]) => touched[k]).map(([, v]) => v))

function onKey(e) {
  if (e.getModifierState) capsLock.value = e.getModifierState('CapsLock')
}

async function submit() {
  Object.assign(touched, { code: true, name: true, email: true, password: true, password2: true })
  error.value = ''
  if (Object.keys(errs.value).length) return
  if (mode.value === 'create' && !f.cgu) {
    error.value = 'Accepte les conditions générales pour continuer.'
    return
  }
  busy.value = true
  try {
    if (mode.value === 'login') {
      setSession(await api('/login', { method: 'POST', body: { code: f.code, password: f.password, remember: f.remember } }))
      router.replace('/qui')
    } else if (mode.value === 'create') {
      setSession(await api('/restaurants', { method: 'POST', body: { name: f.name, code: derivedCode.value, email: f.email, password: f.password, cgu: f.cgu, offre: f.offre } }))
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
  <div class="auth">
    <main class="col">
      <RouterLink to="/bienvenue" class="mark" aria-label="Accueil de Rondo"><RondoLogo :size="40" :wordmark="false" /></RouterLink>
      <h1>{{ TITLE[mode] }}</h1>
      <p v-if="mode === 'login'" class="lead">Entre le code et le mot de passe de ton établissement.</p>
      <p v-else-if="mode === 'create'" class="lead">{{ TRIAL_DAYS }} jours d’essai, sans carte bancaire.</p>
      <p v-else class="lead">Un lien est envoyé à l’adresse e-mail de récupération.</p>

      <form novalidate @submit.prevent="submit" @keydown="onKey" @keyup="onKey">
        <!-- champs groupés dans un seul bloc, chacun avec son libellé -->
        <div class="group">
          <label v-if="mode === 'create'" class="row">
            <span class="row-l">Nom du restaurant</span>
            <input v-model="f.name" class="row-i" autocomplete="organization" :aria-invalid="touched.name && !!errs.name" @blur="touched.name = true" />
          </label>
          <label class="row">
            <span class="row-l">{{ mode === 'create' ? 'Code de connexion' : 'Code de l’établissement' }}</span>
            <input v-model="f.code" class="row-i" autocapitalize="none" autocomplete="username" spellcheck="false"
              :placeholder="mode === 'create' ? derivedCode : ''" :aria-invalid="touched.code && !!errs.code" @blur="touched.code = true" />
          </label>
          <label v-if="mode === 'create'" class="row">
            <span class="row-l">E-mail de récupération</span>
            <input v-model="f.email" class="row-i" type="email" inputmode="email" autocomplete="email" :aria-invalid="touched.email && !!errs.email" @blur="touched.email = true" />
          </label>
          <label v-if="mode !== 'reset'" class="row">
            <span class="row-l">Mot de passe</span>
            <span class="pwd">
              <input v-model="f.password" class="row-i" :type="showPwd ? 'text' : 'password'" :autocomplete="mode === 'create' ? 'new-password' : 'current-password'"
                :aria-invalid="touched.password && !!errs.password" @blur="touched.password = true" />
              <button type="button" class="eye" :aria-label="showPwd ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" :aria-pressed="showPwd" @click="showPwd = !showPwd">
                <component :is="showPwd ? EyeOff : Eye" :size="20" :stroke-width="1.6" />
              </button>
            </span>
          </label>
          <label v-if="mode === 'create'" class="row">
            <span class="row-l">Confirmer le mot de passe</span>
            <input v-model="f.password2" class="row-i" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" :aria-invalid="touched.password2 && !!errs.password2" @blur="touched.password2 = true" />
          </label>
          <label v-if="mode === 'create'" class="row">
            <span class="row-l">Offre après l’essai</span>
            <select v-model="f.offre" class="row-i">
              <option v-for="o in OFFRES" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
          </label>
        </div>

        <p v-if="mode === 'create' && derivedCode && !shown.length" class="note">Ton équipe se connectera avec le code <span class="data">{{ derivedCode }}</span>.</p>
        <p v-if="capsLock" class="note warn">La touche de verrouillage des majuscules est activée.</p>
        <ul v-if="shown.length || error" class="errors" role="alert">
          <li v-for="m in shown" :key="m">{{ m }}</li>
          <li v-if="error">{{ error }}</li>
        </ul>
        <p v-if="info" class="note ok" role="status">{{ info }}</p>

        <label v-if="mode === 'login'" class="check"><input v-model="f.remember" type="checkbox" /> Rester connecté sur cet appareil</label>
        <label v-if="mode === 'create'" class="check">
          <input v-model="f.cgu" type="checkbox" />
          <span>J’accepte les <RouterLink to="/conditions" target="_blank">conditions générales</RouterLink> et la <RouterLink to="/confidentialite" target="_blank">politique de confidentialité</RouterLink>.</span>
        </label>

        <button class="btn btn-primary btn-block" :disabled="busy">
          {{ busy ? 'Un instant…' : mode === 'login' ? 'Continuer' : mode === 'create' ? 'Créer l’établissement' : 'Envoyer le lien' }}
        </button>
      </form>

      <div class="links">
        <template v-if="mode === 'login'">
          <button type="button" class="link" @click="mode = 'reset'">Mot de passe oublié ?</button>
          <p>Pas encore de compte ? <button type="button" class="link" @click="mode = 'create'">Créer un établissement</button></p>
        </template>
        <p v-else>Déjà un compte ? <button type="button" class="link" @click="mode = 'login'">Se connecter</button></p>
      </div>
    </main>

    <footer class="foot">
      <RouterLink v-for="l in LIENS" :key="l.to" :to="l.to">{{ l.label }}</RouterLink>
    </footer>
  </div>
</template>

<style scoped>
.auth { min-height: 100svh; display: grid; grid-template-rows: 1fr auto; background: var(--sand); }
.col { width: 100%; max-width: 400px; margin: 0 auto; padding: calc(56px + env(safe-area-inset-top)) 20px 32px; display: grid; align-content: center; justify-items: center; text-align: center; }
.mark { display: flex; min-width: 44px; min-height: 44px; align-items: center; justify-content: center; margin-bottom: 20px; text-decoration: none; }
.col h1 { font-size: 28px; line-height: 34px; }
.lead { margin-top: 8px; color: var(--ink-soft); max-width: 32ch; }
form { width: 100%; margin-top: 28px; display: grid; gap: 14px; text-align: left; }

/* un seul bloc bordé, les champs séparés par un filet (formulaires Apple) */
.group { border: 1px solid var(--line); border-radius: var(--r-l); background: var(--sand); overflow: hidden; transition: border-color 160ms ease; }
.group:focus-within { border-color: var(--sea-deep); box-shadow: 0 0 0 3px var(--tint); }
.row { display: grid; gap: 2px; padding: 10px 14px 8px; cursor: text; }
.row + .row { border-top: 1px solid var(--line); }
.row-l { font-size: 12px; line-height: 16px; color: var(--ink-soft); }
.row-i { width: 100%; min-height: 28px; padding: 0; border: none; outline: none; background: transparent; color: var(--ink); font: 400 17px/26px var(--font-ui); caret-color: var(--sea-deep); }
.row:has(.row-i[aria-invalid='true']) .row-l { color: var(--alert); }
select.row-i { cursor: pointer; }
.pwd { display: flex; align-items: center; gap: 4px; }
.eye { flex: none; width: 44px; height: 44px; margin: -12px -10px -10px 0; display: grid; place-items: center; border: none; background: none; color: var(--ink-soft); border-radius: var(--r-s); }

.note { font-size: 14px; line-height: 20px; color: var(--ink-soft); }
.note.warn { color: var(--warn); }
.note.ok { color: var(--ok); }
.errors { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; font-size: 14px; line-height: 20px; color: var(--alert); }
.check a { color: var(--sea-deep); }
form .btn { margin-top: 6px; }

.links { margin-top: 24px; display: grid; gap: 10px; justify-items: center; font-size: 15px; color: var(--ink-soft); }
.link { min-height: 44px; padding: 0 2px; border: none; background: none; color: var(--sea-deep); font-weight: 500; font-size: 15px; }
.links p .link { min-height: 0; }
@media (hover: hover) and (pointer: fine) { .link:hover, .foot a:hover { text-decoration: underline; text-underline-offset: 3px; } }

.foot { display: flex; flex-wrap: wrap; justify-content: center; gap: 4px 24px; padding: 16px 20px calc(20px + env(safe-area-inset-bottom)); border-top: 1px solid var(--line); }
.foot a { min-height: 44px; display: inline-flex; align-items: center; color: var(--ink-soft); text-decoration: none; font-size: 13px; }
</style>
