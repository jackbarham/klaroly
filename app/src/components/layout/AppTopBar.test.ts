import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import AppTopBar from '@/components/layout/AppTopBar.vue'
import { barGlassClasses } from '@/components/layout/barGlass'
import { sampleMe } from '@/lib/auth.sample'
import { element } from '@/lib/testHelpers'
import { mount as mountOnce, mountWithCleanup, unmount, type Mounted } from '@/lib/testMount'
import { useAuthStore } from '@/stores/auth'
import type { Me } from '@/types/auth'

const mount = mountWithCleanup()

// The bar no longer reads the store at all, but the tests still sign somebody
// in: the one below about Home is only worth anything if there is a business
// name sitting there for the bar to have used and not used. The store is
// created by the pinia the mount installs, which is why this runs after the
// mount rather than before.
async function mountSignedIn(path: string, me: Me = sampleMe, props: Record<string, unknown> = {}): Promise<Mounted> {
  const mounted = await mount(AppTopBar, path, props)

  const auth = useAuthStore()

  auth.me = me
  auth.status = 'signed_in'

  await nextTick()

  return mounted
}

function title(mounted: Mounted): string {
  return element(mounted.host, 'header p').textContent?.trim() ?? ''
}

function bar(mounted: Mounted): HTMLElement {
  return element(mounted.host, 'header')
}

// The page having moved under the bar. scrollY is read straight off window, so
// this is the honest way to say it: set the value, then fire the event the
// component listens for.
async function scrollTo(y: number): Promise<void> {
  Object.defineProperty(window, 'scrollY', { value: y, configurable: true, writable: true })
  window.dispatchEvent(new Event('scroll'))
  await nextTick()
}

afterEach(async () => {
  await scrollTo(0)
})

describe('the top bar', () => {
  // Home used to be the exception here and say the business name. The tab bar
  // carries no words now, so this line is the only thing on a phone naming the
  // screen, and Home cannot be the one screen that does not say its own.
  //
  // The person is signed in on purpose, and the second assertion is what makes
  // the first mean anything: a bar that said Summary because nobody was loaded
  // would pass a bare check for Summary just as happily as one that had stopped
  // reading the account.
  it('says the screen name on Home, with a business name loaded and going unused', async () => {
    const mounted = await mountSignedIn('/')

    expect(title(mounted)).toBe('Summary')
    expect(sampleMe.account.name).toBe('Ellie Marsh Makeup')
  })

  it('says the screen name everywhere else', async () => {
    const mounted = await mountSignedIn('/enquiries')

    expect(title(mounted)).toBe('Enquiries')
  })

  // A page under a section gets its own name rather than its parent's, which
  // is free: it comes from the route's own meta.titleKey.
  it('says the name of a page inside a section', async () => {
    const mounted = await mountSignedIn('/settings/travel')

    expect(title(mounted)).toBe('Travel')
  })

  it('is not a heading, because the page under it still owns its h1', async () => {
    const mounted = await mountSignedIn('/enquiries')

    expect(mounted.host.querySelectorAll('h1')).toHaveLength(0)
  })

  it('gives both buttons an accessible name, since neither has any words in it', async () => {
    const mounted = await mountSignedIn('/')

    const labels = Array.from(mounted.host.querySelectorAll('header button'))
      .map((button) => button.getAttribute('aria-label'))

    expect(labels).toEqual(['Notifications', 'New'])
  })

  it('asks the shell to open the create sheet, rather than owning it', async () => {
    const create = vi.fn()
    const mounted = await mountSignedIn('/', sampleMe, { onCreate: create })

    // The emit is this component's whole contribution to creating anything.
    // The sheet belongs to AppLayout, which both triggers are children of, and
    // the sidebar's New button is the other one.
    element<HTMLButtonElement>(mounted.host, '[aria-label="New"]').click()
    await nextTick()

    expect(create).toHaveBeenCalledTimes(1)
  })

  it('opens the notifications sheet from the bell, with one line in it and no list', async () => {
    const mounted = await mountSignedIn('/')
    const bell = element<HTMLButtonElement>(mounted.host, '[aria-label="Notifications"]')

    expect(bell.getAttribute('aria-expanded')).toBe('false')
    expect(mounted.host.querySelector('[role="dialog"]')).toBeNull()

    bell.click()
    await nextTick()

    expect(bell.getAttribute('aria-expanded')).toBe('true')

    const sheet = element(mounted.host, '[role="dialog"]')

    expect(sheet.textContent).toContain('Notifications')
    expect(sheet.textContent).toContain('Nothing here yet.')
    expect(sheet.querySelectorAll('li')).toHaveLength(0)
  })

  it('is clear over the page and takes the glass once the page moves under it', async () => {
    const mounted = await mountSignedIn('/')
    const glass = barGlassClasses.split(' ')

    for (const cls of glass) {
      expect(bar(mounted).classList.contains(cls)).toBe(false)
    }

    await scrollTo(20)

    for (const cls of glass) {
      expect(bar(mounted).classList.contains(cls)).toBe(true)
    }
  })

  // Four pixels rather than one: resting at the top of a page is not always
  // exactly nought, and a bar that flickers its background on and off as a
  // finger rests on the screen is worse than one that waits.
  it('does not take the glass for a scroll of a pixel or two', async () => {
    const mounted = await mountSignedIn('/')

    await scrollTo(3)

    expect(bar(mounted).classList.contains('backdrop-blur-xl')).toBe(false)
  })

  // A shell component is mounted once and lives for the session, so this is
  // less about this bar than about the next thing that copies its scroll
  // handler. mount and unmount are used directly rather than the cleanup
  // wrapper, because the point is unmounting it here rather than afterwards.
  it('stops listening when it goes away', async () => {
    const remove = vi.spyOn(window, 'removeEventListener')
    const mounted = await mountOnce(AppTopBar, '/')

    unmount(mounted)

    expect(remove).toHaveBeenCalledWith('scroll', expect.any(Function))

    remove.mockRestore()
  })
})
