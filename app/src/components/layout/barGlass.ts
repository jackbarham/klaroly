// The material both of the phone's floating bars are made of.

// Two bars with different glass would read as two systems rather than as one
// piece of chrome, so it is one string. It lives beside them rather than
// inside either, because neither owns it: the tab bar wears it always and the
// top bar takes it once the page has moved under it. It is the same idea as
// Sheet.vue exporting sheetRowClasses, moved out one level because there are
// two callers of equal standing rather than a parent and its rows.
//
// The values are Tailwind's own and not tokens. --blur-xl is 24px in
// Tailwind's theme, backdrop-saturate-180 is a bare value rather than an
// arbitrary one, and the alpha modifier reads --surface-raised, so the glass
// is white in light and the raised ink in dark with nothing to keep in step.
// A token would be three edits to describe a string that two files share.
//
// src/lib/styleRules.test.ts reads this file: its glob was widened to the .ts
// files under components in the same change, because a class string in a
// module would otherwise escape the arbitrary-value and hex checks entirely.
//
// Only the material is here. The tab bar's border and shadow and the top
// bar's hairline stay in their own files, because those differ on purpose.
export const barGlassClasses = 'bg-surface-raised/60 backdrop-blur-xl backdrop-saturate-180'
