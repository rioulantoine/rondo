<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { CircleCheck } from 'lucide-vue-next'

// Démonstration : un registre du jour qui se remplit, entrée par entrée (données d'exemple de la charte).
const ENTRIES = [
  { n: '0141', title: 'Frigo garde-manger', sub: '08:12', value: '2,8°', visa: 'LÉ' },
  { n: '0142', title: 'Congélateur bar', sub: '08:20 · porte vérifiée', value: '−12°', state: 'alert', visa: 'TO' },
  { n: '0143', title: 'Maintien au chaud', sub: '11:45', value: '67°', visa: 'HU' },
  { n: '0144', title: 'Crème liquide 35 %', sub: 'DLC demain · lot 24-118', tag: 'DLC demain', visa: 'LÉ' },
  { n: '0145', title: 'Plat témoin prélevé', sub: '12:02', value: '3,1°', visa: 'HU' },
]
const props = defineProps({ title: { type: String, default: 'Relevés du jour' } })
const shown = ref(0)
const saved = ref('')
let timer

function tick() {
  if (shown.value < ENTRIES.length) {
    shown.value++
    saved.value = `Relevé n° ${ENTRIES[shown.value - 1].n} enregistré.`
    timer = setTimeout(tick, 1600)
  } else {
    // pause sur le registre complet, puis le service recommence (rondo)
    timer = setTimeout(() => { shown.value = 0; saved.value = ''; timer = setTimeout(tick, 600) }, 4200)
  }
}
onMounted(() => {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) shown.value = ENTRIES.length
  else timer = setTimeout(tick, 500)
})
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <figure class="live" aria-label="Exemple de registre Rondo">
    <figcaption class="head">
      <span class="t">{{ props.title }}</span>
      <span class="caption">Exemple · sam. 15/11</span>
    </figcaption>
    <TransitionGroup tag="ol" name="entry" class="rows">
      <li v-for="e in ENTRIES.slice(0, shown)" :key="e.n" class="row">
        <span class="no data">{{ e.n }}</span>
        <span class="txt"><span class="title">{{ e.title }}</span><span class="caption">{{ e.sub }}</span></span>
        <span v-if="e.value" class="val data" :class="e.state">{{ e.value }}</span>
        <span v-else class="state state-warn">{{ e.tag }}</span>
        <span class="visa">{{ e.visa }}</span>
      </li>
    </TransitionGroup>
    <div class="foot">
      <Transition name="saved" mode="out-in">
        <span v-if="saved" :key="saved" class="saved"><CircleCheck :size="16" :stroke-width="1.8" /> {{ saved }}</span>
        <span v-else class="caption">Ouverture, service, fermeture.</span>
      </Transition>
    </div>
  </figure>
</template>

<style scoped>
.live { margin: 0; background: var(--sand); border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--shadow-lg); overflow: hidden; }
.head { display: flex; justify-content: space-between; align-items: baseline; padding: 16px 18px 12px; border-bottom: 1px solid var(--line); }
.t { font-weight: 600; color: var(--sea-deep); }
.rows { list-style: none; margin: 0; padding: 0 18px; min-height: calc(5 * 61px); }
.row { display: flex; align-items: center; gap: 12px; min-height: 61px; border-bottom: 1px solid var(--line); }
.row:last-child { border-bottom: none; }
.no { width: 3em; font-size: 12px; color: var(--ink-soft); flex: none; }
.txt { flex: 1; min-width: 0; display: grid; }
.title { font-weight: 500; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.val { font-size: 18px; font-weight: 500; }
.val.alert { color: var(--alert); }
.visa { width: 30px; height: 30px; font-size: 11px; }
.foot { padding: 12px 18px; background: var(--sand-dark); border-top: 1px solid var(--line); min-height: 46px; display: flex; align-items: center; }
.saved { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; color: var(--ok); }
/* une entrée s'inscrit : elle arrive d'un léger décalage et se pose (charte : 160–320 ms, ease-out) */
.entry-enter-active { transition: opacity var(--dur-enter) var(--ease-out), transform var(--dur-enter) var(--ease-out), background-color 1.2s ease; }
.entry-enter-from { opacity: 0; transform: translateY(-10px); background-color: var(--tint); }
.entry-leave-active { transition: opacity 240ms ease; position: absolute; }
.entry-leave-to { opacity: 0; }
.saved-enter-active, .saved-leave-active { transition: opacity 200ms ease, transform 200ms var(--ease-out); }
.saved-enter-from { opacity: 0; transform: translateY(4px); }
.saved-leave-to { opacity: 0; }
</style>
