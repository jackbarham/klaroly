import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { element, jsonResponse, settle } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'
import AccountDevicesView from '@/views/account/AccountDevicesView.vue'
import type { Device } from '@/types/auth'

// Three states, and only one of them is the happy one. An empty list is the
// ordinary state for someone who only uses the web app, nothing is current
// when the caller is a session, and a revoke that failed must leave the row
// where it is: a device that is still signed in is the wrong thing to hide.

const fetchMock = vi.fn<typeof fetch>()

const mount = mountWithCleanup()

beforeEach(() => {
  fetchMock.mockReset()
  vi.stubGlobal('fetch', fetchMock)
  document.cookie = 'XSRF-TOKEN=token'
})

afterEach(() => {
  vi.unstubAllGlobals()
})

function device(overrides: Partial<Device> = {}): Device {
  return {
    id: 1,
    name: 'Ellie\'s iPhone',
    last_used_at: '2026-09-04T09:00:00Z',
    expires_at: '2027-09-04T09:00:00Z',
    created_at: '2026-09-01T09:00:00Z',
    current: false,
    ...overrides,
  }
}

async function open(devices: Device[]): Promise<Mounted> {
  fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: devices }))

  const mounted = await mount(AccountDevicesView, '/account/devices')
  await settle()

  return mounted
}

describe('the devices screen', () => {
  it('says why the list is empty rather than showing nothing', async () => {
    const { host } = await open([])

    expect(host.querySelectorAll('li')).toHaveLength(0)
    expect(host.textContent).toContain('No devices yet.')
  })

  it('marks the current device and gives it no way to revoke itself', async () => {
    const { host } = await open([
      device({ id: 1, name: 'Ellie\'s iPhone', current: true }),
      device({ id: 2, name: 'Ellie\'s iPad' }),
    ])

    const rows = [...host.querySelectorAll('li')]

    expect(rows).toHaveLength(2)
    expect(rows[0].textContent).toContain('This device')
    expect(rows[0].querySelector('button')).toBeNull()
    expect(rows[1].querySelector('button')).not.toBeNull()
  })

  it('marks nothing when the caller is a session rather than a token', async () => {
    // Every row comes back with current false, which is what the API answers
    // a browser. Inventing a current row here would be a lie about which
    // credential is reading the page.
    const { host } = await open([device({ id: 1 }), device({ id: 2, name: 'Ellie\'s iPad' })])

    expect(host.textContent).not.toContain('This device')
    expect(host.querySelectorAll('button')).toHaveLength(2)
  })

  it('names the device in each revoke button, so three of them are told apart', async () => {
    const { host } = await open([
      device({ id: 1, name: 'Ellie\'s iPhone' }),
      device({ id: 2, name: 'Ellie\'s iPad' }),
    ])

    const labels = [...host.querySelectorAll('button')].map((button) => button.getAttribute('aria-label'))

    expect(labels).toEqual(['Revoke Ellie\'s iPhone', 'Revoke Ellie\'s iPad'])
  })

  it('shows when a device was signed in if it has never been used', async () => {
    const { host } = await open([device({ last_used_at: null, created_at: '2026-09-01T09:00:00Z' })])

    expect(host.textContent).toContain('Signed in 1 September 2026')
  })

  it('takes a revoked device out of the list', async () => {
    const { host } = await open([
      device({ id: 1, name: 'Ellie\'s iPhone' }),
      device({ id: 2, name: 'Ellie\'s iPad' }),
    ])

    fetchMock.mockResolvedValueOnce(jsonResponse(204))
    element<HTMLButtonElement>(host, 'button').click()
    await settle()

    const rows = [...host.querySelectorAll('li')]

    expect(rows).toHaveLength(1)
    expect(rows[0].textContent).toContain('Ellie\'s iPad')
  })

  it('keeps a device that could not be revoked, and says which', async () => {
    const { host } = await open([device({ id: 1, name: 'Ellie\'s iPhone' })])

    fetchMock.mockResolvedValueOnce(jsonResponse(500, { message: 'No.' }))
    element<HTMLButtonElement>(host, 'button').click()
    await settle()

    expect(host.querySelectorAll('li')).toHaveLength(1)
    expect(element(host, '[role="alert"]').textContent?.trim())
      .toBe('Ellie\'s iPhone could not be revoked. Try again.')
  })
})
