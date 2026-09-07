// TEMPORARY. A trial of three tab bar treatments, switched from the bottom of
// the More page so that the choice is made by looking at a phone rather than
// by arguing about it on a desktop.
//
// The whole trial is this file, one block at the bottom of MoreView.vue and
// three bindings in AppTabBar.vue. It goes when the choice is made, and the
// winning treatment becomes the only one the bar draws.
//
// The class strings deliberately are not here. src/lib/styleRules.test.ts
// reads the source of every file under components and views, so markup written
// in a module under those folders is still checked and markup written here
// would not be. What this file holds is which treatment is on.
import { ref } from 'vue'
import { oneOf, readSettings, writeSettings, type Checks } from '@/lib/viewSettings'

// current: 20px icon over a 12px label, which is what the bar draws today.
// larger:  22px icon over an 11px label, the same shape with the weight moved
//          from the words to the picture.
// icons:   28px icon, no label at all, and the bar itself pulled in a spacing
//          step at each end, because without the words it does not need the
//          width.
export type TabBarStyle = 'current' | 'larger' | 'icons'

interface TrialSettings {
  style: TabBarStyle
}

const storageKey = 'klaroly.tabbar.trial'

const defaults: TrialSettings = { style: 'current' }

const checks: Checks<TrialSettings> = {
  style: oneOf<TabBarStyle>('current', 'larger', 'icons'),
}

// Module state rather than a Pinia store, in the same way src/lib/updates.ts
// holds updateAvailable: two components read it, neither of them owns it, and
// there is no request behind it.
//
// It is kept on the device through the same reader every view setting uses, so
// the treatment survives a reload. That is the point on a phone: an installed
// PWA is reloaded constantly while it is being looked at, and a trial that
// forgot itself each time would be judged on how annoying it was to set.
export const tabBarStyle = ref<TabBarStyle>(readSettings(storageKey, defaults, checks).style)

export function setTabBarStyle(style: TabBarStyle): void {
  tabBarStyle.value = style

  writeSettings(storageKey, { style })
}

// The picker's wording, beside the type it names.
export const tabBarStyleOptions: { value: TabBarStyle, label: string }[] = [
  { value: 'current', label: 'Current: 20px icon, 12px label' },
  { value: 'larger', label: 'Larger: 22px icon, 11px label' },
  { value: 'icons', label: 'Icons only: 28px icon, narrower bar' },
]
