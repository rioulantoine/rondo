<script setup>
import { entryNo } from '../format'
import { initials } from '../api'

// Une ligne de registre : numéro, intitulé, détail, valeur contrôlée, visa. C'est la signature de Rondo.
defineProps({
  number: { type: Number, default: null },
  title: { type: String, required: true },
  sub: { type: String, default: '' },
  value: { type: String, default: '' },
  state: { type: String, default: '' }, // ok | warn | alert
  staff: { type: String, default: '' },
})
</script>

<template>
  <li class="entry">
    <span v-if="number !== null" class="no data" :aria-label="`Entrée numéro ${number}`">{{ entryNo(number) }}</span>
    <span class="text">
      <span class="title">{{ title }}</span>
      <span v-if="sub" class="caption">{{ sub }}</span>
      <slot />
    </span>
    <span v-if="value" class="value data" :class="state && `v-${state}`">{{ value }}</span>
    <span v-if="staff" class="visa" :title="staff">{{ initials(staff) }}</span>
    <slot name="end" />
  </li>
</template>

<style scoped>
.entry { display: flex; align-items: center; gap: 12px; padding: 12px 0; min-height: 64px; }
.no { width: 3.2em; flex: none; font-size: 13px; color: var(--ink-soft); }
.text { flex: 1; min-width: 0; display: grid; gap: 2px; }
.title { font-weight: 500; overflow-wrap: anywhere; }
.text :slotted(.state) { justify-self: start; margin-top: 4px; }
.value { font-size: 20px; line-height: 24px; font-weight: 500; color: var(--ink); white-space: nowrap; }
.v-alert { color: var(--alert); }
.v-warn { color: var(--warn); }
</style>
