<script setup>
import { computed, watchEffect } from 'vue'
import { useRoute } from 'vue-router'
import { PAGES, LIENS, MAJ, incomplet } from '../legal'
import RondoLogo from '../components/RondoLogo.vue'

const route = useRoute()
const page = computed(() => PAGES[route.params.page])
watchEffect(() => { if (page.value) document.title = `${page.value.title} | Rondo` })
</script>

<template>
  <div class="legal">
    <header class="top">
      <RouterLink to="/bienvenue" class="home" aria-label="Accueil de Rondo"><RondoLogo :size="22" /></RouterLink>
      <nav aria-label="Pages légales">
        <RouterLink v-for="l in LIENS" :key="l.to" :to="l.to" :aria-current="route.path === l.to ? 'page' : undefined">{{ l.label }}</RouterLink>
      </nav>
    </header>

    <main class="doc">
      <p v-if="incomplet" class="todo" role="note">
        Page à compléter avant la mise en ligne : l’identité de l’éditeur (raison sociale, SIRET, adresse, hébergeur) n’est pas encore renseignée dans <code>src/legal.js</code>.
      </p>
      <h1>{{ page.title }}</h1>
      <p class="caption">Dernière mise à jour : {{ MAJ }}</p>
      <section v-for="s in page.sections" :key="s.h">
        <h2>{{ s.h }}</h2>
        <p v-for="(t, i) in s.p" :key="i">{{ t }}</p>
      </section>
    </main>
  </div>
</template>

<style scoped>
.legal { min-height: 100svh; background: var(--sand); }
.top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 24px; max-width: 760px; margin: 0 auto; padding: calc(16px + env(safe-area-inset-top)) 20px 16px; border-bottom: 1px solid var(--line); }
.home { display: flex; text-decoration: none; min-height: 44px; align-items: center; }
nav { display: flex; flex-wrap: wrap; gap: 4px 20px; }
nav a { min-height: 44px; display: inline-flex; align-items: center; color: var(--ink-soft); text-decoration: none; font-size: 15px; }
nav a[aria-current='page'] { color: var(--sea-deep); font-weight: 600; }
@media (hover: hover) and (pointer: fine) { nav a:hover { color: var(--sea-deep); } }
.doc { max-width: 760px; margin: 0 auto; padding: 40px 20px calc(72px + env(safe-area-inset-bottom)); }
.doc h1 { margin-bottom: 8px; }
section { margin-top: 36px; }
section h2 { margin-bottom: 10px; }
section p { max-width: 68ch; color: var(--ink); }
section p + p { margin-top: 10px; }
.todo { margin-bottom: 28px; padding: 12px 14px; border: 1px solid var(--warn); border-radius: var(--r-m); background: var(--warn-bg); color: var(--warn); font-size: 14px; line-height: 20px; }
.todo code { font-family: var(--font-data); font-size: 13px; }
</style>
