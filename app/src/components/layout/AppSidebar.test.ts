import { afterEach, describe, expect, it } from 'vitest'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { sidebarMain, sidebarSecondary } from '@/lib/navigation'
import { mount, unmount, type Mounted } from '@/lib/testMount'

let mounted: Mounted | null = null

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('the sidebar', () => {
  it('renders one link for every entry in the navigation config and nothing else', async () => {
    mounted = await mount(AppSidebar, '/')

    const links = mounted.host.querySelectorAll('nav a')

    expect(links).toHaveLength(sidebarMain.length + sidebarSecondary.length)

    const labels = Array.from(links).map((link) => link.textContent?.trim())

    expect(labels).toEqual(['Home', 'Bookings', 'Enquiries', 'Contacts', 'Settings', 'My account', 'Help'])
    expect(labels).not.toContain('More')
  })

  it('marks the current section, on a list page and on a detail page', async () => {
    mounted = await mount(AppSidebar, '/bookings/42')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Bookings')
  })

  it('marks Settings while a settings group is open', async () => {
    mounted = await mount(AppSidebar, '/settings/travel')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Settings')
  })

  it('is a navigation landmark with a name', async () => {
    mounted = await mount(AppSidebar, '/')

    expect(mounted.host.querySelector('nav')?.getAttribute('aria-label')).toBe('Main')
  })
})
