<script setup>
import { Check } from 'lucide-vue-next'
import RondoLogo from '../components/RondoLogo.vue'
import { OFFRES, FEATURES, TRIAL_DAYS } from '../offres'
import { LIENS } from '../legal'

const JOURNEE = [
  { quand: 'Avant le service', texte: 'L’équipe pointe en arrivant. Les frigos sont relevés, les dates limites vérifiées. Ce qui doit partir à la poubelle est signalé dès l’ouverture.' },
  { quand: 'Pendant le service', texte: 'La salle suit les réservations du jour. Un relevé hors bornes demande tout de suite l’action corrective, et le responsable est prévenu.' },
  { quand: 'À la fermeture', texte: 'Le nettoyage est coché et signé, les départs sont pointés. Le registre du jour est complet, prêt à être présenté.' },
]
const FONCTIONS = [
  { titre: 'Hygiène', texte: 'Températures, nettoyage et traçabilité pour la cuisine, la salle et le bar. Chaque saisie est numérotée, datée et signée par la personne qui l’a faite.' },
  { titre: 'Contrôle sanitaire', texte: 'Un rapport PDF par période, avec les relevés, les actions correctives et le suivi du nettoyage. Il reste lisible imprimé en noir et blanc.' },
  { titre: 'Pointage', texte: 'Sur la tablette du restaurant, avec un code personnel et une signature. L’heure retenue est celle du serveur, pas celle de l’appareil.' },
  { titre: 'Planning', texte: 'La semaine est publiée à l’équipe quand elle est prête. Rondo signale les journées trop longues et les repos trop courts.' },
  { titre: 'Équipe', texte: 'Chacun entre avec son code et ne voit que les espaces qui le concernent. Le responsable garde la main sur les accès.' },
  { titre: 'Réservations', texte: 'Une page de réservation au nom du restaurant, à partager à ses clients, et la liste du service pour la salle.' },
]
const QUESTIONS = [
  { q: 'Faut-il installer une application ?', r: 'Non. Rondo s’ouvre dans le navigateur et s’ajoute à l’écran d’accueil d’un iPhone, d’un iPad ou d’un téléphone Android.' },
  { q: 'J’utilise déjà Check ton resto.', r: 'Rondo en est la suite et reprend ses données. Le code de l’établissement et le mot de passe restent les mêmes.' },
  { q: 'Combien ça coûte ?', r: `Les tarifs ne sont pas encore fixés. L’essai de ${TRIAL_DAYS} jours donne accès à tout, sans carte bancaire, et rien n’est facturé sans ton accord.` },
  { q: 'Qui est derrière Rondo ?', r: 'Un restaurateur de Pornichet, qui l’a d’abord construit pour son propre établissement, le Sumo Beach.' },
]
const inclus = (offre, id) => offre.features.includes(id)
</script>

