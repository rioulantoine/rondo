<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Eye, EyeOff, ArrowLeft, CircleCheck, TriangleAlert, LoaderCircle } from 'lucide-vue-next'
import { api, setSession } from '../api'
import { slugify } from '../format'
import { OFFRES, TRIAL_DAYS } from '../offres'
import RondoLogo from '../components/RondoLogo.vue'
import LiveRegister from '../components/LiveRegister.vue'

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

// garde l'URL en phase avec l'onglet (retour arrière, lien partagé)
watch(mode, (m) => {
  error.value = ''
  info.value = ''
  router.replace({ query: { ...route.query, mode: m === 'login' ? undefined : m } })
})

const derivedCode = computed(() => slugify(f.code || f.name))
const offre = computed(() => OFFRES.find((o) => o.id === f.offre))

/** Erreurs de saisie affichées sous chaque champ, une fois le champ quitté. */
const errs = computed(() => {
  const e = {}
  if (mode.value !== 'reset' && mode.value !== 'create' && !f.code.trim()) e.code = 'Indique le code de ton établissement.'
  if (mode.value === 'reset' && !f.code.trim()) e.code = 'Indique le code de ton établissement.'
  if (mode.value === 'create') {
    if (!f.name.trim()) e.name = 'Indique le nom du restaurant.'
    if (!derivedCode.value) e.code = 'Ce code ne contient ni lettre ni chiffre.'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) e.email = 'Adresse e-mail invalide.'
    if (f.password.length < 6) e.password = 'Au moins 6 caractères.'
    if (f.password2 !== f.password) e.password2 = 'Les deux mots de passe sont différents.'
  } else if (mode.value === 'login' && !f.password) e.password = 'Indique le mot de passe.'
  return e
})
const show = (k) => touched[k] && errs.value[k]
const strength = computed(() => {
  const p = f.password
  if (!p) return 0
  return Math.min(3, (p.length >= 6) + (p.length >= 10) + (/[^a-zA-Z]/.test(p) && /[a-zA-Z]/.test(p)))
})

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
    <!-- côté marque (grand écran) : l'appli telle qu'elle sera au service -->
    <aside class="brand" aria-hidden="true">
      <RondoLogo :size="28" class="b-logo" />
      <div class="b-body">
        <p class="b-quote">Chaque relevé est une entrée numérotée, datée et visée. C’est ce que l’inspecteur demande.</p>
        <LiveRegister title="Cuisine · relevés du jour" />
      </div>
      <p class="b-foot">Hygiène · Équipe · Planning · Réservations</p>
    </aside>

    <main class="side">
      <div class="side-top">
        <RouterLink to="/bienvenue" class="back"><ArrowLeft :size="18" :stroke-width="1.7" /> Découvrir Rondo</RouterLink>
        <RondoLogo :size="24" class="m-logo" />
      </div>

      <form class="form" novalidate @submit.prevent="submit" @keydown="onKey" @keyup="onKey">
        <div v-if="mode !== 'reset'" class="seg" role="tablist" aria-label="Connexion ou création">
          <span class="seg-ind" :class="mode" aria-hidden="true" />
          <button type="button" role="tab" :aria-selected="mode === 'login'" class="seg-btn" @click="mode = 'login'">Se connecter</button>
          <button type="button" role="tab" :aria-selected="mode === 'create'" class="seg-btn" @click="mode = 'create'">Créer un compte</button>
        </div>

        <Transition name="swap" mode="out-in">
          <div :key="mode" class="fields">
            <div class="intro">
              <h1 v-if="mode === 'login'">Ouvre le registre</h1>
              <h1 v-else-if="mode === 'create'">Crée ton établissement</h1>
              <h1 v-else>Mot de passe oublié</h1>
              <p class="muted" v-if="mode === 'login'">Code et mot de passe de l’établissement. Chacun choisit ensuite son nom et son code PIN.</p>
              <p class="muted" v-else-if="mode === 'create'">{{ TRIAL_DAYS }} jours d’essai, tout inclus, sans carte bancaire.</p>
              <p class="muted" v-else>Indique le code de l’établissement : on envoie un lien à l’e-mail de récupération.</p>
            </div>

            <!-- offre choisie -->
            <fieldset v-if="mode === 'create'" class="offres">
              <legend class="label">Offre visée après l’essai</legend>
              <label v-for="o in OFFRES" :key="o.id" class="offre" :class="{ on: f.offre === o.id }">
                <input v-model="f.offre" type="radio" name="offre" :value="o.id" class="visually-hidden" />
                <span class="o-name">{{ o.name }}</span>
                <span class="caption">{{ o.pitch }}</span>
              </label>
            </fieldset>

            <label v-if="mode === 'create'" class="field">
              <span class="label">Nom du restaurant</span>
              <input v-model="f.name" class="input" autocomplete="organization" placeholder="Sumo Beach" :aria-invalid="!!show('name')" @blur="touched.name = true" />
              <span v-if="show('name')" class="err">{{ errs.name }}</span>
            </label>

            <label class="field">
              <span class="label">{{ mode === 'create' ? 'Code de connexion' : 'Code de l’établissement' }}</span>
              <input v-model="f.code" class="input" autocapitalize="none" autocomplete="username" spellcheck="false"
                :placeholder="mode === 'create' ? derivedCode || 'déduit du nom' : 'sumo-beach'" :aria-invalid="!!show('code')" @blur="touched.code = true" />
              <span v-if="mode === 'create' && derivedCode" class="hint">L’équipe se connectera avec <strong class="data">{{ derivedCode }}</strong></span>
              <span v-if="show('code')" class="err">{{ errs.code }}</span>
            </label>

            <label v-if="mode === 'create'" class="field">
              <span class="label">E-mail de récupération</span>
              <input v-model="f.email" class="input" type="email" inputmode="email" autocomplete="email" :aria-invalid="!!show('email')" @blur="touched.email = true" />
              <span v-if="show('email')" class="err">{{ errs.email }}</span>
            </label>

            <label v-if="mode !== 'reset'" class="field">
              <span class="label">Mot de passe</span>
              <span class="pwd">
                <input v-model="f.password" class="input" :type="showPwd ? 'text' : 'password'" :autocomplete="mode === 'create' ? 'new-password' : 'current-password'"
                  :aria-invalid="!!show('password')" @blur="touched.password = true" />
                <button type="button" class="eye" :aria-label="showPwd ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" :aria-pressed="showPwd" @click="showPwd = !showPwd">
                  <component :is="showPwd ? EyeOff : Eye" :size="20" :stroke-width="1.7" />
                </button>
              </span>
              <span v-if="mode === 'create' && f.password" class="meter" :data-level="strength" aria-hidden="true"><i /><i /><i /></span>
              <span v-if="capsLock" class="warn-line"><TriangleAlert :size="16" :stroke-width="1.7" /> Majuscules verrouillées</span>
              <span v-if="show('password')" class="err">{{ errs.password }}</span>
            </label>

            <label v-if="mode === 'create'" class="field">
              <span class="label">Confirme le mot de passe</span>
              <input v-model="f.password2" class="input" :type="showPwd ? 'text' : 'password'" autocomplete="new-password" :aria-invalid="!!show('password2')" @blur="touched.password2 = true" />
              <span v-if="show('password2')" class="err">{{ errs.password2 }}</span>
            </label>

            <label v-if="mode === 'login'" class="check"><input v-model="f.remember" type="checkbox" /> Rester connecté sur cet appareil</label>
            <label v-if="mode === 'create'" class="check"><input v-model="f.cgu" type="checkbox" /> J’accepte les conditions générales d’utilisation et de vente</label>

            <p v-if="error" class="error-box" role="alert"><TriangleAlert :size="18" :stroke-width="1.7" /> {{ error }}</p>
            <p v-if="info" class="info-box" role="status"><CircleCheck :size="18" :stroke-width="1.7" /> {{ info }}</p>

            <button class="btn btn-primary btn-block go" :disabled="busy">
              <LoaderCircle v-if="busy" :size="20" :stroke-width="2" class="spin" />
              {{ busy ? 'Un instant…' : mode === 'login' ? 'Entrer' : mode === 'create' ? `Démarrer l’essai ${offre?.name}` : 'Envoyer le lien' }}
            </button>

            <button v-if="mode === 'login'" type="button" class="btn btn-ghost link" @click="mode = 'reset'">Mot de passe oublié ?</button>
            <button v-if="mode === 'reset'" type="button" class="btn btn-ghost link" @click="mode = 'login'"><ArrowLeft :size="18" :stroke-width="1.7" /> Retour à la connexion</button>
          </div>
        </Transition>
      </form>
    </main>
  </div>
