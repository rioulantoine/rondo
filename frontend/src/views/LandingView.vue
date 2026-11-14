<script setup>
import { Check, Minus, Sunrise, UtensilsCrossed, Moon, Thermometer, Clock4, CalendarDays, BookMarked, FileText, Users } from 'lucide-vue-next'
import RondoLogo from '../components/RondoLogo.vue'
import LiveRegister from '../components/LiveRegister.vue'
import { OFFRES, FEATURES, TRIAL_DAYS } from '../offres'

const CYCLE = [
  { icon: Sunrise, moment: 'Avant le service du matin', items: ['Relevés des froids, bornes contrôlées', 'DLC du jour : ce qui part à la poubelle', 'Chacun pointe son arrivée et signe'] },
  { icon: UtensilsCrossed, moment: 'Pendant le service', items: ['Réservations du jour, arrivées, no-shows', 'Maintien au chaud, plat témoin', 'Hors bornes : l’action corrective est demandée'] },
  { icon: Moon, moment: 'Avant la fermeture', items: ['Nettoyage coché et visé', 'Départs pointés, pauses comprises', 'Le registre du jour est complet'] },
]
const MODULES = [
  { icon: Thermometer, title: 'Hygiène HACCP', text: 'Cuisine, salle et bar : températures, tâches, traçabilité. Chaque relevé est numéroté, daté et visé.' },
  { icon: FileText, title: 'Rapport pour l’inspecteur', text: 'Un PDF par période, lisible même imprimé en noir et blanc.' },
  { icon: Clock4, title: 'Pointage', text: 'Sur la tablette du restaurant, avec code PIN et signature. L’heure retenue est celle du serveur.' },
  { icon: CalendarDays, title: 'Planning', text: 'La semaine publiée à l’équipe, avec les alertes de durée et de repos.' },
  { icon: Users, title: 'Équipe et accès', text: 'Chacun entre avec son code et ne voit que ses espaces.' },
  { icon: BookMarked, title: 'Réservations', text: 'Une page client à ton nom, et le service du jour pour la salle.' },
]
const FAQ = [
  { q: 'Faut-il installer quelque chose ?', a: 'Non. Rondo s’ouvre dans le navigateur et s’ajoute à l’écran d’accueil de l’iPhone, de l’iPad ou d’Android comme une appli.' },
  { q: 'J’utilise déjà Check ton resto.', a: 'Rondo le remplace et reprend directement ses données : même code d’établissement, même mot de passe.' },
  { q: 'Que se passe-t-il après les 14 jours ?', a: 'Les prix et le paiement en ligne arrivent bientôt. Pendant l’essai, tout est ouvert, sans carte bancaire.' },
  { q: 'Qui a conçu Rondo ?', a: 'Un restaurateur, à partir des besoins du Sumo Beach à Pornichet : les registres papier, le pointage, le planning.' },
]

/** Apparition au défilement (page commerciale uniquement). */
const vReveal = {
  mounted(el) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return
    el.classList.add('reveal')
    const io = new IntersectionObserver(([e]) => {
      if (e.isIntersecting) { el.classList.add('in'); io.disconnect() }
    }, { rootMargin: '0px 0px -10% 0px' })
    io.observe(el)
  },
}
const has = (offre, id) => offre.features.includes(id)
</script>

