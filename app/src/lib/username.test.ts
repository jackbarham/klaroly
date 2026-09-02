import { describe, expect, it } from 'vitest'
import { deriveUsername } from '@/lib/username'

describe('deriveUsername', () => {
  it('lowercases and keeps only letters and digits', () => {
    expect(deriveUsername('Ellie Marsh Makeup')).toBe('elliemarshmakeup')
  })

  it('drops leading digits', () => {
    expect(deriveUsername('123 Studio')).toBe('studio')
  })

  it('leaves an empty result empty', () => {
    expect(deriveUsername('123 !!!')).toBe('')
    expect(deriveUsername('')).toBe('')
  })
})
