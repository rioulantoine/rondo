<script setup>
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { House, BookOpenCheck, CalendarDays, Clock4 } from 'lucide-vue-next'
import { api, session, setSession, initials } from './api'
import RondoLogo from './components/RondoLogo.vue'
import AppToast from './components/AppToast.vue'
import AppBanners from './components/AppBanners.vue'

const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
const menuEl = ref(null)

// le menu se ferme au clic dehors, avec Échap et au changement d'écran ; le focus va au premier choix
function onDocClick(e) {
  if (menuOpen.value && !e.target.closest('.me')) menuOpen.value = false
}
function onKey(e) {
  if (e.key === 'Escape' && menuOpen.value) menuOpen.value = false
}
watch(menuOpen, async (open) => {
  if (open) { await nextTick(); menuEl.value?.querySelector('[role=menuitem]')?.focus() }
})
watch(() => route.path, () => (menuOpen.value = false))
onMounted(() => { document.addEventListener('click', onDocClick); document.addEventListener('keydown', onKey) })
onBeforeUnmount(() => { document.removeEventListener('click', onDocClick); document.removeEventListener('keydown', onKey) })

// Navigation de la charte ; chaque entrée apparaît quand son module existe
const NAV = [
  { to: '/', label: 'Accueil', icon: House, exact: true },
  { to: '/registres', label: 'Registres', icon: BookOpenCheck },
  { to: '/planning', label: 'Planning', icon: CalendarDays },
  { to: '/pointer', label: 'Pointer', icon: Clock4 },
]
const nav = computed(() => NAV.filter((n) => router.getRoutes().some((r) => r.path === n.to)))
const active = (n) => (n.exact ? route.path === n.to : route.path.startsWith(n.to))

async function switchPerson() {
  menuOpen.value = false
  await api('/identity', { method: 'DELETE' }).catch(() => {})
  setSession({ person: null })
  router.replace('/qui')
}
async function logout() {
  menuOpen.value = false
  await api('/logout', { method: 'POST' }).catch(() => {})
  setSession({ restaurant: null, person: null })
  router.replace('/connexion')
}
</script>

<template>
  <a href="#contenu" class="skip">Aller au contenu</a>
  <AppBanners />
  <div v-if="route.meta.shell && session.person" class="shell">
    <header class="bar">
      <RouterLink to="/" class="home-link"><RondoLogo :size="22" /></RouterLink>
      <span class="resto">{{ session.restaurant?.name }}</span>
      <div class="me">
        <button class="visa me-btn" :aria-expanded="menuOpen" aria-haspopup="menu" @click="menuOpen = !menuOpen">
          {{ initials(session.person.name) }}<span class="visually-hidden">Menu de {{ session.person.name }}</span>
        </button>
        <Transition name="pop">
        <div v-if="menuOpen" ref="menuEl" class="menu panel" role="menu">
          <p class="menu-name">{{ session.person.name }}<span class="caption">{{ session.person.owner ? 'Responsable' : session.restaurant?.name }}</span></p>
          <RouterLink v-if="session.person.owner" role="menuitem" class="btn btn-ghost" to="/equipe" @click="menuOpen = false">Équipe et accès</RouterLink>
          <button role="menuitem" class="btn btn-ghost" @click="switchPerson">Changer de personne</button>
          <button role="menuitem" class="btn btn-ghost" @click="logout">Changer d'établissement</button>
        </div>
        </Transition>
      </div>
    </header>

    <nav class="nav" aria-label="Navigation principale">
      <RouterLink v-for="n in nav" :key="n.to" :to="n.to" class="nav-item" :class="{ on: active(n) }" :aria-current="active(n) ? 'page' : undefined">
        <component :is="n.icon" :size="22" :stroke-width="1.7" />
        <span>{{ n.label }}</span>
      </RouterLink>
    </nav>

    <main id="contenu" class="content" :class="{ wide: route.meta.wide }" tabindex="-1">
      <RouterView v-slot="{ Component }">
        <Transition name="page" mode="out-in"><component :is="Component" :key="route.path" /></Transition>
      </RouterView>
    </main>
  </div>
  <RouterView v-else v-slot="{ Component }">
    <Transition name="page" mode="out-in"><component :is="Component" :key="route.path" /></Transition>
  </RouterView>
  <AppToast />