<template>
  <div class="landing">
    <header class="top">
      <RondoLogo :size="26" />
      <nav class="links" aria-label="Sections">
        <a href="#fonctions">Fonctions</a>
        <a href="#offres">Offres</a>
        <a href="#questions">Questions</a>
      </nav>
      <RouterLink to="/connexion" class="btn btn-ghost btn-sm">Se connecter</RouterLink>
    </header>

    <main>
      <section class="hero wrap">
        <div class="pitch">
          <h1>Le registre du restaurant, tenu à chaque service.</h1>
          <p class="lede">Hygiène HACCP, pointage, planning et réservations dans une seule appli, sur le téléphone de l’équipe comme sur la tablette du passe.</p>
          <div class="ctas">
            <RouterLink to="/connexion?mode=create" class="btn btn-primary cta">Essayer {{ TRIAL_DAYS }} jours gratuitement</RouterLink>
            <RouterLink to="/connexion" class="btn btn-secondary cta">J’ai déjà un compte</RouterLink>
          </div>
          <p class="caption">Sans carte bancaire. Conçu par un restaurateur à Pornichet.</p>
        </div>
        <div class="demo"><LiveRegister /></div>
      </section>

      <section class="cycle wrap" v-reveal>
        <h2>Ouverture, service, fermeture. Et on recommence demain.</h2>
        <ol class="moments">
          <li v-for="c in CYCLE" :key="c.moment" class="moment">
            <span class="m-ico"><component :is="c.icon" :size="22" :stroke-width="1.7" /></span>
            <h3>{{ c.moment }}</h3>
            <ul><li v-for="i in c.items" :key="i">{{ i }}</li></ul>
          </li>
        </ol>
      </section>

      <section id="fonctions" class="wrap" v-reveal>
        <h2>Tout ce que tenait le cahier, et le reste.</h2>
        <ul class="modules">
          <li v-for="m in MODULES" :key="m.title">
            <component :is="m.icon" :size="22" :stroke-width="1.7" class="mod-ico" />
            <div><h3>{{ m.title }}</h3><p class="muted">{{ m.text }}</p></div>
          </li>
        </ul>
      </section>

      <section id="offres" class="wrap" v-reveal>
        <h2>Choisis ton offre</h2>
        <p class="muted sub">{{ TRIAL_DAYS }} jours d’essai avec tout inclus, puis l’offre qui te correspond. Les prix seront annoncés avec l’ouverture du paiement en ligne.</p>

        <div class="table-wrap">
          <table class="offres">
            <caption class="visually-hidden">Comparaison des offres Rondo</caption>
            <thead>
              <tr>
                <th scope="col"><span class="visually-hidden">Fonction</span></th>
                <th v-for="o in OFFRES" :key="o.id" scope="col" :class="{ hl: o.highlight }">
                  <span v-if="o.highlight" class="state state-neutral pick">Recommandé</span>
                  <span class="o-name">{{ o.name }}</span>
                  <span class="o-pitch">{{ o.pitch }}</span>
                  <span class="o-price">Prix à venir</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in FEATURES" :key="f.id">
                <th scope="row"><span class="f">{{ f.label }}</span><span class="caption">{{ f.detail }}</span></th>
                <td v-for="o in OFFRES" :key="o.id" :class="{ hl: o.highlight }">
                  <Check v-if="has(o, f.id)" :size="20" :stroke-width="2" class="yes" aria-label="Inclus" />
                  <Minus v-else :size="18" :stroke-width="1.7" class="no" aria-label="Non inclus" />
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td />
                <td v-for="o in OFFRES" :key="o.id" :class="{ hl: o.highlight }">
                  <RouterLink :to="`/connexion?mode=create&offre=${o.id}`" class="btn btn-block" :class="o.highlight ? 'btn-primary' : 'btn-secondary'">Essayer {{ o.name }}</RouterLink>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- téléphone : une offre après l'autre, sans tableau à faire défiler -->
        <ul class="offres-mobile">
          <li v-for="o in OFFRES" :key="o.id" class="om" :class="{ hl: o.highlight }">
            <div class="om-head">
              <span class="o-name">{{ o.name }}</span>
              <span v-if="o.highlight" class="state state-neutral">Recommandé</span>
            </div>
            <p class="o-pitch">{{ o.pitch }}</p>
            <p class="o-price">Prix à venir</p>
            <ul class="om-list">
              <li v-for="f in FEATURES.filter((x) => has(o, x.id))" :key="f.id"><Check :size="18" :stroke-width="2" class="yes" /> {{ f.label }}</li>
            </ul>
            <RouterLink :to="`/connexion?mode=create&offre=${o.id}`" class="btn btn-block" :class="o.highlight ? 'btn-primary' : 'btn-secondary'">Essayer {{ o.name }}</RouterLink>
          </li>
        </ul>
      </section>

      <section id="questions" class="wrap faq" v-reveal>
        <h2>Questions</h2>
        <details v-for="f in FAQ" :key="f.q" class="qa">
          <summary>{{ f.q }}</summary>
          <p class="muted">{{ f.a }}</p>
        </details>
      </section>

      <section class="final wrap" v-reveal>
        <h2>Ton prochain relevé, dans Rondo.</h2>
        <RouterLink to="/connexion?mode=create" class="btn btn-primary cta">Créer mon établissement</RouterLink>
      </section>
    </main>

    <footer class="foot wrap">
      <RondoLogo :size="18" />
      <span class="caption">Le registre du restaurant · hygiène, équipe, planning et réservations</span>
    </footer>
  </div>
