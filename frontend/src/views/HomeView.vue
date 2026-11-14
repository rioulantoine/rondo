<script setup>
import { computed } from 'vue'
import { session } from '../api'

const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const firstName = computed(() => (session.person.owner ? '' : ' ' + session.person.name.split(' ')[0]))
</script>

<template>
  <div class="stack">
    <p class="caption date">{{ today }}</p>
    <h1>Bonjour{{ firstName }}</h1>
  </div>
</template>

<style scoped>
.date { text-transform: capitalize; margin-bottom: -8px; }
</style>