<template>
  <div class="site">
    <header class="top">
      <div class="wrap bar">
        <RondoLogo :size="24" />
        <nav class="menu" aria-label="Sections">
          <a href="#fonctions">Fonctions</a>
          <a href="#offres">Offres</a>
          <a href="#questions">Questions</a>
        </nav>
        <RouterLink to="/connexion" class="signin">Se connecter</RouterLink>
        <RouterLink to="/connexion?mode=create" class="btn btn-primary btn-sm">Essayer</RouterLink>
      </div>
    </header>

    <main>
      <section class="wrap hero">
        <div class="pitch">
          <h1>Le registre du restaurant, tenu à chaque service.</h1>
          <p class="lede">Rondo remplace les cahiers d’hygiène, la pointeuse et le planning affiché en cuisine. Tout est saisi sur le téléphone de l’équipe ou sur la tablette du restaurant.</p>
          <div class="ctas">
            <RouterLink to="/connexion?mode=create" class="btn btn-primary">Commencer l’essai gratuit</RouterLink>
            <RouterLink to="/connexion" class="btn btn-secondary">Se connecter</RouterLink>
          </div>
          <p class="fine">{{ TRIAL_DAYS }} jours d’essai, sans carte bancaire.</p>
        </div>
        <figure class="shots">
          <img class="desk" src="/captures/registre-cuisine.webp" width="1280" height="800" alt="Le registre des températures de la cuisine dans Rondo, sur ordinateur." />
          <img class="phone" src="/captures/accueil-telephone.webp" width="390" height="844" alt="L’accueil de Rondo sur téléphone, avec les alertes du jour." />
        </figure>
      </section>

      <section class="wrap">
        <h2>Une journée avec Rondo</h2>
        <ol class="journee">
          <li v-for="j in JOURNEE" :key="j.quand">
            <h3>{{ j.quand }}</h3>
            <p>{{ j.texte }}</p>
          </li>
        </ol>
      </section>

      <section id="fonctions" class="wrap">
        <h2>Ce que fait Rondo</h2>
        <dl class="fonctions">
          <div v-for="f in FONCTIONS" :key="f.titre">
            <dt>{{ f.titre }}</dt>
            <dd>{{ f.texte }}</dd>
          </div>
        </dl>
      </section>

      <section id="offres" class="wrap">
        <h2>Trois offres</h2>
        <p class="intro">L’essai de {{ TRIAL_DAYS }} jours comprend toutes les fonctions. Les tarifs seront communiqués avant toute facturation.</p>

        <div class="scroll">
          <table class="offres">
            <caption class="visually-hidden">Ce que comprend chaque offre</caption>
            <thead>
              <tr>
                <td />
                <th v-for="o in OFFRES" :key="o.id" scope="col">
                  <span class="o-name">{{ o.name }}</span>
                  <span class="o-pitch">{{ o.pitch }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in FEATURES" :key="f.id">
                <th scope="row">{{ f.label }}</th>
                <td v-for="o in OFFRES" :key="o.id">
                  <Check v-if="inclus(o, f.id)" :size="18" :stroke-width="2" aria-label="Compris" />
                  <span v-else class="visually-hidden">Non compris</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td />
                <td v-for="o in OFFRES" :key="o.id">
                  <RouterLink :to="`/connexion?mode=create&offre=${o.id}`" class="choose">Essayer {{ o.name }}</RouterLink>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <section id="questions" class="wrap">
        <h2>Questions fréquentes</h2>
        <dl class="questions">
          <div v-for="x in QUESTIONS" :key="x.q">
            <dt>{{ x.q }}</dt>
            <dd>{{ x.r }}</dd>
          </div>
        </dl>
      </section>

      <section class="final">
        <div class="wrap final-in">
          <h2>Essaie Rondo dans ton restaurant.</h2>
          <RouterLink to="/connexion?mode=create" class="btn btn-light">Créer mon établissement</RouterLink>
        </div>
      </section>
    </main>

    <footer class="wrap foot">
      <RondoLogo :size="20" />
      <nav aria-label="Informations légales">
        <RouterLink v-for="l in LIENS" :key="l.to" :to="l.to">{{ l.label }}</RouterLink>
      </nav>
    </footer>
  </div>
</template>

<style scoped>
.site { background: var(--sand); color: var(--ink); }
.wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-left: 24px; padding-right: 24px; }

.top { position: sticky; top: 0; z-index: 10; background: var(--sand); border-bottom: 1px solid var(--line); padding-top: env(safe-area-inset-top); }
.bar { display: flex; align-items: center; gap: 20px; min-height: 64px; }
.menu { display: flex; gap: 28px; margin: 0 auto 0 28px; }
.menu a, .signin { color: var(--ink-soft); text-decoration: none; font-size: 15px; min-height: 44px; display: inline-flex; align-items: center; }
.signin { color: var(--sea-deep); font-weight: 500; }
@media (hover: hover) and (pointer: fine) { .menu a:hover { color: var(--sea-deep); } .signin:hover, .choose:hover, .foot a:hover { text-decoration: underline; text-underline-offset: 3px; } }
@media (max-width: 760px) { .menu { display: none; } .signin { margin-left: auto; } }

