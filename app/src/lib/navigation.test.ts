import { describe, expect, it } from 'vitest'
import {
  activeTabIndex,
  activeTabKey,
  moreItems,
  sectionKey,
  settingsGroups,
  sidebarMain,
  sidebarSecondary,
  tabBarItems,
} from '@/lib/navigation'

// The kitchen sink is a development-only entry, added under
// import.meta.env.DEV in navigation.ts, and the tests below describe the
// navigation the app ships. Dropping it here keeps them saying the same thing
// in a build, and again on the day the entry is taken out.
function shipped(items: { key: string }[]): string[] {
  return items.map((item) => item.key).filter((key) => key !== 'kitchen-sink')
}

describe('the tab bar list', () => {
  it('is five items in the documented order', () => {
    expect(tabBarItems.map((item) => item.key)).toEqual(['home', 'bookings', 'create', 'enquiries', 'more'])
  })

  it('has the create action in the middle, going nowhere', () => {
    expect(tabBarItems[2].routeName).toBeNull()
  })
})

describe('the sidebar lists', () => {
  it('do not include More', () => {
    const keys = [...sidebarMain, ...sidebarSecondary].map((item) => item.key)

    expect(keys).not.toContain('more')
  })

  it('are Home, Bookings, Enquiries and Contacts, then Settings, My account and Help', () => {
    expect(shipped(sidebarMain)).toEqual(['home', 'bookings', 'enquiries', 'contacts'])
    expect(shipped(sidebarSecondary)).toEqual(['settings', 'account', 'help'])
  })

  it('carry the kitchen sink below My account while this is a development build', () => {
    expect(sidebarSecondary.map((item) => item.key)).toEqual(['settings', 'account', 'kitchen-sink', 'help'])
  })

  it('never contain the create action, which is a button rather than a link', () => {
    const routeNames = [...sidebarMain, ...sidebarSecondary].map((item) => item.routeName)

    expect(routeNames).not.toContain(null)
  })
})

describe('the More list', () => {
  it('is everything the tab bar has no room for', () => {
    expect(shipped(moreItems)).toEqual(['contacts', 'settings', 'account', 'help'])
  })
})

describe('sectionKey', () => {
  it('puts a detail page in its list section', () => {
    expect(sectionKey('booking')).toBe('bookings')
    expect(sectionKey('contact')).toBe('contacts')
  })

  it('puts every settings page in Settings', () => {
    expect(sectionKey('settings')).toBe('settings')
    expect(sectionKey('settings-travel')).toBe('settings')
  })

  it('puts the kitchen sink in its own section, so the sidebar marks it', () => {
    expect(sectionKey('kitchen-sink')).toBe('kitchen-sink')
  })

  it('is null for a route in no section at all', () => {
    expect(sectionKey('billing')).toBeNull()
    expect(sectionKey(undefined)).toBeNull()
  })
})

describe('the active tab', () => {
  it('resolves to the position of the item, including on a deep link', () => {
    expect(activeTabIndex('home')).toBe(0)
    expect(activeTabIndex('enquiries')).toBe(3)
    expect(activeTabIndex('enquiry')).toBe(3)
  })

  it('is More for a section the bar has no item for', () => {
    expect(activeTabKey('settings-travel')).toBe('more')
    expect(activeTabKey('contacts')).toBe('more')
  })

  it('is nothing at all when no item matches', () => {
    expect(activeTabIndex('billing')).toBe(-1)
  })
})

describe('the settings groups', () => {
  it('are the ten the settings index lists', () => {
    expect(settingsGroups).toHaveLength(10)
    expect(settingsGroups[0].routeName).toBe('settings-features')
  })
})
