import type { IconName } from '@/components/ui/Icon.vue'

// The raw material the tokens section draws, listed once.
//
// Nothing here is new: every class name below already exists, either as a
// token in src/assets/app.css or as one of Tailwind's own defaults. This page
// adds nothing to the system, it only shows what is already in it, so a token
// change can be judged here before any screen is opened.
//
// The class names are written out in full rather than built from pieces,
// because Tailwind finds the classes it has to generate by reading the source
// and cannot see a name that was assembled at runtime.

export interface ColourToken {
  token: string
  className: string
  use: string
}

export const colourTokens: ColourToken[] = [
  { token: '--color-brand', className: 'bg-brand', use: 'the primary action, and nothing else' },
  { token: '--color-brand-strong', className: 'bg-brand-strong', use: 'the primary action, hovered' },
  { token: '--color-on-brand', className: 'bg-on-brand', use: 'the text and icons on the brand colour' },
  { token: '--color-surface', className: 'bg-surface', use: 'the page itself, behind every card' },
  { token: '--color-neutral-0', className: 'bg-neutral-0', use: 'a card, a control, the sidebar' },
  { token: '--color-neutral-50', className: 'bg-neutral-50', use: 'a hovered row, a disabled control' },
  { token: '--color-neutral-100', className: 'bg-neutral-100', use: 'the current navigation item, a hovered ghost button' },
  { token: '--color-neutral-200', className: 'bg-neutral-200', use: 'every border and divider' },
  { token: '--color-neutral-300', className: 'bg-neutral-300', use: 'a control border, the sheet grab handle' },
  { token: '--color-neutral-400', className: 'bg-neutral-400', use: 'placeholder text, an empty state icon' },
  { token: '--color-neutral-500', className: 'bg-neutral-500', use: 'a hint, a description, a secondary line' },
  { token: '--color-neutral-600', className: 'bg-neutral-600', use: 'an idle tab bar label' },
  { token: '--color-neutral-700', className: 'bg-neutral-700', use: 'a field label, a ghost button, an idle sidebar link' },
  { token: '--color-neutral-800', className: 'bg-neutral-800', use: 'nothing in the app uses this one today' },
  { token: '--color-neutral-900', className: 'bg-neutral-900', use: 'body text, headings, the focus ring, an invalid border' },
]

export interface TypeStep {
  className: string
  sample: string
  use: string
}

// The steps the app actually renders. There is no type scale in the theme
// block, so these are Tailwind's own sizes and weights, and this is the set
// that appears in the components as they stand.
export const typeSteps: TypeStep[] = [
  { className: 'text-2xl font-bold', sample: 'Klaroly', use: 'the wordmark above an authentication card' },
  { className: 'text-2xl font-semibold', sample: 'Bookings', use: 'a page heading' },
  { className: 'text-xl font-semibold', sample: 'Sign in to Klaroly', use: 'an authentication screen heading' },
  { className: 'text-lg font-semibold', sample: 'Travel and charges', use: 'a form section heading, the wordmark' },
  { className: 'text-base', sample: 'Trial at 7am, ceremony at 1pm.', use: 'body text, a control, a menu row' },
  { className: 'text-base font-medium', sample: 'Bridal party, six people', use: 'the current sidebar link' },
  { className: 'text-sm', sample: 'Last updated three hours ago', use: 'a hint, a description, a back link' },
  { className: 'text-sm font-medium', sample: 'Client name', use: 'a field label, an error, a button' },
  { className: 'text-xs', sample: 'Bookings', use: 'a tab bar label' },
]

export interface SpacingStep {
  step: number
  className: string
}

// The eight pixel grid, as the house rules describe it. Step 1 is on the list
// because it is allowed inside a control, between an icon and its label.
export const spacingSteps: SpacingStep[] = [
  { step: 1, className: 'w-1' },
  { step: 2, className: 'w-2' },
  { step: 4, className: 'w-4' },
  { step: 6, className: 'w-6' },
  { step: 8, className: 'w-8' },
  { step: 10, className: 'w-10' },
  { step: 12, className: 'w-12' },
  { step: 16, className: 'w-16' },
  { step: 20, className: 'w-20' },
  { step: 24, className: 'w-24' },
]

export interface ShapeToken {
  token: string
  className: string
  use: string
}

export const radiusTokens: ShapeToken[] = [
  { token: '--radius-control', className: 'rounded-control', use: 'a button, an input, a menu row, a navigation link' },
  { token: '--radius-card', className: 'rounded-card', use: 'a card' },
  { token: '--radius-sheet', className: 'rounded-sheet', use: 'the tab bar and the create sheet' },
]

export const shadowTokens: ShapeToken[] = [
  { token: '--shadow-raised', className: 'shadow-raised', use: 'the tab bar, the create button, the create sheet' },
]

export interface BorderWidth {
  className: string
  use: string
}

// Border widths are Tailwind's own, not tokens. There are two of them in the
// whole app, and the heavier one is how a form says something is wrong.
export const borderWidths: BorderWidth[] = [
  { className: 'border', use: 'every border in the app' },
  { className: 'border-2', use: 'an invalid control, and FormError' },
]

// Every icon in Icon.vue, in the order that file lists them. It is typed, so
// renaming an icon breaks this list at compile time rather than drawing a
// blank square.
export const iconNames: IconName[] = [
  'home',
  'calendar',
  'enquiries',
  'contacts',
  'more',
  'plus',
  'settings',
  'account',
  'help',
  'chevron-left',
  'chevron-right',
  'sign-out',
  'page',
]
