# Klaroly style guide

Extracted from Lemon Squeezy's application UI on 4 September 2026.
Companion files: `tokens.css`, `kitchen-sink.html`, `style-guide-screens/`.

---

## Status: read this first

**This is a borrowed skin, not Klaroly's identity.** Every colour, the typeface and
the whole measured system in this document were taken from Lemon Squeezy's
signed-in application. None of it was designed for Klaroly, and none of it is
final.

That is deliberate. The prototype and the early beta need to look clean and
finished in front of real makeup artists, because both of the main competitors
are genuinely ugly and looking good is part of what is being tested. Designing
Klaroly's own identity now would mean guessing. Designing it after artists have
used the thing, and after it is clear which screens matter and how they are
actually used, means designing from evidence.

So the borrowed system goes in wholesale now, and the file is structured so that
replacing it later is a small job rather than a rewrite. That is what the two
token layers are for. When the real brand work happens, it changes the semantic
layer in `tokens.css` and nothing else. No component is touched.

**A future session should not mistake any of this for a finished brand.** If you
are reading this and wondering whether the purple is Klaroly's colour: it is not.
It is `#7047eb`, it belongs to Lemon Squeezy, and it is one line away from being
replaced.

What was deliberately **not** taken: their logo, wordmark, illustrations, icon
set, product copy, information architecture, screen layouts, and anything about
their mobile view, which is thin and desktop-first because their product is a
desktop admin tool and Klaroly is not.

---

## Principles

1. **Clean, white, modern, minimal.** The page ground is pure white, not a tinted
   grey. Colour appears where it means something and nowhere else.
2. **Phone-first.** The primary user is holding a phone at 7am in a hotel room.
   Every component works at 390px before it works at 1440px.
3. **Fewer boxes.** Whitespace and a hairline divider beat a card. A card is for a
   group that genuinely needs lifting off the page, not for every section.
4. **Simple over decorated.** Where there are two ways of doing something, take
   the plainer one.
5. **One accent.** A single brand colour carries every primary action. Status
   colours are for status, not decoration.
6. **Both themes, always.** Nothing ships that has only been checked in light.

---

## Token tables

Every value below is named semantically. Components reference the semantic name,
never the raw value and never the primitive.

### Colour, light and dark

**Format note.** `tokens.css` defines every colour in **oklch**. The tables below
give hex, because hex is the record of what Lemon Squeezy actually render and it
is what you would paste into a design tool. The two are the same colours: the
conversion is exact in sRGB and was verified by rasterising nine tokens in both
themes and comparing them against the original hex. Nothing shifted.

oklch is used in the token file for one practical reason. The levers at the end of
this document include "swap the neutral ramp warmer", and in oklch that is a single
hue number changed across ten values with lightness and chroma untouched. In hex it
is ten colours recalculated by hand and judged by eye. Each primitive carries its
original hex in a trailing comment.

Semantic tokens and what they resolve to in each theme.

| Token | Light | Dark | Used for |
| --- | --- | --- | --- |
| `--color-surface` | `#ffffff` | `#212027` | The page ground |
| `--color-surface-sunken` | `#f7f7f8` | `white 5%` | Wells, section bands, active nav |
| `--color-surface-hover` | `#e8e8ed` | `white 8%` | The **second** step of hover, for something already sitting on `surface-sunken`. See the rule below. |
| `--color-surface-raised` | `#ffffff` | `#29282f` | Cards, sheets |
| `--color-surface-overlay` | `#ffffff` | `#2e2d34` | Menus, popovers, slide-overs |
| `--color-surface-disabled` | `#f7f7f8` | `#35343d` | Disabled controls |
| `--color-scrim` | `black 10%` | `black 50%` | Behind modals and slide-overs |
| `--color-text` | `#25252d` | `white 80%` | Body copy |
| `--color-text-strong` | `#121217` | `#f2f2f3` | Headings, key values, names |
| `--color-text-muted` | `#55556e` | `white 65%` | Secondary and supporting copy. 7.2:1 on white. |
| `--color-text-subtle` | `#6c6c89` | `white 50%` | Timestamps, counts, captions. 5.1:1 on white. |
| `--color-text-placeholder` | `#8a8aa3` | `white 50%` | Input placeholders |
| `--color-text-on-accent` | `#ffffff` | `#ffffff` | Text on a filled accent |
| `--color-border` | `#e8e8ed` | `white 12%` | Every hairline in the app |
| `--color-border-strong` | `#d1d1db` | `white 24%` | Outlined controls, selectable cards |
| `--color-border-focus` | `#7047eb` | `#7047eb` | The focus ring |
| `--color-accent` | `#7047eb` | `#7047eb` | Primary actions, active nav, selection |
| `--color-accent-hover` | `#5423e7` | `#5423e7` | Hover on a filled accent. **Darker than the resting fill, not lighter.** |
| `--color-accent-text` | `#5423e7` | `#9483fa` | Accent used as **text**. Darker than the fill in light (7.6:1 on white), lighter in dark (5.3:1 on the base, where the fill would be 2.9:1 and fail). Use this, never `accent`, for purple type. |
| `--color-accent-subtle` | `#f3effd` | `white 10%` | Tinted accent backgrounds |
| `--color-danger` | `#f53d6b` | `#f53d6b` | Error rings, required markers, marks on a light tint |
| `--color-danger-solid` | `#d50b3e` | `#d50b3e` | Destructive **fills** carrying a white label. See the note under Buttons. |
| `--color-danger-hover` | `#f3164e` | `#f3164e` | Hover on a danger tint |
| `--color-danger-subtle` | `#fef0f4` | `white 10%` | Error and cancelled pills |
| `--color-danger-text` | `#ad0027` | `#f53d6b` | Error message text, and danger pill text. 6.8:1 on its fill. |
| `--color-success` | `#2dca72` | `#2dca72` | Confirmed, paid |
| `--color-success-subtle` | `#eefbf4` | `white 10%` | Success pill background |
| `--color-success-text` | `#004f23` | `#2dca72` | Success pill text. 9.2:1 on its fill. |
| `--color-warning` | `#ff7d52` | `#ff7d52` | Awaiting, due |
| `--color-warning-solid` | `#c21200` | `#c21200` | A warning **fill** carrying a white label, which is the enquiry count badge on a calendar day. Separate from `--color-warning` for the same reason `--color-danger-solid` is: white on `#ff7d52` is 4.1:1 and fails AA at badge size, and on this it is 6.2:1. Fixed across both themes |
| `--color-warning-subtle` | `#fff2ee` | `white 10%` | Warning pill background |
| `--color-warning-text` | `#9e0000` | `#ff7d52` | Warning pill text. 7.8:1 on its fill. |
| `--color-info` | `#00acff` | `#00acff` | Informational |
| `--color-info-subtle` | `#f0faff` | `white 10%` | Info pill background |
| `--color-info-text` | `#005d8e` | `#00acff` | Info pill text. 6.7:1 on its fill. |
| `--cal-line` | `#e8e8ed` | `white 8%` | The rule between days on the month calendar, drawn as 1px gaps over this colour rather than as a border on each cell. It is the same value as `--color-border` in light and a step fainter in dark, where seven columns by six rows of `white 12%` reads as a table rather than as a diary. It is its own token rather than an alias because it has already diverged once, and because the grid is the one place in the app where a line is repeated eighty times |
| `--row-hover` | `color-mix(in oklab, var(--surface-sunken) 50%, transparent)` | same | A booking row under the pointer. Half the strength of the sunken grey in both themes, so it lands between the page and the sticky group headings above it instead of matching them and making a hovered row look like a heading |

**Which primitives move between themes, and which stay fixed.** This distinction
is the whole architecture of the dark mode and it is worth stating plainly.

**Fixed.** The accent, danger, success, warning and info hues at full strength do
not change. The hue is the identity. Changing it between themes makes an app feel
like two different products, and it also means every status pill needs re-learning
when a user switches. All five stay exactly as they are.

**Moving.** Surface, text and border all move, and they do not move to darker
versions of the light greys. In dark they become **translucent white over the
base**: `white 5%`, `white 12%`, `white 24%`, `white 50%`, `white 80%`. This is the
single most useful thing taken from their system. A translucent border stacks
correctly on any surface at any depth, so one border token works on the page, on a
card and inside a menu without a separate value for each. Solid dark greys do not
do that, and you end up inventing `border-on-card` and `border-on-menu`.

The tinted status backgrounds also move, and they move to `white 10%` rather than
to a darkened version of the hue. A 10% white fill behind coloured text reads as a
pill at any depth, whereas a darkened green stops being legible as soon as it sits
on a raised surface.

**Borders, shadows and elevation in dark.** A drop shadow does almost nothing on a
dark ground, so something else has to carry elevation, and in their system it is
the border alpha. Concretely:

- Inputs in light carry `0 0 0 1px rgb(10 10 46 / .16) inset` plus a 1px drop.
  In dark the drop is removed and the ring becomes `0 0 0 1px rgb(255 255 255 / .16) inset`.
  Same alpha, inverted colour, no shadow.
