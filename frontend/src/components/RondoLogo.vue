<script setup>
defineProps({ size: { type: Number, default: 24 }, wordmark: { type: Boolean, default: true } })
</script>

<template>
  <span class="logo" :style="{ fontSize: size + 'px' }">
    <!-- le « o » posé sur sa ligne, comme le º de « N° » qui numérote chaque entrée du registre -->
    <svg viewBox="96 76 320 360" :width="size" :height="size" aria-hidden="true">
      <circle cx="256" cy="232" r="118" fill="none" stroke="currentColor" stroke-width="46" />
      <rect x="115" y="394" width="282" height="30" rx="15" fill="currentColor" />
    </svg>
    <span v-if="wordmark" class="word">rondo</span>
    <span v-else class="visually-hidden">rondo</span>
  </span>
</template>

<style scoped>
.logo { display: inline-flex; align-items: center; gap: .3em; color: var(--sea-deep); }
.word { font-weight: 600; letter-spacing: -0.045em; line-height: 1; }
</style>
