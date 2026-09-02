// After a failed submit the first field carrying an error receives focus, so
// a keyboard or screen reader user lands on what needs fixing. Every field
// component sets aria-invalid when it has an error, so that attribute is the
// thing to look for.
export function focusFirstInvalid(form: HTMLFormElement | null | undefined): void {
  if (!form) {
    return
  }

  const field = form.querySelector<HTMLElement>('[aria-invalid="true"]')

  field?.focus()
}
