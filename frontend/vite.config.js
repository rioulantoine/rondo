import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import { readFileSync } from 'node:fs'

/** Génère dist/sw.js avec la liste des fichiers du build et une version unique. */
function serviceWorker() {
  return {
    name: 'rondo-sw',
    apply: 'build',
    generateBundle(_, bundle) {
      const assets = Object.keys(bundle).map((f) => '/' + f)
      const source = readFileSync(new URL('./sw.template.js', import.meta.url), 'utf8')
        .replace('__VERSION__', Date.now().toString(36))
        .replace('__ASSETS__', JSON.stringify(['/manifest.webmanifest', '/favicon.svg', '/icon-192.png', ...assets]))
      this.emitFile({ type: 'asset', fileName: 'sw.js', source })
    },
  }
}

export default defineConfig({
  plugins: [vue(), serviceWorker()],
  server: {
    proxy: { '/api': 'http://localhost:8000' },
  },
})
