const TZ = 'Europe/Paris'

/** Nombre à la française avec le vrai signe moins de la charte : −12,5 */
export function num(v, digits = 1) {
  if (v === null || v === undefined || v === '' || Number.isNaN(Number(v))) return '—'
  return Number(v).toLocaleString('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits }).replace('-', '−')
}

export const entryNo = (n) => String(n).padStart(4, '0')

export const time = (iso) => new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: TZ })

export const day = (isoOrDate) =>
  new Date(isoOrDate.length === 10 ? isoOrDate + 'T12:00:00' : isoOrDate).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', timeZone: TZ })

export const dayLong = (d = new Date()) => d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: TZ })

export const monthLabel = (ym) => {
  const s = new Date(ym + '-15T12:00:00').toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
  return s[0].toUpperCase() + s.slice(1)
}

export function shiftMonth(ym, delta) {
  const [y, m] = ym.split('-').map(Number)
  const d = new Date(Date.UTC(y, m - 1 + delta, 1))
  return d.toISOString().slice(0, 7)
}

/** Date du jour à Paris (AAAA-MM-JJ), même règle que le serveur. */
export const todayParis = () => new Date().toLocaleDateString('sv-SE', { timeZone: TZ })

/** État DLC de la v1 : J-3 à dépassée. */
export function dlcState(daysLeft) {
  if (daysLeft < 0) return { cls: 'alert', label: 'DLC dépassée, à jeter' }
  if (daysLeft === 0) return { cls: 'alert', label: 'À jeter aujourd’hui' }
  if (daysLeft === 1) return { cls: 'warn', label: 'DLC demain' }
  if (daysLeft <= 3) return { cls: 'warn', label: `DLC dans ${daysLeft} jours` }
  return { cls: 'ok', label: 'DLC ok' }
}

export function daysUntil(date, today = todayParis()) {
  return Math.round((new Date(date + 'T12:00:00Z') - new Date(today + 'T12:00:00Z')) / 86400000)
}

/** Code d'établissement, même règle que la v1 et que le serveur (Team::slugify). */
export const slugify = (s = '') =>
  s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
