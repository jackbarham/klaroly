// After a failed submit the first field carrying an error receives focus, so
// a keyboard or screen reader user lands on what needs fixing.
//
// FormField works out that a field is invalid from its error and hands that
// down to the control, which puts aria-invalid on the element someone types
// into, so that attribute is the thing to look for and the element carrying
// it is one that can take focus. The two controls that are not a single
// element, the radio group and the toggle switch, put it on a wrapper
// instead; when the first form to use one of those in anger comes along,
// this is where that has to be dealt with.
export function focusFirstInvalid(form: HTMLFormElement | null | undefined): void {
  if (!form) {
    return
  }

  const field = form.querySelector<HTMLElement>('[aria-invalid="true"]')

  field?.focus()
}
