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
  // The custom property that holds the value, which is what the theme file
  // and the style guide call it.
  token: string
  // The utility that paints the swatch. A component reaches the same token as
  // bg-, text-, border- or outline-, whichever it needs.
  className: string
  use: string
}

export interface ColourGroup {
  title: string
  note: string
  tokens: ColourToken[]
}

// The semantic layer, which is the only layer a component may touch. The
// primitives underneath it are not on this page on purpose: nothing is allowed
// to reach them, so showing them would invite it.
export const colourGroups: ColourGroup[] = [
  {
    title: 'Surfaces',
    note: 'What things sit on. The three whites are one colour in light and three different values in dark, which is why they are three tokens.',
    tokens: [
      { token: '--surface', className: 'bg-surface', use: 'the page, a control, the sidebar' },
      { token: '--surface-sunken', className: 'bg-surface-sunken', use: 'a hover on something transparent, the current navigation item' },
      { token: '--surface-hover', className: 'bg-surface-hover', use: 'the second step of hover, for something already sunken. Nothing uses it yet' },
      { token: '--surface-raised', className: 'bg-surface-raised', use: 'a card, the tab bar' },
      { token: '--surface-overlay', className: 'bg-surface-overlay', use: 'the create sheet, and menus when they arrive' },
      { token: '--surface-disabled', className: 'bg-surface-disabled', use: 'a control nobody can use' },
      { token: '--scrim', className: 'bg-scrim', use: 'behind the sheet. Ten per cent black in light, fifty in dark' },
    ],
  },
  {
    title: 'Text',
    note: 'Four weights of quiet, plus the one that goes on top of a filled accent.',
    tokens: [
      { token: '--text', className: 'bg-text', use: 'body copy, and what the page inherits' },
      { token: '--text-strong', className: 'bg-text-strong', use: 'headings, labels, a value that matters' },
      { token: '--text-muted', className: 'bg-text-muted', use: 'a hint, a description, a secondary line' },
      { token: '--text-subtle', className: 'bg-text-subtle', use: 'the quietest copy, an empty state icon' },
      { token: '--text-placeholder', className: 'bg-text-placeholder', use: 'what a control says before anything is typed' },
      { token: '--text-on-accent', className: 'bg-text-on-accent', use: 'text and icons on a filled accent, and the toggle knob' },
    ],
  },
  {
    title: 'Lines',
    note: 'Every hairline in the app is the first of these four.',
    tokens: [
      { token: '--border', className: 'bg-border', use: 'every border and divider' },
      { token: '--border-strong', className: 'bg-border-strong', use: "a control's edge, the sheet's grabber, the toggle when it is off" },
      { token: '--border-focus', className: 'bg-border-focus', use: 'the focus ring, on everything that takes focus' },
      { token: '--border-accent-soft', className: 'bg-border-accent-soft', use: 'a radio card under the pointer: the accent softened by the ground behind it' },
    ],
  },
  {
    title: 'Accent',
    note: 'One accent carries every primary action. The text value is darker than the fill in light and lighter in dark, because the same purple cannot be read on both.',
    tokens: [
      { token: '--accent', className: 'bg-accent', use: 'the primary action, and nothing else' },
      { token: '--accent-hover', className: 'bg-accent-hover', use: 'a filled accent, hovered. Darker, so the white label stays legible' },
      { token: '--accent-text', className: 'bg-accent-text', use: 'the accent as words: a link, the current navigation item' },
      { token: '--accent-subtle', className: 'bg-accent-subtle', use: 'a tinted accent background. Nothing uses it yet' },
    ],
  },
  {
    title: 'Status',
    note: 'Only the danger family is in use, on an invalid control and on a form-level error. The rest arrive with the status pill and the booking states.',
    tokens: [
      { token: '--danger', className: 'bg-danger', use: "an invalid control's border, and FormError's" },
      { token: '--danger-text', className: 'bg-danger-text', use: 'the words of an error' },
      { token: '--danger-hover', className: 'bg-danger-hover', use: 'a danger tint, hovered. Not used yet' },
      { token: '--danger-solid', className: 'bg-danger-solid', use: 'a destructive button, which needs a readable white label. Not used yet' },
      { token: '--danger-subtle', className: 'bg-danger-subtle', use: 'an error or cancelled pill. Not used yet' },
      { token: '--success', className: 'bg-success', use: 'confirmed, paid. Not used yet' },
      { token: '--success-text', className: 'bg-success-text', use: 'a text input\'s valid mark, and the words on a success pill' },
      { token: '--success-subtle', className: 'bg-success-subtle', use: 'a success pill, such as a verified email address' },
      { token: '--warning', className: 'bg-warning', use: 'awaiting, due. Not used yet' },
      { token: '--warning-text', className: 'bg-warning-text', use: 'the words on a warning pill' },
      { token: '--warning-subtle', className: 'bg-warning-subtle', use: 'a warning pill, such as an unverified email address' },
      { token: '--info', className: 'bg-info', use: 'informational. Not used yet' },
      { token: '--info-text', className: 'bg-info-text', use: 'the words on an info pill' },
      { token: '--info-subtle', className: 'bg-info-subtle', use: 'an info pill, such as the device you are reading from' },
    ],
  },
]

