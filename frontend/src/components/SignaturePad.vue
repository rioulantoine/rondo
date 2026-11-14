<script setup>
import { ref, onMounted } from 'vue'

const emit = defineEmits(['change'])
const canvas = ref(null)
const empty = ref(true)
let ctx, drawing = false, last = null

onMounted(() => {
  const c = canvas.value
  const ratio = Math.min(window.devicePixelRatio || 1, 2)
  c.width = c.clientWidth * ratio
  c.height = c.clientHeight * ratio
  ctx = c.getContext('2d')
  ctx.scale(ratio, ratio)
  ctx.lineWidth = 2.2
  ctx.lineCap = ctx.lineJoin = 'round'
  ctx.strokeStyle = '#1C2A4A' // encre, aussi en sombre : la signature est exportée sur fond blanc
})

const pos = (e) => {
  const r = canvas.value.getBoundingClientRect()
  return [e.clientX - r.left, e.clientY - r.top]
}
function down(e) {
  canvas.value.setPointerCapture(e.pointerId)
  drawing = true
  last = pos(e)
}
function move(e) {
  if (!drawing) return
  const p = pos(e)
  ctx.beginPath()
  ctx.moveTo(...last)
  ctx.lineTo(...p)
  ctx.stroke()
  last = p
  empty.value = false
}
function up() {
  if (!drawing) return
  drawing = false
  emit('change', empty.value ? null : exportImage())
}
function clear() {
  ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
  empty.value = true
  emit('change', null)
}
/** PNG réduit sur fond blanc (stocké avec le badgeage, comme la v1). */
function exportImage() {
  const out = document.createElement('canvas')
  out.width = 360
  out.height = Math.round((360 * canvas.value.height) / canvas.value.width)
  const o = out.getContext('2d')
  o.fillStyle = '#fff'
  o.fillRect(0, 0, out.width, out.height)
  o.drawImage(canvas.value, 0, 0, out.width, out.height)
  return out.toDataURL('image/png')
}
</script>

<template>
  <div class="sig">
    <canvas ref="canvas" class="pad" aria-label="Zone de signature" @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" />
    <span v-if="empty" class="hint caption">Signe ici avec le doigt</span>
    <button type="button" class="btn btn-ghost clear" :disabled="empty" @click="clear">Effacer</button>
  </div>
</template>

<style scoped>
.sig { position: relative; }
.pad { display: block; width: 100%; height: 180px; background: #fff; border: 1.5px dashed var(--line); border-radius: var(--r-l); touch-action: none; }
.hint { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; color: #5A606B; }
.clear { position: absolute; right: 4px; top: 4px; min-height: 36px; font-size: 14px; color: #5A606B; }
</style>