- Their popover shadow is five layers, and the first is `0 0 0 1px` at 10% opacity,
  which is a hairline ring, not a blur. In dark that ring is what you actually see;
  the four soft layers underneath do almost nothing.
- Cards carry a 1px border and no shadow, in either theme.

The rule that falls out: **in dark, raise things by making the border brighter, not
by making the shadow bigger.**

### The hover rule

There are two hover fills and which one you use is decided by what the element is
resting on, not by taste.

| Resting state | Hover fill |
| --- | --- |
| Transparent | `surface-sunken` |
| Already `surface-sunken` | `surface-hover` |

So a nav item, a ghost button, an outline button and a menu item all hover to
`surface-sunken`, and the secondary button, which rests on `surface-sunken`, hovers
to `surface-hover`. One step up from wherever you started.

**A nav item's hover is deliberately identical to its active fill.** Hovering
previews where you are about to land, and the active item stays distinguishable
because it also carries accent text and its dot marker. Fill alone was never
carrying that job.

**In Klaroly the hover takes the accent label too**, so a pointed-at row says
so in words as well as in fill. What separates it from the active row is then
the icon: grey while you are only hovering, accent once you are there. So the
active row is the only one where the label and the icon agree.

This supersedes an earlier note in this guide claiming a hover you cannot see is
not a hover. That is true of a table row, which is a 1px divider across a wide
column with nothing else to signal it, and it is why row hover became an accent
underline. It is not true of a 216 × 40 filled block, where the same `#f7f7f8`
reads clearly. The size and shape of the thing being hovered decides how much
contrast the hover needs.

### Type

Nine steps. Their compiled stylesheet ships eleven sizes and four weights. The
application renders four sizes and two weights.

| Token | Size / line / weight | Used for |
| --- | --- | --- |
| `text-title` | 24 / 32 / 500, tracking `-0.01em` | Page titles, panel titles |
| `text-figure` | 24 / 40 / 400 | Large numbers and money |
| `text-section` | 20 / 28 / 500 | Section headings, empty state titles |
| `text-lead` | 16 / 28 / 500 | Large button labels, prominent copy |
| `text-control` | 15 / 24 / 500 | The value inside an input, select or textarea |
| `text-body` + `font-medium` | 14 / 24 / 500 | Labels, nav items, names, emphasis |
| `text-body` | 14 / 24 / 400 | Everything else |
| `text-meta` | 13 / 24 / 400 | Counters, timestamps, secondary meta |
| `text-caption` | 12 / 16 / 400 | Dense phone meta. **Added, not observed.** |

Two weights only: 400 and 500. One tracking value, on the title. Nothing is bold.

`text-control` is the one step that is not in their rendered set. 15px is declared
in their config and never used anywhere in their app. It comes back here because a
value you have typed should carry slightly more weight than the label describing
it, and 14/500 next to a 14/500 label reads flat. A placeholder inside the same
control stays at 400, because a placeholder is a prompt rather than data.

### Spacing

The unit is **4px**. Their Tailwind config declares an 8px unit with fractional
steps, but what the application actually renders is a 4px grid: 8, 12, 16 and 24
are all heavily used, and 12 is the single most common value in the whole app.
2px survives for one purpose only, the inset of the toggle track.

| Value | Where it earns its place |
| --- | --- |
| 2px | Toggle track inset. Nothing else. |
| 4px | Icon gaps, tight inline spacing |
| 8px | Button gap, control vertical padding, menu padding |
| 12px | Label to control, hint to control, small button padding |
| 16px | Control horizontal padding, table cell padding, page gutter |
| 24px | Field to field, card padding, section band padding |
| 32px | Auth column padding |
| 40px | Section to section, desktop page gutter |
| 48px | Page-level breathing room |

**Where they break their own grid:** small buttons at 4px vertical padding, large
buttons at 10px, the search input at 6px, and a scattering of 20px and 28px
one-offs. All are dropped here in favour of explicit control heights, which is
both tidier and better for tap targets.

Semantic spacing tokens, which are the ones components use:

| Token | Default | Phone (< 640px) |
| --- | --- | --- |
| `--space-field-gap` | 24px | 16px |
| `--space-label-gap` | 8px | 8px |
| `--space-section-gap` | 40px | 24px |
| `--space-row-padding` | 16px | 12px |
| `--space-page-gutter` | 16px | 16px |
| `--control-height` | 48px | 48px |
| `--control-height-sm` | 36px | 36px |
| `--control-height-lg` | 56px | 56px |

**Control heights do not move between desktop and phone.** At 48px they already
clear the 44px tap minimum, so there is nothing to correct on a small screen, and
that removes a breakpoint rule rather than adding one. Spacing compresses;
controls stay put.

### Radius

| Token | Value | Used for |
| --- | --- | --- |
| `--radius-xs` | 4px | Thumbnails, small indicators |
| `--radius-control` | 8px | Buttons, inputs, selects, menus, pills, nav items |
| `--radius-card` | **12px in Klaroly**, 8px at source | Cards, and a menu panel from lg up |
| `--radius-pill` | 9999px | Avatars, toggles, round icon buttons |
| `--radius-sheet` | 24px | **Klaroly only.** The tab bar, and a menu panel below lg, where it is a bottom sheet. No equivalent at source |

8px accounts for **76%** of every rounded corner in their application. Their
`rounded-full` is `100%`, which turns any non-square box into an ellipse; this
guide uses `9999px` instead.

**Klaroly deviation: `--radius-card` is 12px, not 8px.** The app arrived at 12px
before this system did, and on a phone, where a card is nearly full width, the
softer corner reads better against the page than the control radius does. They
were always separate tokens so that cards could diverge from controls without a
component being touched; this is that happening. `--radius-control` is unchanged
at 8px, so buttons, inputs and menus are still faithful to the source.

### Border

| Token | Value | Used for |
| --- | --- | --- |
| `--border-width-control` | 1px | Every border in the app |
| `--border-width-focus` | 2px | Focus rings and error rings |
| `--border-width-strong` | 2px | The same width under a name that is not about focus, for a heavy edge that is not a focus state: the ring on a provisional day in the calendar, and the ring on the selected one |

Two widths, and that is the whole system. `--border-width-strong` is a second
name for the second of them rather than a third width, and it exists so that an
accessibility review taking the focus ring to 3px does not silently thicken
every mark in the calendar that merely wanted a heavy edge. If the two ever do
need to differ, they now can.

### Shadow

Three real shadows, and only one of them is a drop shadow. Cards carry a border,
never a shadow.

| Token | Light | Dark |
| --- | --- | --- |
| `--shadow-input` | `0 0 0 1px rgb(10 10 46/.24) inset` | `0 0 0 1px rgb(255 255 255/.24) inset` |
| `--shadow-input-hover` | same at 36% | same at 40% |
| `--shadow-input-focus` | `0 0 0 var(--border-width-focus) var(--border-focus) inset` | identical |
| `--shadow-input-invalid` | `0 0 0 var(--border-width-focus) var(--danger) inset` | identical |
| `--shadow-menu` | five layers, see below. Ring at 3% | ring at 12% plus two soft layers |
| `--shadow-card` | `none` | `none` |

The last two are the focus and error edges, and they are written as `var()`
rather than as values on purpose: both read `--border-width-focus`, so
thickening every form edge in the app is one variable rather than a hunt
through components. They are declared in `@theme inline`, which is what keeps
those references live so each one resolves in the theme it is drawn in.

**Klaroly drops the drop shadow under fields.** Theirs carries a `0 1px 1px` lift
under the inset ring. It reads as fussy at this density, it does nothing at all on
a phone, and removing it means the field's only edge is the inset ring, which is
what lets focus recolour that edge without the box moving by a pixel.

The popover shadow is what makes their menus feel expensive, and it is copied
almost verbatim. The one change is the ring, far lighter than their 10%:

```
0 0 0 1px  rgb(18 18 23 / .03),   /* theirs is .10 */
0 24px 48px rgb(18 18 23 / .03),
0 10px 18px rgb(18 18 23 / .03),
0 5px 8px   rgb(18 18 23 / .04),
0 2px 4px   rgb(18 18 23 / .04)
```

A hairline ring plus four very soft, very low-opacity layers. Not one big blur.

**The ring is the lever for how contained a menu looks**, and it is the only
part of this shadow anyone should expect to tune. Klaroly runs it very light,
so the panel reads as lying on the page rather than boxed off from it. The dark
theme's ring stays at 12% and must not follow: a drop shadow does nothing on a
dark ground, so there the ring is carrying the elevation as well as the edge,
and 3% would leave the panel with no edge at all.

### Layout