</template>

<style scoped>
.landing { min-height: 100svh; background: var(--sand); }
.wrap { width: 100%; max-width: 1120px; margin: 0 auto; padding-left: 20px; padding-right: 20px; }
.top {
  position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 16px;
  padding: calc(12px + env(safe-area-inset-top)) max(20px, calc((100% - 1080px) / 2)) 12px;
  background: color-mix(in srgb, var(--sand) 85%, transparent); backdrop-filter: saturate(1.4) blur(16px);
  border-bottom: 1px solid color-mix(in srgb, var(--line) 70%, transparent);
}
.links { flex: 1; display: flex; justify-content: center; gap: 4px; }
.links a { padding: 10px 14px; border-radius: var(--r-m); color: var(--ink-soft); text-decoration: none; font-weight: 500; font-size: 15px; transition: color var(--dur-press) ease, background-color var(--dur-press) ease; }
@media (hover: hover) and (pointer: fine) { .links a:hover { color: var(--sea-deep); background: var(--sand-dark); } }
@media (max-width: 720px) { .links { display: none; } .top { justify-content: space-between; } }

h2 { font-size: clamp(24px, 3vw, 32px); line-height: 1.15; letter-spacing: -0.02em; margin-bottom: 28px; max-width: 22ch; }
section { padding-top: 88px; }

.hero { display: grid; grid-template-columns: 1.05fr .95fr; gap: 56px; align-items: center; padding-top: 72px; }
.pitch { display: grid; gap: 22px; align-content: center; }
.hero h1 { font-size: clamp(36px, 5.2vw, 60px); line-height: 1.02; letter-spacing: -0.035em; max-width: 13ch; }
.lede { font-size: 19px; line-height: 28px; color: var(--ink-soft); max-width: 46ch; }
.ctas { display: flex; flex-wrap: wrap; gap: 12px; }
.cta { min-height: 54px; padding: 0 24px; font-size: 17px; }
.demo { position: relative; }
.demo::before { content: ''; position: absolute; inset: 8% -6% -8% 8%; border-radius: 28px; background: var(--tint); z-index: -1; }
/* entrée du premier écran : une seule fois, à l'arrivée */
.pitch > * { animation: rise 600ms var(--ease-out) both; }
.pitch > :nth-child(2) { animation-delay: 80ms; } .pitch > :nth-child(3) { animation-delay: 160ms; } .pitch > :nth-child(4) { animation-delay: 220ms; }
.demo { animation: rise 700ms 200ms var(--ease-out) both; }
@media (max-width: 900px) { .hero { grid-template-columns: 1fr; gap: 40px; padding-top: 40px; } .demo::before { inset: 6% -3% -5% 5%; } }

.moments { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(3, 1fr); border-top: 2px solid var(--sea-deep); }
.moment { padding: 24px 24px 8px 0; display: grid; gap: 12px; align-content: start; }
.moment + .moment { padding-left: 24px; border-left: 1px solid var(--line); }
.m-ico { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: var(--tint); color: var(--accent-deep); }
.moment ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 8px; color: var(--ink-soft); }
.moment li li::before { content: '—'; color: var(--line); margin-right: 8px; }
@media (max-width: 760px) { .moments { grid-template-columns: 1fr; } .moment, .moment + .moment { padding: 22px 0; border-left: none; border-bottom: 1px solid var(--line); } }

