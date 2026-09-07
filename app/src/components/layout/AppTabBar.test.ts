import { describe, expect, it } from 'vitest'
import AppTabBar from '@/components/layout/AppTabBar.vue'
import { tabBarItems } from '@/lib/navigation'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'

function pill(host: HTMLElement): HTMLElement | null {
  return host.querySelector('.pill')
}

// Which item is which, read the way a screen reader reads it. The bar draws
// icons and no words, so aria-label is the only thing that says what any of
// these five are, and a test that went on reading textContent would compare
// five empty strings and pass whatever the bar was labelled.
function name(item: Element | null): string {
  return item?.getAttribute('aria-label') ?? ''
}

const mount = mountWithCleanup()

describe('the tab bar', () => {
  it('renders one link for every entry in the tab bar list, and no button at all', async () => {
    const mounted = await mount(AppTabBar, '/')

    const links = mounted.host.querySelectorAll('nav a')

    expect(links).toHaveLength(tabBarItems.length)
    expect(links).toHaveLength(5)

    // The create action left this bar for the top bar. Every item here is a
    // destination now, so a button in it would mean something had crept back
    // in that goes nowhere.
    expect(mounted.host.querySelectorAll('nav button')).toHaveLength(0)

    // The same five names asserted present as accessible names and absent as
    // words on the screen. Either on its own is happy with a bar that has lost
    // both: an empty textContent proves nothing about what was announced, and
    // a label nobody can see is the whole design here rather than a slip.
    expect(Array.from(links).map(name))
      .toEqual(['Summary', 'Bookings', 'Enquiries', 'Contacts', 'More'])

    expect(Array.from(links).map((link) => link.textContent?.trim())).toEqual(['', '', '', '', ''])
  })

  it('marks the current destination and nothing else', async () => {
    const mounted = await mount(AppTabBar, '/')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(name(current[0])).toBe('Summary')
  })

  it('lands on the right item when the app is opened straight at a deep route', async () => {
    const mounted = await mount(AppTabBar, '/enquiries')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(name(current[0])).toBe('Enquiries')

    expect(pill(mounted.host)?.style.display).not.toBe('none')
  })

  it('marks Contacts, now that the bar has an item for it', async () => {
    const mounted = await mount(AppTabBar, '/contacts')

    expect(name(element(mounted.host, '[aria-current="page"]'))).toBe('Contacts')
  })

  // The detail page is the reason sectionKey exists: before Contacts was a tab
  // this lit More up, because Contacts was reached through it.
  it('marks Contacts on one person\'s page too', async () => {
    const mounted = await mount(AppTabBar, '/contacts/7')

    expect(name(element(mounted.host, '[aria-current="page"]'))).toBe('Contacts')
  })

  it('marks More on a section the bar has no item for', async () => {
    const mounted = await mount(AppTabBar, '/settings/travel')

    const current = mounted.host.querySelectorAll('[aria-current="page"]')

    expect(current).toHaveLength(1)
    expect(name(current[0])).toBe('More')
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