section { padding-top: 96px; }
h2 { font-size: clamp(26px, 3.2vw, 36px); line-height: 1.15; letter-spacing: -0.025em; font-weight: 700; margin-bottom: 32px; }
h3, dt { font: 600 17px/24px var(--font-ui); color: var(--sea-deep); }

.hero { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); gap: 56px; align-items: center; padding-top: 72px; }
.hero h1 { font-size: clamp(38px, 5vw, 60px); line-height: 1.04; letter-spacing: -0.035em; }
.lede { margin-top: 20px; font-size: 19px; line-height: 29px; color: var(--ink-soft); max-width: 44ch; }
.ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 32px; }
.ctas .btn { min-height: 52px; padding: 0 22px; }
.fine { margin-top: 14px; font-size: 14px; color: var(--ink-soft); }
.shots { position: relative; margin: 0; padding: 0 0 32px 0; }
.shots img { display: block; height: auto; border: 1px solid var(--line); background: var(--sand); }
.desk { width: 100%; border-radius: 12px; }
.phone { position: absolute; right: -8px; bottom: 0; width: 27%; border-radius: 18px; box-shadow: var(--shadow-lg); }
@media (max-width: 900px) { .hero { grid-template-columns: 1fr; gap: 40px; padding-top: 40px; } .phone { right: 8px; } }

.journee { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; counter-reset: j; }
.journee li { border-top: 2px solid var(--sea-deep); padding-top: 18px; }
.journee p, dd { margin: 8px 0 0; color: var(--ink-soft); max-width: 46ch; }
@media (max-width: 760px) { .journee { grid-template-columns: 1fr; gap: 28px; } }

.fonctions, .questions { margin: 0; display: grid; grid-template-columns: 1fr 1fr; column-gap: 56px; }
.fonctions > div, .questions > div { padding: 22px 0; border-top: 1px solid var(--line); }
@media (max-width: 760px) { .fonctions, .questions { grid-template-columns: 1fr; } }

.intro { margin: -16px 0 28px; color: var(--ink-soft); max-width: 60ch; }
.scroll { overflow-x: auto; }
.offres { width: 100%; min-width: 620px; border-collapse: collapse; }
.offres th, .offres td { padding: 14px 16px; border-bottom: 1px solid var(--line); text-align: center; vertical-align: middle; }
.offres tbody th { text-align: left; font-weight: 400; }
.offres thead th { text-align: center; vertical-align: bottom; border-bottom: 2px solid var(--sea-deep); width: 22%; }
.offres thead span { display: block; }
.o-name { font-size: 19px; font-weight: 700; color: var(--sea-deep); }
.o-pitch { margin-top: 4px; font-weight: 400; font-size: 14px; line-height: 20px; color: var(--ink-soft); }
.offres svg { color: var(--sea-deep); }
.offres tfoot td { border-bottom: none; padding-top: 18px; }
.choose { color: var(--sea-deep); font-weight: 600; text-decoration: none; min-height: 44px; display: inline-flex; align-items: center; }

.final { margin-top: 96px; padding: 72px 0; background: #1C2A4A; }
.final-in { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 24px; }
.final h2 { color: #FFFFFF; margin: 0; }
.btn-light { background: #FFFFFF; color: #1C2A4A; min-height: 52px; padding: 0 22px; }

.foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 24px; padding-top: 28px; padding-bottom: calc(28px + env(safe-area-inset-bottom)); }
.foot nav { display: flex; flex-wrap: wrap; gap: 4px 24px; }
.foot a { color: var(--ink-soft); text-decoration: none; font-size: 14px; min-height: 44px; display: inline-flex; align-items: center; }
</style>