export interface TypeStep {
  className: string
  sample: string
  use: string
}

// The theme's own scale, from docs/style-guide.md, followed by the Tailwind
// sizes and weights the components still mostly use. The two lists sit
// together so that the move from one to the other can be judged here.
export const typeSteps: TypeStep[] = [
  { className: 'text-title font-medium', sample: 'Bookings', use: 'a page title, once the scale is adopted' },
  { className: 'text-figure', sample: '£1,234.56', use: 'a large figure or a sum of money' },
  { className: 'text-section font-medium', sample: 'Travel and charges', use: 'a section heading' },
  { className: 'text-lead font-medium', sample: 'Ready by 12.30', use: 'a large button label, prominent copy' },
  { className: 'text-control font-medium', sample: 'Ellie Marsh', use: 'the value typed into a control' },
  { className: 'text-body font-medium', sample: 'Hannah Whitfield', use: 'a label, a name, a row title' },
  { className: 'text-body', sample: 'Trial at 7am, ceremony at 1pm.', use: 'everything else: a row, a table cell, a pill' },
  { className: 'text-meta', sample: 'Last updated three hours ago', use: 'a counter, a timestamp, a pill' },
  { className: 'text-caption', sample: '14 Jun, 6:30am', use: 'dense phone meta' },
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
  { step: 3, className: 'w-3' },
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

// The radius levers. Changing one of these squares off every button, or every
// card, in one edit and with no component touched.
export const radiusTokens: ShapeToken[] = [
  { token: '--radius-control-value', className: 'rounded-control', use: 'a button, an input, a menu row, a navigation link' },
  { token: '--radius-card-value', className: 'rounded-card', use: 'a card, and a menu panel from lg up. Klaroly runs this at 12px, where the style guide says 8' },
  { token: '--radius-sheet', className: 'rounded-sheet', use: 'the tab bar, and the same panel below lg, where it is a bottom sheet' },
]

// A control's edge is four of these, and every one is an inset ring rather
// than a drop shadow, which is what lets focus and error recolour the edge
// without the box moving. The two state shadows read --border-width-focus, so
// thickening every form edge in the app is one variable.
export const shadowTokens: ShapeToken[] = [
  { token: '--shadow-raised', className: 'shadow-raised', use: 'the tab bar, the create button, and a menu panel below lg, where it is a bottom sheet' },
  { token: '--shadow-input', className: 'shadow-input', use: "a control's edge at rest" },
  { token: '--shadow-input-hover', className: 'shadow-input-hover', use: 'the same edge hovered, when the control is neither disabled nor focused' },
  { token: '--shadow-input-focus', className: 'shadow-input-focus', use: 'the same edge focused. There is no ring around the control' },
  { token: '--shadow-input-invalid', className: 'shadow-input-invalid', use: 'the same edge on a control that is wrong, and it stays while the control is focused' },
  { token: '--shadow-menu-value', className: 'shadow-menu', use: 'a menu panel from lg up. Its first layer is a hairline ring, which is the only edge that panel has' },
]

export interface BorderWidth {
  className: string
  use: string
}

// The two widths are tokens, as --border-width-control and
// --border-width-focus. The focus ring and the control edges read the second
// one, but no Tailwind utility can read a variable for a border width, so a
// component still writes border and border-2 for the rest.
export const borderWidths: BorderWidth[] = [
  { className: 'border', use: 'every border in the app' },
  { className: 'border-2', use: 'an invalid control, and FormError' },
  { className: 'focus-visible:focus-ring', use: 'the ring on anything that takes focus and has no edge to recolour. Tab to the box to see it' },
]

export interface MotionToken {
  token: string
  use: string
}

// Motion is one duration and one curve, and the duration is wired to
// Tailwind's own default for every transition utility. That is why no
// component in the app writes a duration of its own: changing --duration-base
// changes every hover, toggle and slide at once.
export const motionTokens: MotionToken[] = [
  { token: '--duration-base', use: 'every hover, every colour change, the toggle, the tab bar pill, the sheet' },
  { token: '--duration-fast', use: 'a mark that should land rather than glide, which is the tick arriving in a box' },
  { token: '--ease-out', use: 'the one curve, on all of it' },
]