| Token | Value | Note |
| --- | --- | --- |
| `--container-sidebar` | 264px | 216px nav item plus 24px gutters |
| `--container-content` | 1096px | Theirs is 1600px, which is too wide to read. See the deviation below. |
| `--container-panel` | 640px | Slide-over |
| `--container-form` | 560px | A form column on a page |
| `--container-auth` | 464px | Auth column, 400px of field inside 32px padding |
| `--container-copy` | 400px | Centred supporting copy |
| `--container-action` | 280px | Minimum width of a lone primary action |
| `--container-split` | 760px | **Not a width.** The list-beside-detail breakpoint, for every screen that has one. Tailwind takes its container-query variant names from this namespace, so `@split:` is how a component asks whether its container is at least this wide; `@min-[760px]` would be an arbitrary value. Deliberately generic: a second screen inventing `--container-enquiries: 780px` gives the app two breakpoints that mean the same thing and disagree by 20px |
| Page gutter | 40px desktop, 16px phone | |
| Page header to content | 40px | |
| Header bar | none | The sidebar is full height; there is no top bar |
| Control heights | 32 / 40 / 48px | Small, default, large |
| Minimum tap target | 44px | **Klaroly rule, not theirs** |

Breakpoints are stock Tailwind: 640, 768, 1024, 1280.

### Motion

| Token | Value | Used for |
| --- | --- | --- |
| `--duration-fast` | 100ms | A mark that should land rather than glide: the tick arriving in a tick box |
| `--duration-base` | **250ms** | Everything else that moves: hovers, colour changes, toggles, the tab bar pill, the sheet |
| `--ease-out` | `cubic-bezier(0, 0, 0.2, 1)` | Everything |

One easing curve. **Klaroly runs the base at 250ms, not the 200ms measured at
source**, because the slower ease reads as considered rather than eager, which
is what this product is trying to be.

**The base duration is wired to Tailwind's own defaults**, in the theme block:

```css
--default-transition-duration: var(--duration-base);
--default-transition-timing-function: var(--ease-out);
```

Every `transition-*` utility resolves its duration and its curve through those,
so a plain `transition-colors` anywhere in the app is already the app's timing.
**No component writes a duration**, and changing `--duration-base` moves every
hover, toggle and slide at once. The two hand-written style blocks that animate,
the sheet and the tab bar pill, read `var(--duration-base)` directly for the
same reason.

**One thing to know before adding a transition to a focusable element.**
`transition-colors` includes `outline-color`, so anything carrying it fades its
focus ring in from whatever the text colour happens to be, and at the base
duration that is a keyboard user watching a white ring on a white page turn
purple. The fix is
in the base layer and applies everywhere: focusable elements carry
`outline-color: var(--border-focus)` at rest, so focus changes only
`outline-style`, which does not animate, and the ring is there the instant it
is asked for. A control whose edge is an inset shadow eases that edge with
`transition-shadow` instead, which is safe because the edge is on the screen at
rest: easing changes its colour rather than deciding when it appears.

---

## Application shell

The frame everything else sits in. Measured from their app, then stated as rules.
Their information architecture is not taken; the proportions and the rhythm are.

### The one rule that makes it look considered

**The sidebar's logo block and the content column's page-header row are the same
component.** Both are a 40px-tall flex row, both start 24px from the top of the
viewport, and both have 32px beneath them before their column's content begins.

```
y=24   ┌─ logo row, 40px ─┐ ┌─ page header row, 40px ──────────────┐
y=64   │   32px gap        │ │   32px gap                          │
y=96   └─ nav starts ──────┘ └─ content starts ────────────────────┘
```

That is why the two columns appear to line up across the gutter, and it is worth
copying exactly. Inside those rows the contents are optically centred rather than
top-aligned: a 24px logo sits at y=32, a 24/32 page title sits at y=28, and both
land on a centre line at y=44.

If you take one thing from their shell, take this.

### Sidebar

A full-height flex column, `264px` wide, in three parts.

| Part | Construction | Height |
| --- | --- | --- |
| Logo block | 40px row, 40px left padding, 28px right, `justify-between`, 32px below | 40px |
| Nav column | 24px horizontal padding, grows to fill | flexible |
| Footer block | `margin-top: auto`, 24px horizontal padding | 113px |

**Nav rhythm.** Every row is 40px, with a **4px gap** between them. Theirs has no
gap at all: one continuous 40px rhythm from the first item to the last, and that
consistency is most of why it reads as tidy. Klaroly runs the small gap.

**Group separators.** Theirs has none in the whole nav column, and the only
hairline in the sidebar is in the footer, above its last row; sections are
distinguished by expanding, not by dividing. Klaroly has one hairline, between
the main group and Settings, My account and Help.

| Item | Padding | Type |
| --- | --- | --- |
| Top level | `0 12px` | 14 / 24 / 500 |
| Sub-item | `0 16px 0 48px` | 14 / 24 / 500 |

So a sub-item indents 36px from its parent. Each one carries a **5px round dot**
at 21px from the left, vertically centred: `border` grey when idle, `accent` when
active. A column of those dots is what reads as a connecting line down the group.

**Active state** is the sunken fill plus accent text, on both levels.

### Sidebar footer

The pattern Klaroly wants for the account block:

| Row | Content |
| --- | --- |
| Account row | 40px, `0 12px` padding, 24px round avatar, name at 14 / 24 / 500, hover fill, opens a menu upward |
| Hairline | 1px `border`, inset 12px each side, 12px above and below |
| Utility row | 40px, same padding. Theirs is a test-mode toggle |

The menu that opens from the account row is the popover component, 232px wide,
anchored bottom-left, 8px above the trigger.

### Content column

| Property | Value |
| --- | --- |
| Position | starts immediately after the sidebar, at x=264 |
| Padding | 24px top, 40px left and right, 128px bottom |
| Maximum width | theirs 1600px; **Klaroly 1096px** (`--container-content`) |
| Header row | 40px, 32px below it, as above |
| Actions | right-aligned inside the header row |

**The bottom padding is 128px and it is not a mistake.** It keeps the last row of
a long list clear of the bottom of the window, and on a phone it is what stops
content hiding under a fixed tab bar.

### Top-right cluster

Sits inside the content column's 40px header row, right-aligned to the 40px
gutter.

| Element | Size |
| --- | --- |
| Icon buttons | 40 × 40, 8px padding, `--radius-control`, icon at `text-subtle`, hover `surface-hover` |
| Primary action | 36px circle, filled accent, 200ms transform on hover |

Icon buttons butt against each other with no gap, in the same way nav rows do.

### Klaroly's deviations

1. **Settings expands in the sidebar, and nothing appears in the content column.**
   Their settings pages carry a second nav inside the content area as well as the
   expanded sidebar group, which is the same list twice. Klaroly shows it once, in
   the sidebar, and gives the content column entirely to the setting being edited.

2. **Opening a section collapses the others.** One group open at a time. Their
   nav can have several expanded at once, which on a long settings list pushes
   everything else off screen.

3. **The content column caps at 1096px.** See the phone-first rules.

4. **The footer block carries the business, not the store.** Account row is the
   business name; the menu above it holds the things you would otherwise hunt for.

## Component anatomy

Measured values, and the class string that produces each. Where a plain utility
string cannot do the job, the component class from `tokens.css` is named and the
reason is given.

### Text input

40px tall, 8px 16px padding, 8px radius. **The border is not a border.** It is a
1px inset box-shadow, which is why the control does not shift by a pixel when it
gains a focus ring.

| Property | Light | Dark |
| --- | --- | --- |
| Height | 48px, on every screen | same |
| Type | 15 / 24 / 500 (`text-control`) | same |
| Placeholder weight | 400 | 400 |
| Padding | 16px inline, vertical derived from the height | same |
| Radius | 8px | 8px |
| Background | `#ffffff` | transparent over the base |
| Ring | `rgb(10 10 46/.24)` inset | `rgb(255 255 255/.24)` inset |
| Hover ring | same at 36% | same at 40% |
| Drop shadow | **none** | none |
| Focus | the ring itself becomes 2px `#7047eb`, no outer ring | identical |
| Placeholder | `#8a8aa3` | `white 50%` |
| Disabled | background `#f7f7f8`, text muted | background `#35343d` |
| Error | 2px `#f53d6b` inset ring | identical |
| Textarea | min-height 88px | |

**Focus is the field's own border, not a ring around it.** Because the border is
an inset shadow rather than a real border, recolouring and thickening it moves
nothing. This is a deliberate change from their treatment, which floats a 2px ring
outside the control.

**Buttons and links keep the outer ring**, because a button has no border to
recolour and removing the ring would leave a keyboard user with nothing at all.
That split is the rule: if a control has a visible edge, focus recolours the edge;
if it does not, focus draws a ring.

**A specificity trap worth knowing about.** `.k-field:hover:not(:disabled)` is
three classes' worth of specificity and `.k-field:focus` is two, so the hover rule
silently wins over the focus rule and a field you have just clicked keeps its
hover ring until the pointer leaves. The hover rule therefore carries
`:not(:focus)`. Any component whose hover and focus both set the same property
needs the same treatment.

An invalid field keeps its red ring when focused rather than turning accent, so
the error does not appear to have been resolved just because the cursor is in the
field.

```html
<input class="k-field" type="text">
<input class="k-field" aria-invalid="true" aria-describedby="err">
```

