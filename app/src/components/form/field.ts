// One appearance for every form control, in one place, so that a text input,
// a select and a money input cannot drift apart. The invalid state is a
// darker, heavier border rather than a colour, because the app is greyscale
// and because a border colour on its own tells a screen reader nothing: every
// control also sets aria-invalid.

export const controlClasses = 'w-full rounded-control border bg-neutral-0 px-4 text-neutral-900 placeholder:text-neutral-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900 disabled:cursor-not-allowed disabled:bg-neutral-50 disabled:text-neutral-400'

export function borderClasses(invalid: boolean): string {
  return invalid ? 'border-2 border-neutral-900' : 'border-neutral-300'
}
