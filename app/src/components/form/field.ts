// What every form control shares, in one place, so that a text input, a
// select and a money input cannot drift apart.

// The four things FormField hands to whatever sits in its slot. A control
// takes them and does no wiring of its own: the field owns the label, the
// hint, the error and the ids that tie them together.
export interface ControlProps {
  id: string
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}

// One choice in a select or a radio group.
export interface ChoiceOption {
  value: string
  label: string
}

// A control's edge is an inset shadow, not a border. That is what lets focus
// and error recolour and thicken the edge the control already has, rather than
// drawing a ring outside it, and it means none of those states moves the box
// or the text inside it by a pixel. The transparent border is what keeps the
// content box where it was.
export const controlClasses = 'w-full rounded-control border border-transparent bg-surface px-4 text-text-strong placeholder:text-text-placeholder focus:outline-hidden disabled:cursor-not-allowed disabled:bg-surface-disabled disabled:text-text-muted'

// Every state of the edge lives in one function on purpose. If the resting
// string carried the focus edge and the invalid string carried its own, two
// utilities would be setting the same property under the same variant and
// which one won would depend on the order Tailwind happens to emit them in.
// An invalid control simply has no focus utility on it, so an error stays an
// error while the cursor is in the field.
//
// The browser draws a focus ring of its own, and on these controls that would
// sit outside the edge we have just recoloured. outline-hidden rather than
// outline-none: it leaves a transparent outline behind, which a forced-colours
// mode turns back into a visible ring, and an inset shadow is not drawn there
// at all.
//
// not-focus is not decoration. A hover selector carries more specificity than
// a focus selector, so without excluding focus a field you have just clicked
// keeps its hover edge until the pointer leaves it.
export function edgeClasses(invalid: boolean): string {
  if (invalid) {
    return 'shadow-input-invalid'
  }

  return 'shadow-input enabled:hover:not-focus:shadow-input-hover focus:shadow-input-focus'
}

// The option row the checkbox and the radio group share. A 20px box is
// smaller than a thumb, so the whole row is the hit area, at least 44px tall.
// The negative margin pulls the row's padding back out to the edge of the
// form, so the row is wider than the text without the text moving. Hover is
// the words turning accent rather than a grey box: the colour is set here and
// inherited, so anything that has claimed a colour of its own keeps it.
//
// The words are the same size and weight as a FormField's label, because a
// checkbox carrying its own label and a checkbox named by a field's label sit
// beside each other on a form and must not read as two different things. The
// box is centred against them rather than pinned to the first line, which is
// what a one-line option, the only kind either control is written with, wants.
export const optionRowClasses = '-mx-3 flex min-h-11 cursor-pointer items-center gap-2 rounded-control px-3 py-2.5 text-sm font-medium text-text-strong transition-colors hover:text-accent-text'
