import { describe, expect, it } from 'vitest'
import { accountGroups } from '@/lib/navigation'
import { mountWithCleanup } from '@/lib/testMount'
import AccountView from '@/views/account/AccountView.vue'

// The index and the column beside every page in the section are the same four
// entries, and the point of accountGroups is that they cannot disagree.

const mount = mountWithCleanup()

describe('the My account index', () => {
  it('lists one link per group, in the order of the config', async () => {
    const { host } = await mount(AccountView, '/account')
    const links = [...host.querySelectorAll('a[href^="/account/"]')]

    expect(links.map((link) => link.getAttribute('href'))).toEqual(
      accountGroups.map((group) => `/account/${group.key}`),
    )
  })
})