`.k-field` is a component class rather than a utility string, because the border
width and the control height both have to read from a variable so the levers can
move them, and Tailwind v4 has no border-width theme namespace.

### Label, hint and error

| Element | Size / weight | Colour | Gap |
| --- | --- | --- | --- |
| Label | 14 / 24 / 500 | `text-strong` | 8px above the control |
| Hint | 14 / 24 / 400 | `text-muted` | 8px below the control |
| Error | 14 / 24 / 400 | `danger-text` | 8px below the control |
| Required marker | ` *` | `danger` | appended to the label |

Their gap is 12px. Klaroly uses 8px, which binds the label to its control more
tightly and makes the 24px gap between fields read as the real separator.

```html
<label class="mb-label block text-body font-medium text-text-strong">Client name</label>
<p class="mt-label text-body text-text-muted">The address you will travel to.</p>
<p class="mt-label text-body text-danger-text">That is not a complete email address.</p>
```

### Field and section gaps

| Gap | Desktop | Phone |
| --- | --- | --- |
| Field to field | 24px | 16px |
| Label to control | 8px | 8px |
| Section to section | 40px | 24px |

```html
<div class="mb-field"> … </div>
```

### Buttons

No fixed height in their CSS; height comes from padding plus line height. Klaroly
sets the height explicitly instead, so buttons and inputs line up exactly whatever
the border width is.

| Variant | Background | Text | Hover |
| --- | --- | --- | --- |
| Primary | `accent` | white | `accent-hover`, which is **darker** |
| Secondary | `surface-sunken` (`#f7f7f8`) | `text` | `surface-hover` |
| Outline | transparent, 1px `border-strong` | `text` | `surface-sunken` |
| Ghost | transparent | `text` | `surface-sunken` |
| Danger | `danger-solid` | white | `danger-solid-hover`, darker again |

| Size | Height | Padding | Type |
| --- | --- | --- | --- |
| Small | 36px | 0 12px | 14 / 24 / 500 |
| Default | 48px | 0 16px | 14 / 24 / 500 |
| Large | 56px | 0 16px | 16 / 28 / 500 |
| Icon only | square at the size's height | 0 | |

**The secondary button is their grey**, `#f7f7f8`, moving to `#e8e8ed` on hover.
A darker `#d1d1db` was tried and rejected: it reads as a second primary rather
than a way out, and a cancel action should not compete with the thing beside it.

The cost, so it is on the record: the resting fill is a 1.07:1 change against the
page, which is below the 3:1 WCAG 1.4.11 asks of a control boundary. The label
inside it is 14.2:1, so the *text* is never in doubt, and the button is always
paired with a filled primary that establishes where the actions are. It is a
judged trade rather than an oversight.

**Filled buttons darken on hover, they do not lighten.** Lemon Squeezy lighten
theirs, which looks fine and quietly makes the label harder to read: white on
their hover purple is 4.6:1, barely over the line. Darkening takes it to 7.6:1.
The white label is the thing the hover has to keep legible, so the fill moves away
from white, not towards it. This is also why their dark theme has a button whose
hover is fainter than its resting state.

**The danger button does not use `--color-danger`.** White on `#f53d6b` is 3.6:1,
which fails AA, and a destructive button is the last place to have a label you
cannot read. Filled danger uses `--color-danger-solid` at `#d50b3e`, which is
5.3:1, and darkens to 7.5:1 on hover. `--color-danger` keeps the original value
and is still what error rings, required markers and pill text use, because those
sit on white or on a light tint where it measures fine.

Disabled is `opacity: .5` and `cursor: not-allowed`. Anything else that can be
clicked takes the pointing hand, which is a single base rule in
`src/assets/app.css` rather than a class per button, because Tailwind 4's reset
gives a button the ordinary arrow. Radius is `--radius-control`. Gap between
icon and label is 8px.

An icon may sit on either side of the label, and the side is the label's to
decide: a leading icon for what the button does, a trailing one for where it
goes, so Next carries a right chevron and Back a left one.

```html
<button class="k-btn k-btn-primary">Confirm booking</button>
<button class="k-btn k-btn-secondary k-btn-sm">Save draft</button>
<button class="k-btn k-btn-primary k-btn-lg w-full">Sign in</button>
```

**Their action-bar pattern is worth keeping.** A small auto-width secondary and a
primary that grows to fill the rest. It reads correctly on a phone with no
changes, unlike a right-aligned pair.

```html
<div class="flex gap-2">
  <button class="k-btn k-btn-secondary">Cancel</button>
  <button class="k-btn k-btn-primary grow">Save changes</button>
</div>
```

### Select, checkbox, radio and toggle

**Select.** The text input with `appearance: none`, `cursor: pointer` and the
icon set's `chevron-down` at 20px in `text`, sitting 16px from the right edge,
which is where a text input's status mark sits. It is the body text colour and
not `text-muted`, because it is the only thing on the control saying there is
a list behind it. The platform's own arrow is a hairline
and reads thinner than every other mark on a form, and the chevron is the one
part of a select that can be replaced without giving up the native picker.

The static `kitchen-sink.html` fakes the same chevron with two gradients,
because a bare `<select>` cannot hold an icon and that file has no components
to reach for. The app draws the real one.

```html
<select class="k-field k-select"> … </select>
```

**Checkbox.** 20px box, 4px radius, 1px `border-strong`. Checked is a filled
`accent` box with a white tick, drawn as a centred background image rather than a
rotated pseudo-element, because a rotated pseudo-element drifts off centre.

Theirs is 16px and sits at `opacity: .4` when unchecked, which is too faint to
read as something you can click. Klaroly uses 20px at full opacity.

**Radio.** Same box at `--radius-pill`, with a 7px white dot when checked.

**The row is the control.** A 20px box is smaller than a thumb, so the whole row
carries the hit area and the hover state, not just the box. `.k-option` gives a
44px minimum height and a focus ring on the row when the input inside it is
focused.

**The box is centred against the words, 8px from them.** Both were once the
other way: the box was pinned to the first line with a 2px nudge to make it
look level, and the gap was 12px. A one-line option is the only kind either
control is written with, so centring is what actually lines them up, and 8px is
the same gap that binds a label to its control everywhere else on a form.

**Hover is the label turning accent, not a background fill.** A grey box behind
every option in a form is a lot of furniture for a hover state, and it fights the
fewer-boxes rule. The row sets `color: var(--color-accent-text)` on hover and lets
it inherit, which means any text that has not claimed its own colour turns accent
and any text carrying `text-text-muted` keeps its own. So a title highlights and
its description stays quiet, with no extra markup and no second class to remember.
The unchecked box picks up the same accent on its border, so the control and its
label respond together.

**The row is medium weight and the description opts out**, by exactly the same
mechanism: `.k-option` sets `font-weight: 500` and a description carries
`font-normal` alongside its `text-text-muted`. One rule to remember for both
properties, rather than two.

```html
<!-- title highlights on hover, description stays muted -->
<label class="k-option">
  <input type="radio" name="travel" class="k-check k-radio">
  <span>
    <span class="block text-body">Included</span>
    <span class="block text-body text-text-muted">No separate charge for travel.</span>
  </span>
</label>

<!-- single line: the whole label highlights -->
<label class="k-option items-center">
  <input type="checkbox" class="k-check">
  <span class="text-body">Send the client a confirmation email</span>
</label>
```

The only rule to follow when writing one of these: **do not put a colour or weight
class on the text you want to highlight.** Those classes are how a description
opts out of the row's hover colour and its medium weight.

This applies to every checkbox and radio in the app, including single ones. If a
control can be toggled, the label toggles it and the label shows a hover.

**Radio card.** For a small set of meaningful choices. 1px `border-strong`,
`--radius-card`, 16px padding, a 14/24/500 title and a 14/24/400 muted
description. Selected is a 1px `accent` border plus a 1px `accent` ring, which
gives a 2px edge without the box moving.

**Hover is the same 1px edge in `--border-accent-soft`**, which is
`color-mix(in oklab, var(--accent) 70%, var(--surface))`: the accent softened
by the ground the card is on, so one definition is right in both themes. It
started at 50% and was taken to 70% because half the accent was too quiet to
read as a response. Softer than the accent and half the edge is what keeps a
card under the pointer from reading as the selected one, and it is the only
hover the card has. Only an unselected, enabled card takes it: a selected card
is already wearing the accent, and a disabled one is not going anywhere.

**Focus on a radio card is an outer ring, and it is the one exception to the
rule below.** Everywhere else, a control with a visible edge recolours that
edge on focus. Here the edge is already carrying a meaning: it is how the card
says it is selected. Recolouring it on focus would make a focused card that is
not selected look exactly like a selected one, and the person tabbing through
would have no way to tell which of the two they were looking at. So the card
takes a ring at `--border-focus`, `--border-width-focus` thick, offset 2px, and
the ring sits outside the edge without touching it. All four combinations then
read differently: selected, focused, both, and neither. The radio inside the
card is visually hidden but focusable, so several cards sharing a `name` are
one arrow-key group like any other radio set.

