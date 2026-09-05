import { describe, expect, it } from 'vitest'
import SectionNav from '@/components/layout/SectionNav.vue'
import { accountGroups, settingsGroups } from '@/lib/navigation'
import { mountWithCleanup } from '@/lib/testMount'

// One component, two sections. What would break quietly is a list that has
// stopped coming from the navigation config, or an index page that draws the
// list twice because the column forgot to hide itself.

const mount = mountWithCleanup()

const settingsProps = {
  groups: settingsGroups,
  indexRouteName: 'settings',
  labelKey: 'settings.title',
}

const accountProps = {
  groups: accountGroups,
  indexRouteName: 'account',
  labelKey: 'account.title',
}

describe('the section column', () => {
  it('draws one link per settings group, in order', async () => {
    const { host } = await mount(SectionNav, '/settings/travel', settingsProps)
    const links = [...host.querySelectorAll('a')]

    expect(links).toHaveLength(settingsGroups.length)
    expect(links.map((link) => link.getAttribute('href'))).toEqual(
      settingsGroups.map((group) => `/settings/${group.key}`),
    )
  })

  it('draws one link per account group, from the same component', async () => {
    const { host } = await mount(SectionNav, '/account/devices', accountProps)
    const links = [...host.querySelectorAll('a')]

    expect(links).toHaveLength(accountGroups.length)
    expect(links.map((link) => link.getAttribute('href'))).toEqual(
      accountGroups.map((group) => `/account/${group.key}`),
    )
  })

  it('marks the page it is on and nothing else', async () => {
    const { host } = await mount(SectionNav, '/account/password', accountProps)
    const current = [...host.querySelectorAll('[aria-current="page"]')]

    expect(current).toHaveLength(1)
    expect(current[0].textContent?.trim()).toBe('Password')
  })

  it('names itself, so two columns are told apart in a landmark list', async () => {
    const { host } = await mount(SectionNav, '/account/details', accountProps)

    expect(host.querySelector('nav')?.getAttribute('aria-label')).toBe('My account')
  })

  it('renders nothing on the settings index, because the index is that list', async () => {
    const { host } = await mount(SectionNav, '/settings', settingsProps)

    expect(host.querySelector('nav')).toBeNull()
  })

  it('renders nothing on the My account index, for the same reason', async () => {
    const { host } = await mount(SectionNav, '/account', accountProps)

    expect(host.querySelector('nav')).toBeNull()
  })
})