</template>

<style scoped>
.shell { min-height: 100dvh; display: grid; grid-template-rows: auto 1fr auto; grid-template-areas: 'bar' 'content' 'nav'; }
.bar {
  grid-area: bar; position: sticky; top: 0; z-index: 10;
  display: flex; align-items: center; gap: 12px;
  padding: calc(10px + env(safe-area-inset-top)) 16px 10px;
  background: color-mix(in srgb, var(--sand) 92%, transparent); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--line);
}
.home-link { display: flex; text-decoration: none; }
.resto { flex: 1; font-weight: 600; color: var(--ink); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.me { position: relative; }
.me-btn { width: 44px; height: 44px; border: none; transition: box-shadow var(--dur-press) var(--ease-out); }
.me-btn[aria-expanded='true'] { box-shadow: 0 0 0 3px var(--tint-strong); }
.menu { position: absolute; right: 0; top: 52px; min-width: 260px; padding: 8px; display: grid; box-shadow: var(--shadow-lg); transform-origin: top right; z-index: 20; }
/* menu ancré au bouton : il s'ouvre depuis son coin */
.pop-enter-active { transition: opacity 160ms var(--ease-out), transform 160ms var(--ease-out); }
.pop-leave-active { transition: opacity 120ms ease, transform 120ms ease; }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: scale(.96); }
.menu .btn { justify-content: flex-start; font-weight: 500; }
.menu-name { display: grid; padding: 8px 20px 12px; font-weight: 600; border-bottom: 1px solid var(--line); margin-bottom: 4px; }
.content { grid-area: content; width: 100%; max-width: 960px; margin: 0 auto; padding: 24px 16px 32px; outline: none; }
.content.wide { max-width: 1440px; }
.nav {
  grid-area: nav; position: sticky; bottom: 0; z-index: 10;
  display: flex; justify-content: space-around;
  padding: 6px 8px calc(6px + env(safe-area-inset-bottom));
  background: color-mix(in srgb, var(--sand) 88%, transparent); backdrop-filter: saturate(1.4) blur(16px);
  border-top: 1px solid var(--line);
}
.nav-item {
  flex: 1; max-width: 120px; display: grid; justify-items: center; gap: 2px; padding: 6px 4px;
  border-radius: var(--r-m); color: var(--ink-soft); text-decoration: none; font-size: 12px; font-weight: 500;
  -webkit-touch-callout: none; transition: background-color var(--dur-press) ease, color var(--dur-press) ease;
}
.nav-item.on { color: var(--accent-deep); background: var(--tint); }
.nav-item.on span { font-weight: 600; }
@media (hover: hover) and (pointer: fine) { .nav-item:not(.on):hover { background: var(--sand-dark); color: var(--ink); } }
.skip { position: fixed; left: 12px; top: -60px; z-index: 100; padding: 10px 16px; border-radius: var(--r-m); background: var(--sea-deep); color: var(--on-sea); font-weight: 600; text-decoration: none; }
.skip:focus { top: calc(12px + env(safe-area-inset-top)); }

/* tablette et ordinateur : la navigation devient un rail */
@media (min-width: 900px) {
  .shell { grid-template-columns: 96px 1fr; grid-template-rows: auto 1fr; grid-template-areas: 'bar bar' 'nav content'; }
  .nav { position: sticky; top: 61px; align-self: start; height: calc(100dvh - 61px); flex-direction: column; justify-content: flex-start; gap: 8px; padding: 16px 8px; border-top: none; border-right: 1px solid var(--line); }
  .nav-item { flex: none; padding: 10px 4px; }
  .content { padding: 32px 32px 48px; }
}
</style>
