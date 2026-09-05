import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { sampleMe } from '@/lib/auth.sample'
import { element } from '@/lib/testHelpers'
import { sidebarMain, sidebarSecondary } from '@/lib/navigation'
import { mountWithCleanup, type Mounted } from '@/lib/testMount'
import { useAuthStore } from '@/stores/auth'
import type { Me } from '@/types/auth'

const mount = mountWithCleanup()

// The sidebar's footer row reads the signed-in person out of the store, so a
// test of it has to put one there. The store is created by the pinia the
// mount installs, which is why this runs after the mount rather than before.
async function mountSignedIn(me: Me = sampleMe): Promise<Mounted> {
  const mounted = await mount(AppSidebar, '/')

  const auth = useAuthStore()

  auth.me = me
  auth.status = 'signed_in'

  await nextTick()

  return mounted
}

function accountRow(mounted: Mounted): HTMLButtonElement {
  return element<HTMLButtonElement>(mounted.host, '[aria-haspopup="dialog"]')
}

describe('the sidebar', () => {
  it('renders one link for every entry in the navigation config and nothing else', async () => {
    const mounted = await mount(AppSidebar, '/')

    const links = mounted.host.querySelectorAll('nav a')

    expect(links).toHaveLength(sidebarMain.length + sidebarSecondary.length)

    const labels = Array.from(links).map((link) => link.textContent?.trim())

    // Kitchen sink is a development-only entry (see navigation.ts) and this
    // test is about the navigation the app ships, so it is dropped here.
    expect(labels.filter((label) => label !== 'Kitchen sink'))
      .toEqual(['Home', 'Bookings', 'Enquiries', 'Contacts', 'Settings', 'My account', 'Help'])
    expect(labels).not.toContain('More')
  })

  it('marks the current section, on a list page and on a detail page', async () => {
    const mounted = await mount(AppSidebar, '/bookings/42')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Bookings')
  })

  it('marks Settings while a settings group is open', async () => {
    const mounted = await mount(AppSidebar, '/settings/travel')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Settings')
  })

  it('names the account by its business name at the foot of the column', async () => {
    const mounted = await mountSignedIn()

    expect(accountRow(mounted).textContent?.trim()).toBe('Ellie Marsh Makeup')
  })

  it('falls back to the person\'s own name when the account has none', async () => {
    const mounted = await mountSignedIn({
      ...sampleMe,
      account: { ...sampleMe.account, name: '' },
    })

    expect(accountRow(mounted).textContent?.trim()).toBe('Ellie Marsh')
  })

  it('opens the account menu from that row, with the way out in it', async () => {
    const mounted = await mountSignedIn()
    const row = accountRow(mounted)

    expect(row.getAttribute('aria-expanded')).toBe('false')
    expect(mounted.host.querySelector('[role="dialog"]')).toBeNull()

    row.click()
    await nextTick()

    expect(row.getAttribute('aria-expanded')).toBe('true')

    const menu = element(mounted.host, '[role="dialog"]')

    expect(menu.textContent).toContain('Sign out')
  })

  it('is a navigation landmark with a name', async () => {
    const mounted = await mount(AppSidebar, '/')

    expect(mounted.host.querySelector('nav')?.getAttribute('aria-label')).toBe('Main')
  })
})