.modules { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; column-gap: 48px; }
.modules > li { display: flex; gap: 16px; padding: 22px 0; border-top: 1px solid var(--line); }
.modules h3 { margin-bottom: 4px; font-size: 17px; }
.mod-ico { color: var(--sea-deep); flex: none; margin-top: 2px; }
@media (max-width: 760px) { .modules { grid-template-columns: 1fr; } }

.sub { max-width: 60ch; margin: -14px 0 28px; }
.table-wrap { overflow-x: auto; }
.offres { width: 100%; border-collapse: separate; border-spacing: 0; }
.offres th, .offres td { padding: 14px 16px; border-bottom: 1px solid var(--line); text-align: center; vertical-align: middle; }
.offres tbody th { text-align: left; display: grid; gap: 2px; font-weight: 400; }
.offres thead th { vertical-align: bottom; text-align: left; padding-top: 20px; }
.offres thead th > span { display: block; }
.offres tfoot td { border-bottom: none; padding-top: 20px; }
.f { font-weight: 500; }
.hl { background: var(--sand-dark); }
.offres thead th.hl { border-radius: var(--r-l) var(--r-l) 0 0; }
.offres tfoot td.hl { border-radius: 0 0 var(--r-l) var(--r-l); }
.pick { margin-bottom: 10px; display: inline-flex !important; }
.o-name { font-size: 20px; font-weight: 600; color: var(--sea-deep); }
.o-pitch { font-weight: 400; color: var(--ink-soft); font-size: 14px; line-height: 20px; margin: 4px 0 10px; }
.o-price { font-family: var(--font-data); font-size: 14px; color: var(--ink); }
.yes { color: var(--ok); }
.no { color: var(--line); }
.offres-mobile { display: none; list-style: none; margin: 0; padding: 0; gap: 16px; }
.om { border: 1px solid var(--line); border-radius: var(--r-l); padding: 20px; display: grid; gap: 8px; }
.om.hl { border-color: var(--sea-deep); background: var(--sand); box-shadow: var(--shadow); }
.om-head { display: flex; justify-content: space-between; align-items: center; }
.om-list { list-style: none; margin: 8px 0 12px; padding: 0; display: grid; gap: 8px; }
.om-list li { display: flex; gap: 10px; align-items: flex-start; }
@media (max-width: 760px) { .table-wrap { display: none; } .offres-mobile { display: grid; } }

.faq { max-width: 760px; }
.qa { border-top: 1px solid var(--line); }
.qa:last-child { border-bottom: 1px solid var(--line); }
.qa summary { list-style: none; cursor: pointer; padding: 18px 40px 18px 0; font-weight: 600; position: relative; min-height: 44px; }
.qa summary::-webkit-details-marker { display: none; }
.qa summary::after { content: ''; position: absolute; right: 8px; top: 50%; width: 10px; height: 10px; border-right: 1.7px solid var(--ink-soft); border-bottom: 1.7px solid var(--ink-soft); transform: translateY(-70%) rotate(45deg); transition: transform var(--dur-ui) var(--ease-out); }
.qa[open] summary::after { transform: translateY(-30%) rotate(-135deg); }
.qa p { padding: 0 0 20px; max-width: 62ch; }

.final { display: grid; justify-items: start; gap: 8px; padding-bottom: 96px; }
.final h2 { margin-bottom: 16px; }
.foot { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding-top: 28px; padding-bottom: calc(28px + env(safe-area-inset-bottom)); border-top: 1px solid var(--line); }

.reveal { opacity: 0; transform: translateY(16px); transition: opacity 600ms var(--ease-out), transform 600ms var(--ease-out); }
.reveal.in { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) { .pitch > *, .demo { animation: none; } }
</style>
