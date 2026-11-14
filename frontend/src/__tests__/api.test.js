import { describe, it, expect } from 'vitest'
import { initials, can, session } from '../api'

describe('visa', () => {
  it('reprend les deux premières lettres du prénom, comme la charte', () => {
    expect(initials('Léa Martin')).toBe('LÉ')
    expect(initials('  ')).toBe('?')
  })
})

describe('permissions', () => {
  it('le responsable a tout, les autres seulement leurs espaces', () => {
    session.person = { owner: false, perms: ['cuisine'] }
    expect(can('cuisine')).toBe(true)
    expect(can('bar')).toBe(false)
    session.person = { owner: true, perms: [] }
    expect(can('bar')).toBe(true)
  })
})