</template>

<style scoped>
.auth { min-height: 100svh; display: grid; grid-template-columns: minmax(0, 1fr); background: var(--sand); }
@media (min-width: 1000px) { .auth { grid-template-columns: minmax(420px, 1fr) minmax(480px, 560px); } }

/* ---------- côté marque ---------- */
.brand { display: none; }
@media (min-width: 1000px) {
  .brand {
    display: grid; grid-template-rows: auto 1fr auto; gap: 32px; padding: 40px 48px;
    background: #1C2A4A; color: #FFFFFF;
    background-image: radial-gradient(120% 80% at 100% 0%, rgb(191 227 220 / .16), transparent 60%);
  }
  .b-logo { color: #BFE3DC; }
  .b-body { display: grid; gap: 28px; align-content: center; max-width: 520px; width: 100%; justify-self: center; }
  .b-quote { font-size: 24px; line-height: 32px; font-weight: 500; letter-spacing: -0.01em; color: #FFFFFF; text-wrap: balance; }
  .b-foot { color: #AFC0EA; font-size: 14px; }
  .brand :deep(.live) { box-shadow: 0 30px 60px -30px rgb(0 0 0 / .6); }
}

/* ---------- formulaire ---------- */
.side { display: grid; grid-template-rows: auto 1fr; padding: calc(16px + env(safe-area-inset-top)) 20px calc(24px + env(safe-area-inset-bottom)); }
.side-top { display: flex; justify-content: space-between; align-items: center; min-height: 48px; }
.back { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; color: var(--ink-soft); text-decoration: none; font-size: 15px; font-weight: 500; }
@media (hover: hover) and (pointer: fine) { .back:hover { color: var(--sea-deep); } }
@media (min-width: 1000px) { .m-logo { display: none; } .side { padding: 32px 56px; } }
.form { width: 100%; max-width: 420px; margin: 0 auto; align-self: center; display: grid; gap: 24px; padding: 24px 0; }
.fields { display: grid; gap: 18px; }
.intro { display: grid; gap: 8px; }
.intro h1 { font-size: 32px; line-height: 36px; letter-spacing: -0.025em; }

.seg { position: relative; display: grid; grid-template-columns: 1fr 1fr; padding: 4px; background: var(--sand-dark); border: 1px solid var(--line); border-radius: 12px; }
.seg-ind { position: absolute; top: 4px; bottom: 4px; left: 4px; width: calc(50% - 4px); border-radius: 9px; background: var(--sand); box-shadow: 0 1px 3px rgb(28 42 74 / .12); transition: transform var(--dur-ui) var(--ease-in-out); }
.seg-ind.create { transform: translateX(100%); }
.seg-btn { position: relative; min-height: 44px; border: none; background: none; color: var(--ink-soft); font-weight: 500; font-size: 15px; transition: color var(--dur-ui) ease; }
.seg-btn[aria-selected='true'] { color: var(--sea-deep); font-weight: 600; }

.swap-enter-active { transition: opacity var(--dur-ui) var(--ease-out), transform var(--dur-ui) var(--ease-out); }
.swap-leave-active { transition: opacity 100ms ease; }
.swap-enter-from { opacity: 0; transform: translateY(8px); }
.swap-leave-to { opacity: 0; }

.offres { border: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.offres legend { margin-bottom: 8px; }
.offre { display: grid; gap: 2px; align-content: start; padding: 10px 12px; border: 1.5px solid var(--line); border-radius: var(--r-m); cursor: pointer; min-height: 72px; transition: border-color var(--dur-press) ease, background-color var(--dur-press) ease; }
.offre.on { border-color: var(--sea-deep); background: var(--tint); }
.offre:focus-within { outline: 2px solid var(--sea-deep); outline-offset: 2px; }
.o-name { font-weight: 600; color: var(--sea-deep); }
.offre .caption { font-size: 12px; line-height: 16px; }
@media (max-width: 420px) { .offres { grid-template-columns: 1fr; } .offre { min-height: 0; } }

.hint { font-size: 13px; color: var(--ink-soft); }
.hint strong { color: var(--accent-deep); font-weight: 500; }
.err { font-size: 13px; line-height: 18px; color: var(--alert); }
.pwd { position: relative; display: block; }
.pwd .input { padding-right: 52px; }
.eye { position: absolute; right: 2px; top: 2px; width: 44px; height: 44px; display: grid; place-items: center; border: none; background: none; color: var(--ink-soft); border-radius: var(--r-s); }
.meter { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.meter i { height: 4px; border-radius: 2px; background: var(--line); transition: background-color var(--dur-ui) ease; }
.meter[data-level='1'] i:nth-child(-n+1) { background: var(--warn); }
.meter[data-level='2'] i:nth-child(-n+2) { background: var(--ok); }
.meter[data-level='3'] i { background: var(--ok); }
.warn-line { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--warn); }
.error-box, .info-box { display: flex; gap: 8px; align-items: flex-start; padding: 12px 14px; border-radius: var(--r-m); font-size: 14px; line-height: 20px; }
.error-box { background: var(--alert-bg); color: var(--alert); animation: rise 220ms var(--ease-out); }
.info-box { background: var(--ok-bg); color: var(--ok); }
.error-box svg, .info-box svg { flex: none; margin-top: 1px; }
.go { min-height: 52px; font-size: 17px; }
.spin { animation: spin 800ms linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.link { justify-self: center; font-weight: 500; font-size: 15px; }
</style>
