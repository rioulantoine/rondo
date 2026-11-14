<script setup>
import { ref, reactive, onMounted } from 'vue'
import { UserPlus, MapPin } from 'lucide-vue-next'
import { api, initials } from '../api'
import { toast } from '../toast'

const PERMS = {
  cuisine: 'Cuisine', salle: 'Salle', bar: 'Bar', reservation: 'Réservations', planning: 'Gérer le planning',
  incidents: 'Signaler un renvoi ou un incident', absences: 'Enregistrer une absence justifiée', validateSwaps: 'Valider les échanges de shift',
}
const POSTES = { cuisine: 'Cuisine', salle: 'Salle', bar: 'Bar', ouvfer: 'Ouverture / fermeture + caisse' }

const d = ref(null)
const error = ref('')
const form = ref(null)
const busy = ref(false)

async function load() {
  try {
    d.value = await api('/equipe')
  } catch (e) {
    error.value = e.message
  }
}
onMounted(load)

const blank = () => ({ id: null, name: '', pin: '', permissions: [], postes: [], weeklyHours: 35, phone: '', email: '', canBadgeFromPersonalDevice: false, forfaitJour: false, hygieneTrainingDone: false, hygieneTrainingDate: '' })
function edit(m) {
  form.value = reactive({ ...blank(), ...m, pin: '', hygieneTrainingDate: m.hygieneTrainingDate || '' })
}

async function save() {
  busy.value = true
  try {
    const f = form.value
    await api(f.id ? `/equipe/${f.id}` : '/equipe', { method: f.id ? 'PUT' : 'POST', body: f })
    toast(f.id ? `Fiche de ${f.name} mise à jour.` : `${f.name} ajouté·e à l'équipe.`)
    form.value = null
    load()
  } catch (e) {
    toast(e.message, 'alert')
  } finally {
    busy.value = false
  }
}
async function remove() {
  if (!confirm(`Retirer ${form.value.name} de l'équipe ? Ses relevés et pointages restent dans les registres.`)) return
  try {
    await api(`/equipe/${form.value.id}`, { method: 'DELETE' })
    toast(`${form.value.name} retiré·e de l'équipe.`)
    form.value = null
    load()
  } catch (e) {
    toast(e.message, 'alert')
  }
}

async function saveSettings(patch) {
  try {
    await api('/reglages/pointage', { method: 'PUT', body: { pauseEnabled: d.value.settings.pauseEnabled, ...patch } })
    toast('Réglage enregistré.')
    load()
  } catch (e) {
    toast(e.message, 'alert')
  }
}
function setPosition() {
  if (!navigator.geolocation) return toast("Cet appareil ne donne pas sa position.", 'alert')
  navigator.geolocation.getCurrentPosition(
    (p) => saveSettings({ location: { lat: p.coords.latitude, lng: p.coords.longitude } }),
    () => toast("Position refusée. Autorise la localisation et recommence depuis l'établissement.", 'alert'),
    { enableHighAccuracy: true, timeout: 8000 },
  )
}
</script>

