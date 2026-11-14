import { reactive } from 'vue'

export class ApiError extends Error {
  constructor(message, status, code) {
    super(message)
    this.status = status
    this.code = code
  }
}

/** Appel au backend. Toute la donnée passe par /api : le navigateur ne parle jamais à Firebase. */
export async function api(path, { method = 'GET', body } = {}) {
  let res
  try {
    res = await fetch(`/api${path}`, {
      method,
      credentials: 'same-origin',
      headers: body === undefined ? {} : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError('Pas de réseau. Vérifie le Wi-Fi et réessaie.', 0)
  }
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    // session expirée côté serveur : on resynchronise (un PIN refusé n'en est pas une)
    if (res.status === 401 && !data.code && session.restaurant && path !== '/identity') await loadSession()
    throw new ApiError(data.error || 'Erreur inattendue, réessaie.', res.status, data.code)
  }
  return data
}

/** Établissement et personne connectés, partagés par toute l'appli. */
export const session = reactive({ ready: false, restaurant: null, person: null })

export async function loadSession() {
  try {
    Object.assign(session, await api('/session'))
  } catch {
    // hors ligne au démarrage : on garde ce qu'on avait
  }
  session.ready = true
}

export function setSession(data) {
  Object.assign(session, data)
}

export function can(perm) {
  return !!session.person && (session.person.owner || session.person.perms.includes(perm))
}

/** Visa de la charte : deux premières lettres du prénom (Léa → LÉ). */
export function initials(name = '') {
  return name.trim().slice(0, 2).toUpperCase() || '?'
}
