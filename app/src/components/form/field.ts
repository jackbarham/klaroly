// One appearance for every form control, in one place, so that a text input,
// a select and a money input cannot drift apart.
//
// A control's edge is an inset shadow, not a border. That is what lets focus
// and error recolour and thicken the edge the control already has, rather than
// drawing a ring outside it, and it means none of those states moves the box
// or the text inside it by a pixel. The transparent border is what keeps the
// content box where it was.
//
// Every state of the edge lives in one function on purpose. If the resting
// string carried the focus edge and the invalid string carried its own, two
// utilities would be setting the same property under the same variant and
// which one won would depend on the order Tailwind happens to emit them in.
// An invalid control simply has no focus utility on it, so an error stays an
// error while the cursor is in the field.

export const controlClasses = 'w-full rounded-control border border-transparent bg-surface px-4 text-text-strong placeholder:text-text-placeholder focus:outline-hidden disabled:cursor-not-allowed disabled:bg-surface-disabled disabled:text-text-muted'

// The browser draws a focus ring of its own, and on these controls that would
// sit outside the edge we have just recoloured, which is the one thing this
// treatment exists to avoid. outline-hidden rather than outline-none: it
// leaves a transparent outline behind, which is what a forced-colours mode
// turns back into a visible ring, and an inset shadow is not drawn there at
// all. So the ring goes where the edge cannot be seen, and nowhere else.
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