**Toggle.** Track 48 × 28, 4px inset, knob 20px white, `--radius-pill`, 200ms
transform over 20px of travel. Off is `border-strong`, on is `accent`. The knob
moves with a transform and not with a layout change, because the point of the
200ms is that the travel can be watched.

Theirs is 30 × 18 with a 14px knob, which is a 30 × 18 tap target and fails the
44px minimum badly. Klaroly's track is 48 × 28 and a pseudo-element extends the
hit area to 48 × 44 without changing anything visible.

```html
<input type="checkbox" class="k-check">
<input type="radio" class="k-check k-radio">
<input type="checkbox" class="k-switch">
```

### Card and panel

| Property | Value |
| --- | --- |
| Padding | 24px |
| Radius | `--radius-card`, 8px |
| Border | 1px `border` |
| Shadow | none, in both themes |
| Gap between cards | 24px |

```html
<div class="k-card"> … </div>
```

**Their application has no card component.** This is recorded so it is available
when it is wanted, but it is worth saying plainly that across eight screens they
never wrap a settings form, a stat row or a list in a card. Their dashboard "stat
cards" are a `border-top` with 40px of vertical padding. Their form sections are a
sunken bar with a 40px height, not a bordered box. See the deviation rules below.

**Section band**, which is their answer to a panel header. **Klaroly builds it
at 48px, not the 40px measured at source**, so that it matches the control
height everything else on a form is on:

```html
<div class="flex h-12 items-center justify-between rounded-control bg-surface-sunken px-4">
  <span class="text-body font-medium text-text-strong">Pricing</span>
</div>
```

Optionally collapsible, in which case the bar is a real button carrying
`aria-expanded` and `aria-controls`, and it takes a focus ring like any other
button.

### Table and list row

