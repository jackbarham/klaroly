import { describe, expect, it } from 'vitest'
import AppTabBar from '@/components/layout/AppTabBar.vue'
import { tabBarItems } from '@/lib/navigation'
import { mountWithCleanup } from '@/lib/testMount'

function pill(host: HTMLElement): HTMLElement | null {
  return host.querySelector('.pill')
}

const mount = mountWithCleanup()

describe('the tab bar', () => {
  it('renders one item for every entry in the tab bar list, with the create action as a button', async () => {
    const mounted = await mount(AppTabBar, '/')

    const links = mounted.host.querySelectorAll('nav a')
    const buttons = mounted.host.querySelectorAll('nav button')

    expect(links.length + buttons.length).toBe(tabBarItems.length)
    expect(buttons).toHaveLength(1)
    expect(buttons[0].getAttribute('aria-label')).toBe('New')
  })

  it('marks the current destination and nothing else', async () => {
    const mounted = await mount(AppTabBar, '/')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Home')
  })

  it('lands on the right item when the app is opened straight at a deep route', async () => {
    const mounted = await mount(AppTabBar, '/enquiries')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Enquiries')

    // The pill is shown, and it is shown behind the third item, which is
    // where Enquiries sits once the create button is counted.
    expect(pill(mounted.host)?.style.display).not.toBe('none')
  })

  it('marks More on a section the bar has no item for', async () => {
    const mounted = await mount(AppTabBar, '/settings/travel')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('More')
  })

  it('hides the pill when no item matches the route', async () => {
    const mounted = await mount(AppTabBar, '/billing')

    expect(mounted.host.querySelectorAll('[aria-current="page"]')).toHaveLength(0)
    expect(pill(mounted.host)?.style.display).toBe('none')
  })

  it('is a navigation landmark with a name', async () => {
    const mounted = await mount(AppTabBar, '/')

    expect(mounted.host.querySelector('nav')?.getAttribute('aria-label')).toBe('Main')
  })
})
