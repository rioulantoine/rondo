<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { Delete } from 'lucide-vue-next'

const props = defineProps({ busy: Boolean, error: String })
const emit = defineEmits(['complete'])
const pin = ref('')

function press(d) {
  if (props.busy || pin.value.length >= 4) return
  pin.value += d
  if (pin.value.length === 4) emit('complete', pin.value)
}
function erase() {
  pin.value = pin.value.slice(0, -1)
}
// un code refusé s'efface pour laisser recommencer
watch(() => props.error, (e) => { if (e) pin.value = '' })

function onKey(e) {
  if (/^\d$/.test(e.key)) press(e.key)
  else if (e.key === 'Backspace') erase()
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="pinpad">
    <div class="dots" :class="{ shake: error }" role="img" :aria-label="`${pin.length} chiffre(s) sur 4`">
      <span v-for="i in 4" :key="i" class="dot" :class="{ on: i <= pin.length }" />
    </div>
    <p class="error-text msg" role="alert">{{ error || ' ' }}</p>
    <div class="keys">
      <button v-for="d in ['1','2','3','4','5','6','7','8','9']" :key="d" type="button" class="key data" @click="press(d)">{{ d }}</button>
      <span />
      <button type="button" class="key data" @click="press('0')">0</button>
      <button type="button" class="key erase" aria-label="Effacer" @click="erase"><Delete :size="24" :stroke-width="1.7" /></button>
    </div>
  </div>
</template>

<style scoped>
.pinpad { display: grid; justify-items: center; gap: 12px; }
.dots { display: flex; gap: 18px; padding: 8px; }
.dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--sea-deep); }
.dot.on { background: var(--sea-deep); }
.shake { animation: shake 320ms var(--ease-out); }
@keyframes shake { 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }
.msg { min-height: 20px; text-align: center; }
.keys { display: grid; grid-template-columns: repeat(3, 76px); gap: 12px; }
/* gestes répétés : pas d'animation, juste un changement de fond immédiat */
.key {
  height: 64px; border-radius: var(--r-l); border: 1px solid transparent; box-shadow: 0 0 0 .5px var(--hairline), 0 1px 2px rgb(28 42 74 / .05); background: var(--sand);
  color: var(--ink); font-size: 24px; font-weight: 500; display: grid; place-items: center;
}
.key:active { background: var(--tint); }
.erase { border-color: transparent; color: var(--ink-soft); }
</style>
