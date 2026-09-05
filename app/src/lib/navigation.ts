import type { IconName } from '@/components/ui/Icon.vue'

// Where someone can go in the app, written down once. The sidebar, the phone
// tab bar and the More list are all built from this array, so adding a
// section is a line here rather than an edit in three components.
//
// The order of the array is the order things appear in. The tab bar takes the
// five entries flagged for it, in this order, which is why the create action
// sits between Bookings and Enquiries: it is drawn in the middle of the bar.

export interface NavItem {
  key: string
  // The route this goes to, or null for the create action, which opens the
  // create sheet instead of navigating.
  routeName: string | null
  labelKey: string
  icon: IconName
  inTabBar: boolean
  // Which half of the sidebar it belongs to, or null for entries the sidebar
  // does not show. More is one of those: it exists to reach the sections a
  // phone has no room for, and a wide screen shows all of them already.
  sidebarGroup: 'main' | 'secondary' | null
}

export const navigation: NavItem[] = [
  { key: 'home', routeName: 'home', labelKey: 'nav.home', icon: 'home', inTabBar: true, sidebarGroup: 'main' },
  { key: 'bookings', routeName: 'bookings', labelKey: 'nav.bookings', icon: 'calendar', inTabBar: true, sidebarGroup: 'main' },
  { key: 'create', routeName: null, labelKey: 'nav.create', icon: 'plus', inTabBar: true, sidebarGroup: null },
  { key: 'enquiries', routeName: 'enquiries', labelKey: 'nav.enquiries', icon: 'enquiries', inTabBar: true, sidebarGroup: 'main' },
  { key: 'more', routeName: 'more', labelKey: 'nav.more', icon: 'more', inTabBar: true, sidebarGroup: null },
  { key: 'contacts', routeName: 'contacts', labelKey: 'nav.contacts', icon: 'contacts', inTabBar: false, sidebarGroup: 'main' },
  { key: 'settings', routeName: 'settings', labelKey: 'nav.settings', icon: 'settings', inTabBar: false, sidebarGroup: 'secondary' },
  { key: 'account', routeName: 'account', labelKey: 'nav.account', icon: 'account', inTabBar: false, sidebarGroup: 'secondary' },
  { key: 'help', routeName: 'help', labelKey: 'nav.help', icon: 'help', inTabBar: false, sidebarGroup: 'secondary' },
]

// The kitchen sink is a development page, so its entry is added the same way
// its route is, under import.meta.env.DEV. That is a compile-time constant,
// so a build drops this and the array is the nine entries above. It is
// spliced in below My account rather than pushed, because Help belongs at the
// end of the list whether this is there or not.
if (import.meta.env.DEV) {
  const afterAccount = navigation.findIndex((item) => item.key === 'account') + 1

  navigation.splice(afterAccount, 0, {
    key: 'kitchen-sink',
    routeName: 'kitchen-sink',
    labelKey: 'nav.kitchen_sink',
    icon: 'sink',
    inTabBar: false,
    sidebarGroup: 'secondary',
  })
}

// An entry that goes somewhere. Everything except the create action is one,
// and the lists below are all of this type so that a link never has to cope
// with a missing route name.
export interface Destination extends NavItem {
  routeName: string
}

function isDestination(item: NavItem): item is Destination {
  return item.routeName !== null
}

// Home, Bookings, the create button, Enquiries, More.
export const tabBarItems: NavItem[] = navigation.filter((item) => item.inTabBar)

// Home, Bookings, Enquiries, Contacts.
export const sidebarMain: Destination[] = navigation.filter(isDestination).filter((item) => item.sidebarGroup === 'main')

// Settings, My account, Help, below the divider.
export const sidebarSecondary: Destination[] = navigation.filter(isDestination).filter((item) => item.sidebarGroup === 'secondary')

// What the phone's More page lists: everything the tab bar has no room for.
export const moreItems: Destination[] = navigation.filter(isDestination).filter((item) => !item.inTabBar)

// The create action, so that the sidebar's New button takes its label and
// icon from the same place the tab bar's plus button does.
export const createItem: NavItem = navigation.filter((item) => item.routeName === null)[0]

// A section that is itself a list of pages: an index listing them, and a
// column of the same links beside each one on a wide screen. Settings and My
// account are both that shape, so both are an array of these and both are
// drawn by SectionNav and SectionLayout.
export interface SectionGroup {
  key: string
  routeName: string
  labelKey: string
}

// The ten groups of settings, in the order they appear.
export const settingsGroups: SectionGroup[] = [
  { key: 'features', routeName: 'settings-features', labelKey: 'settings.features' },
  { key: 'rate-card', routeName: 'settings-rate-card', labelKey: 'settings.rate_card' },
  { key: 'travel', routeName: 'settings-travel', labelKey: 'settings.travel' },
  { key: 'payments', routeName: 'settings-payments', labelKey: 'settings.payments' },
  { key: 'templates', routeName: 'settings-templates', labelKey: 'settings.templates' },
  { key: 'automation', routeName: 'settings-automation', labelKey: 'settings.automation' },
  { key: 'agreement', routeName: 'settings-agreement', labelKey: 'settings.agreement' },
  { key: 'intake', routeName: 'settings-intake', labelKey: 'settings.intake' },
  { key: 'working', routeName: 'settings-working', labelKey: 'settings.working' },
  { key: 'business-year', routeName: 'settings-business-year', labelKey: 'settings.business_year' },
]

// The four pages of My account, in the order they appear.
export const accountGroups: SectionGroup[] = [
  { key: 'details', routeName: 'account-details', labelKey: 'account.details' },
  { key: 'password', routeName: 'account-password', labelKey: 'account.password' },
  { key: 'devices', routeName: 'account-devices', labelKey: 'account.devices' },
  { key: 'email', routeName: 'account-email', labelKey: 'account.email' },
]

// Which section of the app a route belongs to. A booking's page belongs to
// Bookings, every settings page belongs to Settings, and so on, so that the
// navigation marks the right thing on a detail page as well as on a list.
const sections: Record<string, string> = {
  home: 'home',
  bookings: 'bookings',
  booking: 'bookings',
  enquiries: 'enquiries',
  enquiry: 'enquiries',
  contacts: 'contacts',
  contact: 'contacts',
  more: 'more',
  account: 'account',
  help: 'help',
  'kitchen-sink': 'kitchen-sink',
}

export function sectionKey(routeName: unknown): string | null {
  if (typeof routeName !== 'string') {
    return null
  }

  // A section with pages under it marks its own entry from any of them, so
  // /settings/travel marks Settings and /account/devices marks My account.
  if (routeName === 'settings' || routeName.startsWith('settings-')) {
    return 'settings'
  }

  if (routeName.startsWith('account-')) {
    return 'account'
  }

  return sections[routeName] ?? null
}

// Which tab bar item is the current one. The sections the tab bar has no
// item for are all reached through More, so More is what lights up on them.
export function activeTabKey(routeName: unknown): string | null {
  const key = sectionKey(routeName)

  if (key === null) {
    return null
  }

  return tabBarItems.some((item) => item.key === key) ? key : 'more'
}

// Where the sliding pill goes, as a position in the tab bar's five items.
// Minus one means no item matches and the pill is hidden.
export function activeTabIndex(routeName: unknown): number {
  const key = activeTabKey(routeName)

  if (key === null) {
    return -1
  }

  return tabBarItems.findIndex((item) => item.key === key)
}
