<template>
  <svg
    class="shrink-0"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path
      v-for="(d, index) in paths[name]"
      :key="index"
      :d="d"
    />
  </svg>
</template>

<script lang="ts">
// The only icons the app has. There is no icon package: each icon is a list
// of path strings drawn on a 24 by 24 grid and stroked with currentColor, so
// an icon takes the colour and the size of whatever it sits in.
//
// The type is exported because the navigation config names icons, and a typo
// there should be a type error rather than a blank square.
export type IconName =
  | 'home'
  | 'calendar'
  | 'enquiries'
  | 'contacts'
  | 'more'
  | 'plus'
  | 'settings'
  | 'account'
  | 'help'
  | 'chevron-left'
  | 'chevron-right'
  | 'chevron-down'
  | 'sign-out'
  | 'page'
  | 'search'
  | 'sink'

const paths: Record<IconName, string[]> = {
  home: [
    'M4 11.5 12 5l8 6.5',
    'M6.5 10.5V19h11v-8.5',
  ],
  calendar: [
    'M4 8a1.5 1.5 0 0 1 1.5-1.5h13A1.5 1.5 0 0 1 20 8v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5Z',
    'M4 10.5h16',
    'M8.5 4v4',
    'M15.5 4v4',
  ],
  enquiries: [
    'M4 13.5h4l1.5 2.5h5l1.5-2.5h4',
    'M4 13.5 6.5 5.5h11L20 13.5v5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5Z',
  ],
  contacts: [
    'M9.5 11.5a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z',
    'M3.5 19.5c0-3 2.7-5 6-5s6 2 6 5',
    'M16 5.6a3 3 0 0 1 0 5.8',
    'M17.5 15.1c1.8.7 3 2.3 3 4.4',
  ],
  // Three dots, solid rather than three capped points. A zero-length path
  // stroked with a round cap draws a dot exactly as wide as the stroke, which
  // next to the icons made of real lines reads as a hairline. These are
  // circles of radius 0.875, half the stroke width, so the stroke closes the
  // middle and each dot comes out solid at twice the width of a line.
  more: [
    'M6 12.875a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z',
    'M12 12.875a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z',
    'M18 12.875a.875.875 0 1 0 0-1.75.875.875 0 0 0 0 1.75Z',
  ],
  plus: [
    'M12 5.5v13',
    'M5.5 12h13',
  ],
  // Two sliders, which reads as settings without needing a cog's teeth.
  settings: [
    'M4 8.5h3',
    'M11 8.5h9',
    'M4 15.5h9',
    'M17 15.5h3',
    'M9 6.5a2 2 0 1 0 0 4 2 2 0 1 0 0-4',
    'M15 13.5a2 2 0 1 0 0 4 2 2 0 1 0 0-4',
  ],
  account: [
    'M12 12.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
    'M4.5 20c0-3.6 3.4-6 7.5-6s7.5 2.4 7.5 6',
  ],
  help: [
    'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z',
    'M9.6 9.4a2.5 2.5 0 0 1 4.9.7c0 1.7-2.5 2.1-2.5 3.9',
    'M12 16.95v.1',
  ],
  'chevron-left': [
    'M14.5 6 8.5 12l6 6',
  ],
  'chevron-right': [
    'M9.5 6l6 6-6 6',
  ],
  'chevron-down': [
    'M6 9.5l6 6 6-6',
  ],
  'sign-out': [
    'M14 7.5v-2A1.5 1.5 0 0 0 12.5 4h-6A1.5 1.5 0 0 0 5 5.5v13A1.5 1.5 0 0 0 6.5 20h6a1.5 1.5 0 0 0 1.5-1.5v-2',
    'M10 12h10',
    'M17 9l3 3-3 3',
  ],
  page: [
    'M5.5 6.5A1.5 1.5 0 0 1 7 5h10a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 17 19H7a1.5 1.5 0 0 1-1.5-1.5Z',
    'M9 10h6',
    'M9 14h4',
  ],
  // The filter field's mark. A ring and a handle, and the handle starts inside
  // the ring's edge rather than touching it, so the two read as one shape at
  // 20px instead of as a lollipop.
  search: [
    'M10.75 17a6.25 6.25 0 1 0 0-12.5 6.25 6.25 0 0 0 0 12.5Z',
    'M15.4 15.4 20 20',
  ],
  // A basin with a tap over it. The tap sits left of centre and its spout
  // ends above the rim, because an arch centred on a closed box reads as a
  // padlock at this size.
  sink: [
    'M3 12h18v5a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17Z',
    'M7 12V7.5h5.5a2.5 2.5 0 0 1 2.5 2.5v1',
    'M5 7.5h4',
    'M12 15.4v.2',
  ],
}

// Every icon, in the order above. The kitchen sink draws the set from this,
// so an icon added here appears there without a second list to update.
export const iconNames = Object.keys(paths) as IconName[]
</script>

<script setup lang="ts">
// The size comes from the caller, as a Tailwind height and width class, so
// that an icon is sized where it is used rather than by a prop here.
defineProps<{
  name: IconName
}>()
</script>
