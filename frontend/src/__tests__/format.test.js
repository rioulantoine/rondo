import { describe, it, expect } from 'vitest'
import { num, entryNo, shiftMonth, dlcState, daysUntil, slugify } from '../format'

describe('format', () => {
  it('écrit les chiffres comme la charte', () => {
    expect(num(-12)).toBe('−12,0')
    expect(num(2.8)).toBe('2,8')
    expect(num(null)).toBe('—')
    expect(entryNo(142)).toBe('0142')
  })

  it('change de mois, y compris en janvier', () => {
    expect(shiftMonth('2026-01', -1)).toBe('2025-12')
    expect(shiftMonth('2026-12', 1)).toBe('2027-01')
  })

  it('reprend les seuils DLC de la v1', () => {
    expect(daysUntil('2026-11-18', '2026-11-15')).toBe(3)
    expect(dlcState(3).cls).toBe('warn')
    expect(dlcState(0).label).toBe('À jeter aujourd’hui')
    expect(dlcState(-1).cls).toBe('alert')
    expect(dlcState(4).cls).toBe('ok')
  })

  it('déduit le code comme le serveur', () => {
    expect(slugify('  Sumo Beach! ')).toBe('sumo-beach')
    expect(slugify('Chez Lulu — Crêperie')).toBe('chez-lulu-creperie')
  })
})