| Property | Value |
| --- | --- |
| Header cell | 12px vertical padding, 14/24/**400**, `text-muted` |
| Body cell | 16px vertical padding, 14/24/400 |
| Row height | 48px plus a 1px divider |
| Cell horizontal padding | 16px, with the first and last cells flush |
| Divider | 1px `border` on the bottom of each row |
| Row hover | the divider **recolours to `accent`**, still 1px, over 200ms |
| Header row | a bottom divider only, no fill |

Their table header is regular weight and muted, not bold. It is a good decision
and it is kept.

**Row hover recolours the divider that is already there.** Their fill is
`#f7f7f8`, a 1.07:1 change against white and effectively invisible, and they have
to patch it with two offset pseudo-elements so the highlight does not stop at the
last cell. Transitioning the existing `border-bottom` from `border` to `accent`
costs nothing, spans the whole row for free, and adds no second line: the row does
not grow, nothing is drawn on top of anything, and the same 1px sits in the same
place throughout. The same treatment is used on list rows.

Do not draw the hover as an extra line. A 2px accent underline sitting above a 1px
grey divider reads as three pixels of rule and pulls the eye out of the table.

```html
<table class="k-table w-full border-collapse text-body">
  <thead><tr class="text-left text-text-muted">
    <th class="py-3 pr-row font-normal">Client</th>
  </tr></thead>
  <tbody><tr><td class="py-row pr-row">…</td></tr></tbody>
</table>
```

**List row** is the phone form of the same data, and on Klaroly it is the default:

```html
<ul class="k-list border-t border-border">
<li class="flex items-center gap-4 py-row">
  <div class="grid size-10 shrink-0 place-items-center rounded-pill bg-accent-subtle
              text-body font-medium text-accent">HW</div>
  <div class="min-w-0 grow">
    <div class="truncate text-body font-medium text-text-strong">Hannah Whitfield</div>
    <div class="truncate text-body text-text-muted">14 Jun, 6:30am</div>
  </div>
</li>
```

### Page header

Title, description, and actions on the right, over a hairline divider.

| Element | Value |
| --- | --- |
| Title | 24 / 32 / 500, tracking `-0.01em`, `text-strong` |
| Description | 14 / 24 / 400, `text-muted` |
| Actions | right aligned, bottom aligned with the title block |
| Divider | 1px `border` below, 24px of padding above it |
| Gap to content | 40px |

```html
<div class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-6">
  <div>
    <h1 class="text-title font-medium text-text-strong">Bookings</h1>
    <p class="text-body text-text-muted">Everything confirmed and pending.</p>
  </div>
  <button class="k-btn k-btn-primary">New booking</button>
</div>
```

### Sidebar item

| Property | Value |
| --- | --- |
| Height | 40px |
| Padding | 0 12px, or 0 12px 0 48px for a nested item |
| Radius | `--radius-control` |
| Type | 14 / 24 / 500 |
| Idle | `text-strong`, transparent background |
| Hover | `surface-sunken`, **the same fill as active**, plus an `accent-text` label. The icon does not follow |
| Active | `surface-sunken` background, `accent` text, plus the dot marker |
| Icon | 24px, 12px gap to the label, `text-placeholder` idle **and on hover**, the row's own colour only when active |
| Group separation | **none.** See the shell section |
| Column width | 264px total, 216px item, 24px gutters |

```html
<a class="flex h-10 items-center rounded-control bg-surface-sunken px-3
          text-body font-medium text-accent">Bookings</a>
```

Their sidebar has no group separators at all. The nav is one continuous 40px
rhythm from top to bottom, and expanding a section inserts its children into that
same rhythm. The only hairline in the whole column is in the footer block, above
the last row. This is covered properly in the shell section below.

### Menu and popover

This is the component to copy most carefully, because it is where their app feels
most finished.

| Property | Value |
| --- | --- |
| Panel | `surface-overlay`, `--radius-card`, 12px padding, and no border of its own: the hairline ring is the shadow's first layer. As a bottom sheet below `lg` it keeps `--radius-sheet` on its top corners, 16px padding, `--shadow-raised` and a real top border |
| Elevation | `--shadow-menu`, the five-layer shadow, whose 3% ring is the panel's only edge |
| Minimum width | 200px, or the trigger width where that is wider |
| Item | 40px tall, 0 16px padding, 14 / 24 / 500, 12px gap to its icon, 4px between rows. Same weight as a sidebar item, because the menu opens beside one |
| Item hover | `surface-sunken` fill, with the label and the icon both going `accent-text` |
| Offset from trigger | 8px |
| Transform origin | the corner nearest the trigger |
| Enter | opacity 0 to 1 over 200ms, with a 16px slide out of the trigger: down from a menu anchored below it, up from one anchored above. Reversed on the way out |
| Trigger | 200ms transform, for a plus that rotates into a close |

Note what the animation is not: there is no scale and no spring. Theirs has no
slide either, and Klaroly adds one, but only 16px and always in the direction
the menu opens, so the panel reads as coming out of the button that was clicked
and going back into it. Anything longer sweeps the panel across the sidebar
instead. Below `lg` the same panel is a bottom sheet and travels its own height
up from the bottom edge, which is the one place a long move is right.

```html
<div class="k-menu">
  <div class="k-menu-item">New booking</div>
  <div class="my-2 border-t border-border"></div>
  <div class="k-menu-item text-danger-text">Sign out</div>
</div>
```

### Badge and status pill

Inline flex, `--radius-control`, 10px horizontal and 2px vertical padding,
**13 / 24 / 500**. The background is the status `subtle` token and the text is the
status `text` token, which is what makes it survive the theme flip.

One step down from body size, and medium weight to hold its own at that size.
Their pill is body size at regular weight in a light green that washes out; the
text colours here were darkened until each pill measured at least 4.8:1 against
its own fill. Success went from 4.3:1 to 6.5:1 and warning from 3.7:1, which
failed AA outright, to 5.7:1.

```html
<span class="k-pill bg-success-subtle text-success-text">Confirmed</span>
```

**A fifth tone, neutral, is Klaroly's and not theirs.** It is
`surface-sunken` behind `text-muted`, and it exists because this product has
states that are not good news, bad news or a warning. An enquiry at New or In
conversation is simply where it is, and colouring it as a success or a warning
would be saying something about it that is not true. The tone is what the
component takes; which booking mark or enquiry stage maps to which tone is the
screen's decision, and no component knows the names of any of them.

### Empty state

Centred, no illustration and no icon. A heading, at most two lines of copy, and
one primary action with a 280px minimum width.

| Element | Value |
| --- | --- |
| Container | centred, roughly `100vh - 230px` tall |
| Title | 24 / 32 / 500, 16px below |
| Body | 14 / 24 / 400, `text-muted`, max 400px, centred |
| Action | primary, `min-width: 280px`, 24px below the body |

The restraint here is the good bit. No illustration to draw, none to maintain, and
nothing to redraw when the brand changes.

### Slide-over

| Property | Value |
| --- | --- |
| Width | 640px, or 90% below that |
| Position | full height, right edge |
| Padding | 24px vertical, 32px horizontal |
| Scrim | `--color-scrim`, no backdrop blur |
| Title | 24 / 32 / 500, with a close button opposite |
| Footer | pinned, `border-top`, 24px padding, the grow-primary action bar |
| Body | scrolls independently of the header and footer |

### Auth screen shell

Measured properly, because Klaroly's sign in, register, forgot password and reset
password screens are already built and this goes straight onto them.

**Layout.** A split, not a centred card. The form column is `w-full md:w-1/2`, and
the right half is a decorative panel that is `hidden` below 768px. Since their
right half is a brand illustration and we are not taking it, Klaroly's version is
the form column full width, with the right panel available later if it is wanted.

**The form column.** Not a card. It sits directly on the page background with no
border and no shadow, which is why the whole screen reads as white.

| Property | Value |
| --- | --- |
| Column max width | **464px**, centred (`--container-auth`) |
| Column padding | 64px vertical, 32px horizontal |
| Field width | **400px** |
| Page background | `surface`, same as the form background |
| Logo | 40px tall, centred, 40px above the heading |
| Heading | 24 / 32 / 500, centred |
| Sub-heading | 14 / 24 / 400, `text-muted`, max 400px, centred, 16px below the heading |
| Heading to first field | 40px |
| Label to field | 8px |
| Field to field | 24px |
| Last field to submit | 24px |
| Submit | primary, **large (56px)**, full width |
| Secondary control below | 24px below the submit, centred, as a `.k-option` row |
| Footer links | 40px below, centred row, 24px apart, 14 / 24 / 400 `text-muted`, `accent` on hover |

**Theirs is a 560px column with 496px fields.** That is too wide for a two-field
form: the eye has to travel the whole width to confirm what it typed. Klaroly's
column is 464px with 400px fields, which is the same proportion at a comfortable
reading width, and both numbers sit on the 8px grid.

```html
<div class="mx-auto w-full max-w-auth px-8 py-16">
  <div class="mb-10 flex justify-center"> … logo … </div>
  <h1 class="mb-4 text-center text-title font-medium text-text-strong">Sign in to Klaroly</h1>
  <p class="mx-auto mb-10 max-w-copy text-center text-body text-text-muted"> … </p>
  <div class="mb-field"> … label + input … </div>
  <button class="k-btn k-btn-primary k-btn-lg w-full">Sign in</button>
  <label class="k-option mt-6 items-center justify-center">
    <input type="checkbox" class="k-check"><span class="text-body">Keep me signed in</span>
  </label>
</div>
```

**One thing not to copy.** Their auth forms have no labels at all, only
placeholders. That is an accessibility failure and it also means the field loses
its name the moment you start typing. Every Klaroly auth field gets a real label.

---

## Where Klaroly deviates, as rules

### Rule 1: fewer boxes

**Prefer whitespace and a hairline divider to a card.** A card is for a group of
content that genuinely needs lifting off the page, not for every section.

Nested white panels waste space, and on a phone they waste it twice: once for the
border and padding, and again because the content column narrows.

The card anatomy is recorded above so it is there when it is wanted. The default
is not a card.

This one turned out to be better supported by the source than expected. Lemon
Squeezy's own settings screens are divider-separated rows sitting directly on the
page background, with no card anywhere: a `border-bottom`, 24px of vertical
padding, label and helper text on the left, control on the right. Their stat rows
are a `border-top` with padding. Their form sections are a sunken 40px bar. Across
eight screens the only bordered boxes are selectable radio cards and popovers.

**In practice:**

- A settings row is `border-b border-border py-row`, two columns above `lg`.
- A group of related content is a heading plus `border-t border-border pt-4`.
- A card is `.k-card`, and needs a reason.

### Rule 2: phone-first density

**Their scale is tuned for a 1440px monitor used sitting down. Klaroly's is for
someone holding a phone at 7am in a hotel room.**

Do not shrink everything. Four tokens change below 640px, and they are the four
that actually cause scrolling:

| Token | Desktop | Phone | Why this one |
| --- | --- | --- | --- |
| `--space-field-gap` | 24px | 16px | Repeats once per field; the biggest single win on a long form |
| `--space-section-gap` | 40px | 24px | Repeats once per section |
| `--space-row-padding` | 16px | 12px | Repeats once per list row and table cell |
| `--space-page-gutter` | 40px | 16px | Buys 48px of content width |

**What deliberately does not change on a phone:** control heights, type sizes, the
label gap, and radii. Shrinking a control on the device where it is hardest to hit
is the wrong instinct, and 14px body text is already at the floor.

**Minimum tap target is 44px.** Standard controls are 48px, so they clear it
everywhere without a breakpoint. Where something is visually smaller, it gets
padding or a pseudo-element to bring the hit area up. This is why the toggle here
is 44 × 26 rather than their 30 × 18.

### Rule 3: simple over decorated

Where they have two ways of doing something, take the plainer one.

- Sidebar groups are separated by a hairline, not a heading.
- Empty states are text and a button, with no illustration.
- The menu animation is an opacity fade, with no scale or slide.
- Row hover is a background change on the row, not their pair of offset
  pseudo-elements faking a full-bleed rounded pill.
- Section headings are a sunken bar, not a bordered panel with a header.

---

## Rules for adding a new component

1. **Every value comes from a semantic token.** Colour, size, space, radius,
   border, shadow and duration. Never a raw hex, never a raw pixel, never a
   primitive like `--color-neutral-300`.
2. **No Tailwind arbitrary values. None, anywhere.** No `p-[13px]`, no
   `text-[#7047eb]`, no `max-w-[420px]`. Every width a layout needs is already a
   token in the container scale (`max-w-content`, `max-w-panel`, `max-w-form`,
   `max-w-auth`, `max-w-copy`, `min-w-action`). `kitchen-sink.html` renders the
   entire system with zero arbitrary values, so there is a worked example that
   this is achievable rather than aspirational.
3. **If the value you need has no token, stop and ask.** Do not invent one. If a
   token is added, it is recorded in this guide in the same change.
4. **Minimum tap target is 44 × 44.** Standard controls are 48px and clear this
   on their own. Anything visually smaller, such as a checkbox, a radio or a
   toggle, gets the hit area back through the row that wraps it or through a
   pseudo-element.
5. **Every interactive element has a visible focus state.** If it has a visible
   edge, focus recolours that edge to `--color-border-focus` at
   `--border-width-focus`. If it does not, focus draws a ring at the same colour
   and width, offset 2px. Never `outline: none` without putting one of the two
   back. The radio card is the one documented exception, and the reason is in
   its own paragraph above.

   In the app the ring is one utility, `focus-ring` in `src/assets/app.css`,
   written as `focus-visible:focus-ring` (or `peer-focus-visible:focus-ring`
   on the radio card). It reads `--border-width-focus` and `--border-focus`,
   so the width lever moves every ring as well as every recoloured edge.

   **Where a control recolours its edge, suppress the browser's ring with
   `outline-hidden`, never `outline-none`.** The two are not the same thing.
   `outline-hidden` leaves a transparent outline in place, which a forced
   colours mode turns back into a real ring; `outline-none` removes the outline
   outright. An inset box-shadow is not painted in forced colours at all, so a
   control that carries its edge as a shadow and its focus as that same shadow
   would, with `outline-none`, leave somebody in that mode with no focus
   indicator whatsoever.

   And removing the classes is not the same as removing the ring. Taking
   `focus-visible:focus-ring` off a control does not leave it ringless: it hands
   the job to the browser's own stylesheet, and the control then draws a ring
   and a recoloured edge at once. Suppress it deliberately or not at all.
6. **It works in both themes.** Check dark before opening a pull request. If a
   colour has no dark value, it is not a semantic token and it does not belong in
   a component.
7. **It works at 390px first.** Build the phone layout, then add the wider one.
8. **Prefer a divider to a card.** See rule 1 above.
9. **Semantic HTML.** One `h1` per page, real `main` and `nav` landmarks, real
   labels on every field. Their app has none of these and it is not a model to
   follow here.

---

## The levers

The point of the two-layer structure is that the personality of the whole app
changes by editing a handful of variables, not by touching components.

| Lever | Set it to | What happens |
| --- | --- | --- |
| `--radius-control-value` | `0px` | Every input, button, select, menu, pill and nav item squares off at once |
| `--radius-card-value` | `0px` | Every card and radio card squares off, independently of the controls |
| `--border-width-control` | `2px` | Every form border, outline button and card edge thickens at once, and controls stay exactly the same height |
| `--color-border` | a darker value | The whole interface becomes harder edged |
| `--space-field-gap` | larger or smaller | Every form in the app loosens or tightens |
| `--space-section-gap` | larger or smaller | The vertical rhythm of every page changes |
| `--control-height` | `36px` or `48px` | Every button, input and select resizes together |
| The neutral ramp | the hue number changed on ten values | The whole feel shifts from cool to warm, with no component edits. Their ramp holds a constant hue of 285°, so moving all ten to, say, 65° gives a warm grey with identical lightness and chroma. This is the lever oklch exists for. |
| `--color-accent` | one value | **This is the single line that replaces the borrowed brand.** |

**There is no lever switcher in `kitchen-sink.html`.** An earlier version had a
control that flipped between a default, a squared and thicker variation and a
tighter variation, to prove that no component needed editing. It proved the point
and was then removed, along with all its code, because the default is the design
and the switcher was scaffolding. The variables above are unchanged and still do
exactly what the table says; there is simply no demo of them any more.

The proof still stands and is worth recording. Measured across the three states
before the control was removed, with no component edited:

| | Default | Squared and thicker | Tighter |
| --- | --- | --- | --- |
| Button radius | 8px | 0px | 8px |
| Button height | 40px | 40px | 36px |
| Input radius | 8px | 0px | 8px |
| Input border | 1px | 2px | 1px |
| Input height | 40px | 40px | 36px |
| Field gap | 24px | 24px | 12px |

One thing did have to be fixed to get there. The input was initially 42px tall
while the button beside it was 40px, because the border was adding to the height.
`.k-field` subtracts the border from its vertical padding, so a control is exactly
`--control-height` at any border width. Without that, thickening the border
misaligns every form row in the app.

**One honest limitation.** Border width and control height cannot be levers
through Tailwind utilities alone, because Tailwind v4 has no border-width theme
namespace and no height namespace that reads a variable. They need a thin
component layer, which is what `.k-field` and `.k-btn` in `tokens.css` are for.
That layer is about 120 lines and it is the only hand-written CSS in the system.

---

## Appendix: what was observed at source

### Method

Eight screens were walked in the signed-in application at 1440px and 1024px, in
both themes: dashboard, products index, product detail slide-over, orders,
customers, discounts (empty), settings general, settings team, settings account.
Three signed-out screens were captured separately: sign in, register and password
reset.

Values were not eyeballed. On each screen a walker read the computed style of
every visible element and tallied every font size, line height, weight, tracking,
padding, margin, gap, colour, border, radius, shadow and transition by frequency.
Their compiled stylesheet, all 23 files of it, was parsed separately for the scale
definitions and the component rules, which turned out to be defined as named CSS
classes rather than scattered utilities.

### Counts, before and after

| | Distinct at source | After rationalising | What went |
| --- | --- | --- | --- |
| **Type combinations** | 11 sizes, 6 line heights, 4 weights defined; **7 combinations actually rendered** | 9 steps | Sizes 10, 11, 18 and 30px are defined but never rendered. Line heights 48 and 56 likewise. Weights 600 and 700 are never used: everything is 400 or 500. `tracking-widest` is never used. Two steps were **added**: a 12/16 caption for phone density, and 15/24/500 for the value inside a control. 15px was cut in the first pass as unused and brought back deliberately. |
| **Colour** | ~60 distinct hex values across four naming systems; **33 rendered in light, 35 in dark** | 47 primitives, 31 semantic tokens | Dropped all `wtf-*` duplicates, all `light-*`/`dark-*` aliases of `wedges-*`, and the stock Tailwind leftovers (`gray-500`, `red-700`, `#e5e7eb`, `#9ca3af`, `#333333`, `#111827`). |
| **Spacing** | 12 declared steps; **15 distinct paddings rendered** | 9 steps | Dropped 6px, 10px, 20px, 28px, 128px and one 163px. |
| **Radius** | 8 declared; **4 rendered** | 4 | Dropped 6px, 18px, 24px and 40px. Replaced `100%` with `9999px`. |
| **Border width** | 2 | 2 | No change. |
| **Shadow** | 5 declared; **3 rendered** | 4 plus 3 dark variants | Dropped Tailwind's default `shadow` and `shadow-sm`, neither of which is used. |
| **Motion** | 4 durations, 2 easings; **2 durations, 1 easing rendered** | 2 durations, 1 easing | Dropped 75ms, 300ms and `ease-in`. |

**How many greys do they genuinely use?** Four, in practice. `#121217` for
headings, `#25252d` for body, `#6c6c89` for muted and `#a9a9bc` for subtle. The
ramp declares nine. Two of the four have a near-duplicate five units away
(`#6c6c84`) doing the same job under a different name.

**Is it a 4px or an 8px grid?** The config says 8px with fractional steps. The
rendered application is a 4px grid: 8, 12, 16 and 24 all appear hundreds of times,
and 12px is the single most common padding value in the app. The honest answer is
a 4px grid where 8px multiples dominate.

### Is their neutral ramp any good?

Converting it to oklch answers this, and it is worth recording because the ramp is
the part of the borrowed identity most likely to survive the rebrand.

| Step | Hex | L | C | H | ΔL |
| --- | --- | --- | --- | --- | --- |
| 50 | `#f7f7f8` | 97.6% | 0.0013 | 286.4° | |
| 100 | `#e8e8ed` | 93.2% | 0.0067 | 286.3° | 4.4 |
| 200 | `#d1d1db` | 86.4% | 0.0137 | 286.1° | 6.9 |
| 300 | `#a9a9bc` | 74.1% | 0.0271 | 285.7° | 12.3 |
| 400 | `#8a8aa3` | 64.2% | 0.0371 | 285.3° | 9.9 |
| 500 | `#6c6c89` | 54.2% | 0.0450 | 284.8° | 10.0 |
| 700 | `#3f3f50` | 37.5% | 0.0289 | 284.9° | 16.8 |
| 800 | `#282833` | 28.2% | 0.0201 | 285.0° | 9.3 |
| 850 | `#25252d` | 26.8% | 0.0148 | 285.4° | 1.4 |
| 900 | `#121217` | 18.5% | 0.0101 | 285.4° | 8.3 |

**Yes, it is well built.** Hue holds at 285 to 286 degrees across all ten steps,
which makes it a deliberate single-hue cool grey rather than a set of greys picked
one at a time. Chroma rises to a peak at 500 and falls away at both ends, which is
the correct shape: a neutral ramp should carry most of its colour in the midtones
and almost none at the extremes. This is a large part of why their application
reads as calm, and it is worth keeping.

Two flaws the conversion exposes that hex was hiding:

- **800 and 850 are 1.4% apart in lightness**, which is to say they are the same
  colour. 850 (`#25252d`) is the one the app renders for body text; 800 is declared
  and never used. When the ramp is rebuilt for the real brand, these collapse into
  one step.
- **The accent hover drifts hue.** 500 sits at 287.5°, the hover at 289.7°, the
  darker variant at 281.5°. Those were picked by eye. In oklch a hover state
  becomes a stated lightness step rather than a guess, which is one fewer decision
  at rebrand time.

**One thing this does not do.** Writing these values in oklch does not make them
look better than the hex did, and it would be wrong to expect it to. What makes a
palette look good is even perceptual steps, constant hue and a sensible chroma
curve, and this ramp already has all three. oklch is the notation that makes those
properties visible and adjustable. It also allows colours outside sRGB, which on a
P3 display can be more saturated than hex can express, but that is a deliberate
choice to make at brand time and none of these values take it.

### The font

**Inter, licensed under the SIL Open Font License 1.1.** Free for commercial use
in a production web application, webfont embedding permitted, no fee, no licence
to buy. They self-host it and declare weights 100 to 900, of which only 400 and
500 are ever loaded.

**No substitution was needed and none was made.** Klaroly uses Inter, self-hosted
as a variable font. Do not hotlink it from a third party and do not load nine
weights when two are used.

### Things that could not be measured, or were inferred

1. **Their real dark-mode toggle was never found.** There is no control in the UI
   for this account, no theme value in local storage, and the CSS contains no
   `prefers-color-scheme` rule, so the OS preference does nothing. The dark theme
   was captured by adding the `.dark` class their stylesheet is written against,
   locally in the browser, which was then removed. Everything dark in this
   document is therefore measured from their own CSS, but it was never seen in the
   state their users see it in.
2. **Light-mode placeholder colour is inferred.** `.form-input` does not set one.
   Their search input uses `#6c6c84` and their auth inputs fall through to the
   browser default `#9ca3af`. `--color-text-placeholder` is set to `#8a8aa3`,
   which is the ramp value nearest both.
3. **The input error state was never triggered**, because nothing was submitted.
   The error treatment here combines their `.form-error` text colour with their
   focus-ring mechanics, which is consistent but is a reconstruction.
4. **They have no card component**, so the card anatomy here is assembled from
   their radio card and their section band rather than measured from a real card.
5. **The toggle off-state colour was never seen.** Every toggle in the account was
   on. `border-strong` is a reasoned choice, not an observation.
6. **Loading and skeleton states were never encountered.**
7. **No screenshots were captured at source.** Their content security policy
   blocks any external script, so no page-to-image library could run, and browser
   screenshots cannot be written to disk from this environment. The images in
   `style-guide-screens/` are therefore rendered from `kitchen-sink.html`, which
   is to say they show what this guide specifies rather than what their app looks
   like. The measured values in the tables above are the record of the source.
   The folder also carries the states that a static shot normally loses:
   `light-table-hover`, `light-form-hover` and `light-form-focus`, plus the four
   `phone-*` captures at 390px.

### Changes made after the first review

The first pass reproduced their system faithfully. Reviewing it on screen turned
up a set of places where faithful was not the same as right. Each of these is a
deliberate departure from source, and each was checked with a measurement rather
than an opinion.

| Change | From (theirs) | To (Klaroly) | Why |
| --- | --- | --- | --- |
| Muted text | `#6c6c89`, 5.1:1 | `#55556e`, **7.2:1** | Secondary copy read as tentative. It should be quieter than a heading, not faint. |
| Subtle text | `#a9a9bc`, **2.3:1** | `#6c6c89`, **5.1:1** | Their value fails WCAG AA for body text outright. Captions and timestamps are exactly what an older user struggles with. |
| Success pill text | `#1e874c`, 4.3:1 | `#004f23`, **9.2:1** | Washed out against its own fill. Taken down a second step later, with the other three, because a pill is read at 13px. |
| Warning pill text | `#eb3a00`, **3.7:1** | `#9e0000`, **7.8:1** | Also failed AA. |
| Pill type | 14 / 24 / 400 | 13 / 24 / 500 | One step down, medium weight to hold at that size. |
| Field drop shadow | `0 1px 1px` under the inset ring | removed | Fussy at this density, invisible on a phone, and it complicated the focus treatment. |
| Field border | 16% ink | **24% ink**, 1.7:1 on white | Theirs is faint on a poor screen. See the note below on how far this still is from WCAG 1.4.11. |
| Field focus | 2px ring floating outside the control | the field's own border becomes 2px accent | Asked for directly, and it is better: nothing moves, and the edge that changes is the edge you were already looking at. |
| Secondary button | `#f7f7f8` | tried `#d1d1db`, **reverted to `#f7f7f8`** | Darker read as a second primary. A cancel should not compete with the action beside it. The label is 14.2:1 regardless. |
| Row hover | `#f7f7f8` fill, patched with two offset pseudo-elements | the existing 1px divider transitions to accent | Their fill is a 1.07:1 change and effectively invisible, and it stops at the last cell. Recolouring the divider spans the row for free and adds no second line. |
| Checkbox and radio rows | the 20px box is the only target | the whole row, 44px minimum, label at medium weight turning accent on hover | A 20px box is smaller than a thumb. A fill was tried first and dropped: too much furniture for a hover, and it fought the fewer-boxes rule. |
| Filled button hover | lighter, white label at 4.6:1 | **darker**, white label at 7.6:1 | The hover has to keep a white label legible, so the fill moves away from white. |
| Danger button fill | `#f53d6b`, white label at **3.6:1** | `#d50b3e`, 5.3:1 | Failed AA on the one button where an unreadable label is worst. |
| Checkbox tick | rotated pseudo-element, off centre | centred background image | The rotation was pushing the tick about 2px high. |
| Toggle hit area | 30 × 18 | 48 × 44, visual 48 × 28 | A pseudo-element takes the 28px track up to the 44px minimum without changing anything visible. |
| Control text | 14 / 24 / 400, same as its label | **15 / 24 / 500** | A value you typed should out-weigh the label describing it. Placeholders stay at 400. |
| Control height | 40px | **48px on every screen** | 40px is the desktop-admin default and reads like one. 48px is calmer, clears the 44px tap minimum without a breakpoint rule, and costs 56px across a twelve-control form. |
| Label to control | 12px | 8px | Binds the label to its control so the 24px field gap reads as the real separator. |
| Auth column | 560px, 496px fields | 464px, 400px fields | 496px is too wide for a two-field form. |
| Empty state title | 20px | 24px | It is the only thing on the screen; it can carry the page. |
| Hover fill | `surface-sunken`, `#f7f7f8` | new `surface-hover` token, `#e8e8ed` | A hover you cannot see is not a hover. Sunken stays for wells and active states. |

**One place this system still does not pass, and you should know about it.** WCAG
1.4.11 asks for 3:1 between a control's boundary and the surface behind it. The
field border is 1.7:1 at 24% ink, and reaching 3:1 needs roughly 45% ink, which is
a visibly grey box around every input and a different-looking product. Almost every
modern SaaS interface, Lemon Squeezy included, fails this the same way. It is
survivable because the field also has a background, a label and a placeholder, but
it is a known gap rather than an oversight, and it is the first thing to revisit if
Klaroly ever needs a formal accessibility statement.

Contrast figures are WCAG 2.1 ratios computed from the rendered colours, not
estimated. Every text token in both themes now passes AA at body size: in light,
body 15.2:1, muted 7.2:1, subtle 5.1:1; in dark, 7.5:1 and 5.1:1 against the base.
The one value left below 5:1 is the danger pill at 4.8:1, which passes AA and was
left alone because the red was specifically approved.

**Also removed:** the lever switcher and all of its code. See the levers section
for what remains.

### Things in their system that are bad ideas and were not copied

1. **There is no visible keyboard focus on buttons or menu items.** Every control
   carries `outline: 2px solid transparent; outline-offset: 2px`, which removes
   the browser default, and only inputs get a ring put back. Buttons, menu items,
   checkboxes and radios have nothing. This is a real accessibility failure and it
   is the single worst thing in their system.
2. **Their dark mode is unfinished.** Measured on the dark pass: 41 elements still
   render `#121217` text and 49 render `#6c6c89`, both light-mode values, on a
   dark ground. 44 borders are still `#e8e8ed`. The sidebar navigation is close to
   unreadable in dark, and the active nav item keeps a near-white pill. Treat
   their dark mode as a source of architecture, not as a finished reference.
3. **Four naming systems for the same colours.** `wedges-*` (current),
   `light-*`/`dark-*` (a lightness-indexed duplicate), `wtf-*` (an older brand
   palette) and stock Tailwind leftovers. `#7047eb` is reachable as
   `wedges-purple`, `wedges-purple-500` and `wtf-majorelle`. `#f7f7f8` is
   `wedges-gray-50`, `light-97`, `light-95` and `grey-50`.
4. **`rounded-full` is `100%`**, which makes an ellipse of any non-square box.
5. **The unchecked checkbox sits at `opacity: .4`**, which is too faint to read as
   an affordance.
6. **Their dark button hover makes the button fainter.** Resting is
   `rgb(255 255 255 / .10)` and hover is `rgb(255 255 255 / .05)`. The hover state
   is less visible than the resting state.
7. **No semantic landmarks.** No `h1`, no `main`, no `nav` on any screen visited.
   Auth forms are placeholder-only with no labels at all.
8. **Arbitrary Tailwind values in production**, including `tracking-[-0.01em]` on
   every page title, `py-[2px]` on every badge and `max-w-[420px]` on settings
   helper text.
9. **A typo shipped to production.** The OAuth buttons on the sign in screen carry
   `boreder-light-91`, so the intended `#e8e8ed` border never applies and the
   button falls back to stock Tailwind's `#e5e7eb`. It is a fair illustration of
   what happens without the lock-down block at the end of this document.
10. **Table row hover is faked with two offset pseudo-elements**, one translated
    `-16px` and one `+16px`, to extend the highlight into the gutters with rounded
    ends. It looks good and it is a lot of machinery for a hover state.

### The three places their desktop system fights a phone-first app

1. **Tap targets.** The toggle is 30 × 18 and the checkbox is 16 × 16. Both fail
   the 44px minimum badly, and the toggle is a primary control in a settings
   screen. **Fixed:** the toggle is 48 × 28 and the checkbox is 20px, both with
   a 44px hit area, and `--tap-target-min` is a token so it can be checked
   against.
2. **Vertical rhythm.** 40px page gutters, 40px section gaps and 24px field gaps
   are comfortable on a 1440px monitor and produce a great deal of scrolling at
   390px. A twelve-field booking form loses roughly a screen and a half to gaps
   alone. **Fixed:** four spacing tokens compress below 640px, named individually
   so it is clear which ones matter, and control heights and type sizes
   deliberately do not move.
3. **The content column is effectively unbounded.** Theirs caps at 1600px, which
   no normal monitor reaches, so in practice it fills whatever is left after a
   264px sidebar. Fine for a dense admin table, poor for reading. **Fixed:**
   `--container-content` is 1096px, which is what their column measures at 1440px,
   so long-form content and settings rows do not stretch on a wide screen.

A fourth, worth noting but not fixed: their primary data view is a table, and a
table is the wrong primary view on a phone. The list row component above is
Klaroly's default, with the table as the wide-screen variant of the same data.
That is a layout decision rather than a token one, so it belongs in the screen
designs rather than here.

---

## For CLAUDE.md

Paste the block below into `CLAUDE.md`. Every future prompt inherits it.

```markdown
## Style guide

Read `docs/style-guide.md` before creating or changing any component. Every
colour, size, space, radius, border, shadow and duration comes from the semantic
tokens in `src/assets/app.css`. Never hardcode a value and never use a Tailwind
arbitrary value. If something you need has no token, stop and ask rather than
inventing one; if a token is added, it is recorded in the style guide in the same
change. Every component works in both light and dark, at 390px first, with a
visible focus ring and a minimum 44px tap target. Prefer a hairline divider and
whitespace to a card. The style guide is the source of truth and it outranks
anything a prompt says in passing.
```
