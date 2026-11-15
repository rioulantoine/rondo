<script setup>
import { useId } from 'vue'
import { CircleCheck } from 'lucide-vue-next'

// Scène de service : la cloche se lève au passe, la vapeur monte, l'assiette porte le symbole Rondo
// (le « o » posé sur sa ligne : la ligne, c'est le bord de l'assiette). Boucle douce de 8 s.
defineProps({ onDark: { type: Boolean, default: false } })
// identifiants propres à chaque scène : deux scènes sur la page ne partagent pas leurs dégradés
const id = useId()
</script>

<template>
  <div class="scene" :class="{ dark: onDark }" role="img" aria-label="Une cloche se soulève sur une assiette, au passe : le service commence.">
    <svg viewBox="0 -60 400 360" aria-hidden="true">
      <defs>
        <linearGradient :id="`kc-dome-${id}`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#F4F6FA" />
          <stop offset=".45" stop-color="#C9D0DC" />
          <stop offset="1" stop-color="#8E99AD" />
        </linearGradient>
        <linearGradient :id="`kc-shine-${id}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#FFFFFF" stop-opacity=".9" />
          <stop offset="1" stop-color="#FFFFFF" stop-opacity="0" />
        </linearGradient>
        <radialGradient :id="`kc-glow-${id}`" cx=".5" cy=".5" r=".5">
          <stop offset="0" stop-color="#BFE3DC" stop-opacity=".55" />
          <stop offset="1" stop-color="#BFE3DC" stop-opacity="0" />
        </radialGradient>
      </defs>

      <!-- halo chaud derrière l'assiette, qui s'allume quand la cloche se lève -->
      <ellipse class="glow" cx="200" cy="200" rx="150" ry="95" :fill="`url(#kc-glow-${id})`" />

      <!-- vapeur -->
      <g class="steam" fill="none" stroke-linecap="round" stroke-width="5">
        <path class="s1" d="M168 196 C 156 172, 182 160, 170 136 S 178 104, 168 86" />
        <path class="s2" d="M200 190 C 188 164, 214 150, 202 124 S 210 90, 200 70" />
        <path class="s3" d="M232 196 C 220 172, 246 160, 234 136 S 242 104, 232 86" />
      </g>

      <!-- le symbole Rondo, posé sur l'assiette -->
      <circle class="mark" cx="200" cy="188" r="30" fill="none" stroke-width="11" />

      <!-- assiette (sa tranche fait la ligne du symbole) -->
      <ellipse cx="200" cy="232" rx="120" ry="15" class="plate-shadow" />
      <path class="plate" d="M86 226 Q 200 246 314 226 L 304 232 Q 200 252 96 232 Z" />
      <rect class="plate-line" x="122" y="222" width="156" height="7" rx="3.5" />

      <!-- passe -->
      <rect class="pass" x="20" y="250" width="360" height="10" rx="5" />

      <!-- cloche -->
      <g class="cloche">
        <path d="M84 228 A 116 104 0 0 1 316 228 Z" :fill="`url(#kc-dome-${id})`" />
        <path d="M110 214 A 92 82 0 0 1 176 140" fill="none" :stroke="`url(#kc-shine-${id})`" stroke-width="8" stroke-linecap="round" />
        <rect x="78" y="224" width="244" height="9" rx="4.5" fill="#AEB7C6" />
        <rect x="193" y="112" width="14" height="14" rx="3" fill="#9AA5B7" />
        <circle cx="200" cy="108" r="12" :fill="`url(#kc-dome-${id})`" stroke="#9AA5B7" stroke-width="2" />
      </g>
    </svg>

    <p class="ticket"><CircleCheck :size="16" :stroke-width="2" /> Plat témoin · <span class="data">63 °C</span> · Conforme</p>
  </div>
</template>

<style scoped>
/* couleurs prises sur les jetons : encre en clair, céladon en sombre */
.scene { position: relative; width: 100%; max-width: 440px; margin: 0 auto; --mark: var(--sea-deep); --steam: color-mix(in srgb, var(--ink) 24%, transparent); --pass: var(--sea-deep); }
.scene.dark { --mark: #BFE3DC; --steam: rgb(255 255 255 / .38); --pass: rgb(191 227 220 / .5); }
svg { display: block; width: 100%; height: auto; overflow: visible; }

.mark { stroke: var(--mark); }
.plate { fill: #FFFFFF; }
.plate-line { fill: var(--mark); }
.plate-shadow { fill: rgb(0 0 0 / .12); }
.pass { fill: var(--pass); opacity: .9; }
.steam path { stroke: var(--steam); stroke-dasharray: 160; stroke-dashoffset: 160; opacity: 0; }

/* tout tient dans une boucle de 8 s : repos, la cloche se lève (léger rebond), vapeur, retour */
.cloche { transform-origin: 316px 228px; animation: lift 8s var(--ease-out) infinite; }
@keyframes lift {
  0%, 14% { transform: none; }
  26% { transform: translate(18px, -118px) rotate(14deg); animation-timing-function: var(--ease-back); }
  30%, 74% { transform: translate(22px, -126px) rotate(16deg); }
  88%, 100% { transform: none; }
}
.glow { opacity: 0; transform-origin: 200px 200px; animation: glow 8s ease-out infinite; }
@keyframes glow { 0%, 18% { opacity: 0; transform: scale(.85); } 32%, 72% { opacity: 1; transform: none; } 86%, 100% { opacity: 0; } }
.mark { transform-origin: 200px 188px; animation: mark 8s var(--ease-out) infinite; }
@keyframes mark { 0%, 20% { transform: scale(.9); } 32%, 74% { transform: none; } 90%, 100% { transform: scale(.9); } }
.steam .s1 { animation: steam 8s ease-out infinite 0s; }
.steam .s2 { animation: steam 8s ease-out infinite .35s; }
.steam .s3 { animation: steam 8s ease-out infinite .7s; }
@keyframes steam {
  0%, 26% { stroke-dashoffset: 160; opacity: 0; transform: none; }
  40% { opacity: 1; }
  66% { stroke-dashoffset: 0; opacity: .8; }
  80%, 100% { stroke-dashoffset: 0; opacity: 0; transform: translateY(-14px); }
}

.ticket {
  position: absolute; left: 50%; bottom: -6px; transform: translateX(-50%);
  display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; margin: 0;
  padding: 8px 14px; border-radius: 999px; font-size: 14px; font-weight: 500;
  color: var(--ok); background: color-mix(in srgb, var(--sand) 88%, transparent);
  -webkit-backdrop-filter: var(--material-filter); backdrop-filter: var(--material-filter);
  box-shadow: 0 0 0 .5px var(--hairline), var(--shadow);
  animation: ticket 8s var(--ease-out) infinite;
}
@keyframes ticket {
  0%, 34% { opacity: 0; transform: translate(-50%, 10px) scale(.96); }
  42%, 74% { opacity: 1; transform: translate(-50%, 0); }
  84%, 100% { opacity: 0; transform: translate(-50%, 6px); }
}

/* mouvement réduit : la scène reste figée, cloche levée, assiette servie */
@media (prefers-reduced-motion: reduce) {
  .cloche, .glow, .mark, .steam path, .ticket { animation: none; }
  .cloche { transform: translate(22px, -126px) rotate(16deg); }
  .glow { opacity: 1; }
  .steam path { stroke-dashoffset: 0; opacity: .7; }
  .ticket { opacity: 1; }
}
</style>
