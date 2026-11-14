import { reactive } from 'vue'

export const toastState = reactive({ text: '', kind: 'ok', key: 0 })
let timer

/** Message bref en bas d'écran (« Relevé n° 0143 enregistré. »). */
export function toast(text, kind = 'ok') {
  clearTimeout(timer)
  Object.assign(toastState, { text, kind, key: toastState.key + 1 })
  timer = setTimeout(() => (toastState.text = ''), 3200)
}
