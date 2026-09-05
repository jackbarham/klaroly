import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { sampleMe } from '@/lib/auth.sample'
import { element, jsonResponse, settle, submitForm, typeInto } from '@/lib/testHelpers'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'
import { useAuthStore } from '@/stores/auth'
import AccountDetailsView from '@/views/account/AccountDetailsView.vue'
import type { Me } from '@/types/auth'

// One form, two endpoints. What would break quietly here is a save that
// touches the profile endpoint when nobody edited the profile, which
// un-verifies an address for no reason, and a failure of the second request
// that reads as though the first failed too, which sends someone back to
// re-enter what is already saved.

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

function me(overrides: { role?: string } = {}): Me {
  const value = structuredClone(sampleMe) as Me

  if (overrides.role) {
    value.membership.role = overrides.role
  }

  return value
}

interface Screen extends Mounted {
  name: HTMLInputElement
  email: HTMLInputElement
  business: HTMLInputElement
}

async function open(overrides: { role?: string } = {}): Promise<Screen> {
  const mounted = await mount(AccountDetailsView, '/account/details')

  // The real store is filled by bootstrap() before the first navigation, so a
  // view never sees it empty. Here there is no guard to do that, so the test
  // fills it and lets the view's watcher pick it up, which is the same path a
  // save takes when it replaces me with what the API answered.
  useAuthStore().me = me(overrides)
  await nextTick()

  const inputs = [...mounted.host.querySelectorAll('input')]

  return {
    ...mounted,
    name: inputs[0] as HTMLInputElement,
    email: inputs[1] as HTMLInputElement,
    business: inputs[2] as HTMLInputElement,
  }
}

function pathsCalled(): string[] {
  return fetchMock.mock.calls.map((call) => String(call[0]).replace(import.meta.env.VITE_API_URL, ''))
}

describe('the details screen', () => {
  it('sends only the profile when only the profile changed', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: sampleMe }))

    const screen = await open()

    typeInto(screen.name, 'Ellie Marsh-Doyle')
    submitForm(screen.host)
    await settle()

    expect(pathsCalled()).toEqual(['/api/user/profile-information'])
  })

  it('never touches the profile endpoint when only the business name changed', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: sampleMe }))

    const screen = await open()

    typeInto(screen.business, 'Marsh & Doyle Makeup')
    submitForm(screen.host)
    await settle()

    // Sending the profile here would un-verify an email address nobody
    // edited, which is the whole reason the halves are sent separately.
    expect(pathsCalled()).toEqual(['/api/account'])
  })

  it('sends the profile first when both changed', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: sampleMe }))

    const screen = await open()

    typeInto(screen.email, 'new@example.com')
    typeInto(screen.business, 'Marsh & Doyle Makeup')
    submitForm(screen.host)
    await settle()

    expect(pathsCalled()).toEqual(['/api/user/profile-information', '/api/account'])
  })

  it('sends nothing at all when nothing changed', async () => {
    const screen = await open()

    submitForm(screen.host)
    await settle()

    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('puts a rejected email on that field and moves focus there', async () => {
    fetchMock.mockResolvedValue(jsonResponse(422, {
      message: 'The email has already been taken.',
      errors: { email: ['The email has already been taken.'] },
    }))

    const screen = await open()

    typeInto(screen.email, 'taken@example.com')
    submitForm(screen.host)
    await settle()

    expect(screen.email.getAttribute('aria-invalid')).toBe('true')

    const describedBy = screen.email.getAttribute('aria-describedby') ?? ''
    const message = element(screen.host, `#${describedBy.split(' ').pop()}`)

    expect(message.textContent?.trim()).toBe('The email has already been taken.')
    expect(document.activeElement).toBe(screen.email)
  })

  it('says the business name was refused without claiming the profile was', async () => {
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { data: sampleMe }))
      .mockResolvedValueOnce(jsonResponse(403, { message: 'Only the account owner can change this.' }))

    const screen = await open()

    typeInto(screen.name, 'Ellie Marsh-Doyle')
    typeInto(screen.business, 'Marsh & Doyle Makeup')
    submitForm(screen.host)
    await settle()

    const alert = element(screen.host, '[role="alert"]')

    expect(alert.textContent?.trim()).toBe(
      'Your name and email address were saved. The business name was not, because only the account owner can change it.',
    )

    // Nothing is marked invalid: the name and email are not what was wrong.
    expect(screen.host.querySelectorAll('[aria-invalid="true"]')).toHaveLength(0)
  })

  it('keeps the confirmation after a save, which replaces me underneath it', async () => {
    const saved = structuredClone(sampleMe) as Me

    saved.user.name = 'Ellie Marsh-Doyle'
    fetchMock.mockResolvedValue(jsonResponse(200, { data: saved }))

    const screen = await open()

    typeInto(screen.name, 'Ellie Marsh-Doyle')
    submitForm(screen.host)
    await settle()

    // A successful save replaces the store's me and the watcher reacting to
    // that refills the fields, so the confirmation is set and the form is
    // rewritten in the same breath. This says the message is still there
    // afterwards. It does not say why: the watcher no longer touches the
    // confirmation at all, and no test on this page can tell that apart from
    // the ordering that used to make it survive anyway.
    expect(element(screen.host, '[role="status"]').textContent?.trim()).toBe('Saved.')
    expect(screen.name.value).toBe('Ellie Marsh-Doyle')
  })

  it('puts the fields back on cancel, and takes the confirmation with them', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, { data: sampleMe }))

    const screen = await open()

    typeInto(screen.name, 'Ellie Marsh-Doyle')
    submitForm(screen.host)
    await settle()

    // Let the typing render before the click. typeInto writes the DOM value
    // itself, so if the ref goes back to where it started before Vue has
    // flushed, Vue sees no change and never patches the field back.
    typeInto(screen.name, 'Someone Else')
    await settle()

    element<HTMLButtonElement>(screen.host, 'button[type="button"]').click()
    await settle()

    expect(screen.name.value).toBe('Ellie Marsh')
    expect(screen.host.querySelector('[role="status"]')).toBeNull()
  })

  it('disables the business name for a collaborator and says why', async () => {
    const screen = await open({ role: 'collaborator' })

    expect(screen.business.disabled).toBe(true)

    const describedBy = screen.business.getAttribute('aria-describedby') ?? ''
    const hint = element(screen.host, `#${describedBy.split(' ')[0]}`)

    expect(hint.textContent?.trim()).toBe('Only the account owner can change the business name.')
  })
})
