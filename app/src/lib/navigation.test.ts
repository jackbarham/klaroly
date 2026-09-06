import { describe, expect, it } from 'vitest'
import {
  accountGroups,
  activeTabIndex,
  activeTabKey,
  createItem,
  moreItems,
  navigation,
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
    expect(tabBarItems.map((item) => item.key)).toEqual(['home', 'bookings', 'enquiries', 'contacts', 'more'])
  })

  it('is five destinations, so the bar is five links and has no button in it', () => {
    expect(tabBarItems.map((item) => item.routeName))
      .toEqual(['home', 'bookings', 'enquiries', 'contacts', 'more'])
  })

  it('does not contain the create action, which is not a destination', () => {
    expect(tabBarItems.map((item) => item.key)).not.toContain('create')
  })
})

describe('the create action', () => {
  it('is still in the navigation array, as the only entry going nowhere', () => {
    expect(createItem.key).toBe('create')
    expect(createItem.routeName).toBeNull()
    expect(navigation.filter((item) => item.routeName === null)).toHaveLength(1)
  })

  it('is in no list at all: not the bar, not the sidebar, not More', () => {
    const listed = [...tabBarItems, ...sidebarMain, ...sidebarSecondary, ...moreItems]

    expect(listed.map((item) => item.key)).not.toContain('create')
  })
})

describe('the sidebar lists', () => {
  it('do not include More', () => {
    const keys = [...sidebarMain, ...sidebarSecondary].map((item) => item.key)

    expect(keys).not.toContain('more')
  })

  it('are Summary, Bookings, Enquiries and Contacts, then Settings, My account and Help', () => {
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
  it('is every destination the tab bar has no room for', () => {
    expect(shipped(moreItems)).toEqual(['settings', 'account', 'help'])
  })

  // Contacts is the change: it was reached through More and is a tab of its
  // own now. Asserted both ways round, because an absence on its own passes
  // just as happily against a list that lost the wrong thing.
  it('has lost Contacts to the tab bar', () => {
    expect(tabBarItems.map((item) => item.key)).toContain('contacts')
    expect(moreItems.map((item) => item.key)).not.toContain('contacts')
  })

  // Every row in MoreView is a RouterLink, so an entry with no route would
  // render a link to nowhere. isDestination is what stops it, and this is the
  // assertion that says so rather than MoreView carrying a special case.
  it('contains nothing that goes nowhere', () => {
    expect(moreItems.map((item) => item.routeName)).not.toContain(null)
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

  it('puts every My account page in My account', () => {
    expect(sectionKey('account')).toBe('account')
    expect(sectionKey('account-devices')).toBe('account')
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
    expect(activeTabIndex('enquiries')).toBe(2)
    expect(activeTabIndex('enquiry')).toBe(2)
  })

  it('is More for a section the bar has no item for', () => {
    expect(activeTabKey('settings-travel')).toBe('more')
    expect(activeTabKey('account-devices')).toBe('more')
  })

  it('is Contacts for Contacts, now that the bar has an item for it', () => {
    expect(activeTabKey('contacts')).toBe('contacts')
    expect(activeTabKey('contact')).toBe('contacts')
    expect(activeTabIndex('contacts')).toBe(3)
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

describe('the account groups', () => {
  it('are the four the My account index lists, in order', () => {
    expect(accountGroups.map((group) => group.key)).toEqual(['details', 'password', 'devices', 'email'])
  })

  it('name routes under the account section, so the navigation marks it', () => {
    for (const group of accountGroups) {
      expect(sectionKey(group.routeName)).toBe('account')
    }
  })
})
