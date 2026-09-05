import { nextTick, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/lib/api'

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

// What a screen does with a rejected submit is the same on every one: a
// 422's field messages go into FormField's error, anything else goes into
// FormError, and focus moves to the first invalid control. This is that rule
// written once. A screen calls submit() with the request it wants made and
// binds pending, errors and formError to its template, which must carry
// ref="form" on the form element.
//
// A screen with something of its own to do with a failure passes handle. It
// runs first, sees the ApiError, and returns true when it has dealt with it
// so the rule below is skipped, or false to let the rule run afterwards.
export function useSubmit() {
  const { t } = useI18n()
  const form = useTemplateRef<HTMLFormElement>('form')
  const pending = ref(false)
  const errors = ref<Record<string, string>>({})
  const formError = ref<string | null>(null)

  // A rejection the screen worked out for itself, before any request.
  async function reject(fieldErrors: Record<string, string>): Promise<void> {
    errors.value = fieldErrors
    await nextTick()
    focusFirstInvalid(form.value)
  }

  async function submit(
    action: () => Promise<void>,
    handle?: (error: ApiError) => boolean | Promise<boolean>,
  ): Promise<void> {
    // A second submit while the first is in flight sends nothing.
    if (pending.value) {
      return
    }

    pending.value = true
    errors.value = {}
    formError.value = null

    try {
      await action()
    } catch (error) {
      const handled = error instanceof ApiError && handle !== undefined && await handle(error)

      if (handled) {
        // The screen has said what it wanted to.
      } else if (error instanceof ApiError && error.status === 422) {
        errors.value = error.validationErrors()
      } else if (error instanceof ApiError && error.status === 429) {
        formError.value = t('common.too_many_attempts')
      } else {
        formError.value = t('common.request_failed')
      }

      await nextTick()
      focusFirstInvalid(form.value)
    } finally {
      pending.value = false
    }
  }

  return { form, pending, errors, formError, submit, reject }
}
