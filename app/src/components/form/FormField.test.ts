import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref, type PropType } from 'vue'
import CheckboxInput from '@/components/form/CheckboxInput.vue'
import FormField from '@/components/form/FormField.vue'
import TextInput from '@/components/form/TextInput.vue'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'

interface SlotProps {
  id: string
  labelledBy: string
  describedBy?: string
  invalid: boolean
}

// A real field: FormField around the control it is meant to wire up. The
// text input is the default, because most of these tests are about the wiring
// every control shares; the checkbox is the one control that used to bring a
// label of its own into the field.
const Host = defineComponent({
  props: {
    label: { type: String, required: true },
    hint: { type: String, default: undefined },
    error: { type: String, default: undefined },
    control: { type: String as PropType<'text' | 'checkbox'>, default: 'text' },
  },
  setup(props) {
    const text = ref('')
    const ticked = ref(false)

    return () => h(FormField, { label: props.label, hint: props.hint, error: props.error }, {
      default: (slotProps: SlotProps) => {
        if (props.control === 'checkbox') {
          return h(CheckboxInput, {
            'id': slotProps.id,
            'labelledBy': slotProps.labelledBy,
            'describedBy': slotProps.describedBy,
            'invalid': slotProps.invalid,
            'modelValue': ticked.value,
            'onUpdate:modelValue': (next: boolean) => {
              ticked.value = next
            },
          })
        }

        return h(TextInput, {
          'id': slotProps.id,
          'labelledBy': slotProps.labelledBy,
          'describedBy': slotProps.describedBy,
          'invalid': slotProps.invalid,
          'modelValue': text.value,
          'onUpdate:modelValue': (next: string) => {
            text.value = next
          },
        })
      },
    })
  },
})

const mount = mountWithCleanup()

describe('a form field', () => {
  it('ties its label to the control by id', async () => {
    const mounted = await mount(Host, '/', { label: 'Base postcode' })

    const label = element(mounted.host, 'label')
    const input = element(mounted.host, 'input')

    expect(input.id).not.toBe('')
    expect(label.getAttribute('for')).toBe(input.id)
    expect(label.textContent).toBe('Base postcode')
  })

  it('announces the hint with the control', async () => {
    const mounted = await mount(Host, '/', { label: 'Base postcode', hint: 'The postcode you set off from.' })

    const input = element(mounted.host, 'input')
    const describedBy = input.getAttribute('aria-describedby') ?? ''

    expect(describedBy).not.toBe('')

    const hint = element(mounted.host, `#${describedBy}`)

    expect(hint.textContent?.trim()).toBe('The postcode you set off from.')
    expect(input.getAttribute('aria-invalid')).toBeNull()
  })

  it('marks the control invalid and announces the error as well as the hint', async () => {
    const mounted = await mount(Host, '/', {
      label: 'Base postcode',
      hint: 'The postcode you set off from.',
      error: 'Enter a postcode.',
    })

    const input = element(mounted.host, 'input')

    expect(input.getAttribute('aria-invalid')).toBe('true')

    const ids = (input.getAttribute('aria-describedby') ?? '').split(' ')

    expect(ids).toHaveLength(2)
    expect(element(mounted.host, `#${ids[0]}`).textContent?.trim()).toBe('The postcode you set off from.')
    expect(element(mounted.host, `#${ids[1]}`).textContent?.trim()).toBe('Enter a postcode.')
  })
})

// The bug this covers: the checkbox used to bring its own label wherever it
// was put, so inside a field the same control had two labels pointing at it
// and was announced twice.
describe('a checkbox', () => {
  it('carries its own label when it stands on its own', async () => {
    const mounted = await mount(CheckboxInput, '/', {
      id: 'remember',
      label: 'Keep me signed in',
      modelValue: false,
    })

    const labels = mounted.host.querySelectorAll('label')

    expect(labels).toHaveLength(1)
    expect(labels[0].textContent).toBe('Keep me signed in')
    expect(labels[0].getAttribute('for')).toBe('remember')
  })

  it('carries no label of its own when a field names it', async () => {
    const mounted = await mount(CheckboxInput, '/', {
      id: 'remember',
      labelledBy: 'remember-label',
      modelValue: false,
    })

    expect(mounted.host.querySelectorAll('label')).toHaveLength(0)
    expect(element(mounted.host, 'input').getAttribute('aria-labelledby')).toBe('remember-label')
  })

  // The rule the component cannot state in its prop types, so it states it
  // here instead: a box is named once, by one thing or the other.
  it('refuses both a label of its own and a field naming it', async () => {
    await expect(mount(CheckboxInput, '/', {
      id: 'remember',
      label: 'Keep me signed in',
      labelledBy: 'remember-label',
      modelValue: false,
    })).rejects.toThrow(/never both/)
  })

  it('refuses to be given neither', async () => {
    await expect(mount(CheckboxInput, '/', {
      id: 'remember',
      modelValue: false,
    })).rejects.toThrow(/never both/)
  })

  it('is named once, by the field, when it sits inside one', async () => {
    const mounted = await mount(Host, '/', { label: 'Send a confirmation email', control: 'checkbox' })

    const labels = mounted.host.querySelectorAll('label')
    const input = element(mounted.host, 'input')

    expect(labels).toHaveLength(1)
    expect(labels[0].textContent).toBe('Send a confirmation email')
    expect(input.getAttribute('aria-labelledby')).toBe(labels[0].id)
  })
})