<template>
  <div class="stack">
    <div class="head">
      <h1>Équipe</h1>
      <button v-if="!form" class="btn btn-primary" @click="form = reactive(blank())"><UserPlus :size="20" :stroke-width="1.7" /> Ajouter</button>
    </div>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>

    <form v-if="form" class="panel pad stack" @submit.prevent="save">
      <h2>{{ form.id ? form.name : 'Nouvelle personne' }}</h2>
      <div class="grid2">
        <label class="field"><span class="label">Nom</span><input v-model="form.name" class="input" autocomplete="off" required /></label>
        <label class="field"><span class="label">{{ form.id ? 'Nouveau PIN (vide = inchangé)' : 'Code PIN' }}</span>
          <input v-model="form.pin" class="input data" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="off" :required="!form.id" />
        </label>
        <label class="field"><span class="label">Téléphone</span><input v-model="form.phone" class="input" type="tel" /></label>
        <label class="field"><span class="label">E-mail</span><input v-model="form.email" class="input" type="email" /></label>
        <label class="field"><span class="label">Heures par semaine (contrat)</span><input v-model.number="form.weeklyHours" class="input data" type="number" min="0" max="60" step="0.5" /></label>
      </div>

      <fieldset class="group"><legend class="label">Postes</legend>
        <label v-for="(l, k) in POSTES" :key="k" class="check"><input v-model="form.postes" type="checkbox" :value="k" /> {{ l }}</label>
      </fieldset>
      <fieldset class="group"><legend class="label">Accès</legend>
        <label v-for="(l, k) in PERMS" :key="k" class="check"><input v-model="form.permissions" type="checkbox" :value="k" /> {{ l }}</label>
      </fieldset>
      <fieldset class="group"><legend class="label">Pointage et hygiène</legend>
        <label class="check"><input v-model="form.canBadgeFromPersonalDevice" type="checkbox" /> Peut pointer depuis son téléphone</label>
        <label class="check"><input v-model="form.forfaitJour" type="checkbox" /> Cadre au forfait jour (pas de pointage horaire)</label>
        <label class="check"><input v-model="form.hygieneTrainingDone" type="checkbox" /> Formation hygiène suivie</label>
        <label v-if="form.hygieneTrainingDone" class="field date"><span class="caption">Date de la formation</span><input v-model="form.hygieneTrainingDate" type="date" class="input data" /></label>
      </fieldset>

      <div class="row-btns">
        <button v-if="form.id" type="button" class="btn btn-danger" @click="remove">Retirer</button>
        <span class="grow" />
        <button type="button" class="btn btn-secondary" @click="form = null">Annuler</button>
        <button class="btn btn-primary" :disabled="busy">Enregistrer</button>
      </div>
    </form>

    <ul v-if="d" class="register panel list">
      <li v-for="m in d.members" :key="m.id">
        <button class="member" @click="edit(m)">
          <span class="visa">{{ initials(m.name) }}</span>
          <span class="grow txt">
            <span class="name">{{ m.name }}</span>
            <span class="caption">{{ m.postes.map((p) => POSTES[p]).join(' · ') || 'Aucun poste' }}<template v-if="m.weeklyHours"> · <span class="data">{{ m.weeklyHours }} h</span></template></span>
            <span v-if="!m.hygieneTrainingDone" class="state state-warn">Formation hygiène à prévoir</span>
          </span>
        </button>
      </li>
      <li v-if="!d.members.length" class="muted empty">Ajoute la première personne : elle pourra entrer avec son code PIN.</li>
    </ul>

    <section v-if="d" class="panel pad stack">
      <h2>Réglages du pointage</h2>
      <label class="check"><input type="checkbox" :checked="d.settings.pauseEnabled" @change="saveSettings({ pauseEnabled: $event.target.checked })" /> Pointer aussi les pauses</label>
      <div class="pos">
        <MapPin :size="20" :stroke-width="1.7" />
        <span class="grow">
          <span class="label">Position de l'établissement</span>
          <span class="caption">{{ d.settings.location ? 'Enregistrée : les pointages à plus de 150 m sont signalés.' : 'Pas encore enregistrée.' }}</span>
        </span>
        <button class="btn btn-secondary small" @click="setPosition">{{ d.settings.location ? 'Mettre à jour' : 'Enregistrer ici' }}</button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; }
.pad { padding: 16px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.group { border: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.group legend { margin-bottom: 10px; font-weight: 600; }
.date { max-width: 220px; margin-left: 30px; }
.row-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.grow { flex: 1; min-width: 0; }
.list > li { padding: 0 16px; }
.member { width: 100%; display: flex; align-items: center; gap: 12px; min-height: 68px; padding: 10px 0; border: none; background: none; text-align: left; color: var(--ink); }
.txt { display: grid; gap: 2px; }
.txt .state { justify-self: start; margin-top: 4px; }
.name { font-weight: 600; }
.empty { padding: 16px 0; }
.pos { display: flex; align-items: center; gap: 12px; }
.pos > svg { flex: none; color: var(--sea-deep); }
.pos .grow { display: grid; gap: 2px; }
.small { min-height: 40px; font-size: 14px; }
@media (max-width: 560px) { .grid2 { grid-template-columns: 1fr; } }
</style>
