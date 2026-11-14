<script setup>
import { CircleCheck, TriangleAlert } from 'lucide-vue-next'
import { toastState } from '../toast'
</script>

<template>
  <div class="toast-zone" role="status" aria-live="polite">
    <Transition name="toast">
      <p v-if="toastState.text" :key="toastState.key" class="toast" :class="toastState.kind">
        <component :is="toastState.kind === 'ok' ? CircleCheck : TriangleAlert" :size="20" :stroke-width="1.7" />
        {{ toastState.text }}
      </p>
    </Transition>
  </div>
</template>

<style scoped>
.toast-zone { position: fixed; left: 0; right: 0; bottom: calc(84px + env(safe-area-inset-bottom)); display: flex; justify-content: center; pointer-events: none; z-index: 50; padding: 0 16px; }
.toast {
  display: flex; align-items: center; gap: 10px; margin: 0; padding: 12px 16px; max-width: 480px;
  border-radius: var(--r-m); background: var(--ink); color: var(--sand); font-weight: 500; box-shadow: var(--shadow);
}
.toast.ok svg { color: var(--tint-strong); }
.toast.alert svg { color: #FF9B8E; }
.toast-enter-active { transition: transform 240ms var(--ease-out), opacity 240ms var(--ease-out); }
.toast-leave-active { transition: opacity 160ms ease-in; }
.toast-enter-from { transform: translateY(12px); opacity: 0; }
.toast-leave-to { opacity: 0; }
@media (min-width: 900px) { .toast-zone { bottom: 24px; } }
</style>
