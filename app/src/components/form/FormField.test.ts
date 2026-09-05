import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref, type PropType } from 'vue'
import CheckboxInput from '@/components/form/CheckboxInput.vue'
import FormField from '@/components/form/FormField.vue'
import TextInput from '@/components/form/TextInput.vue'
import { element } from '@/lib/testHelpers'
import { mountWithCleanup } from '@/lib/testMount'

interface SlotProps {
  id: string
  labelledBy?: string
  describedBy?: string
  invalid: boolean
}

// A real field: FormField around the control it is meant to wire up. The
// text input is the default, because most of these tests are about the wiring
// every control shares; the checkbox is the one that takes the inline shape,
// where the label becomes the row and wraps the box.
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

    return () => h(FormField, {
      label: props.label,
      hint: props.hint,
      error: props.error,
      inline: props.control === 'checkbox',
    }, {
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

// The bug this covers: the checkbox used to be written two ways, one
// carrying its own label and one named by a field's, and the two lined up
// differently on the same form. There is one shape now.
describe('a checkbox', () => {
  it('is one row, with the field\'s label wrapping the box', async () => {
    const mounted = await mount(Host, '/', { label: 'Send a confirmation email', control: 'checkbox' })

    const labels = mounted.host.querySelectorAll('label')
    const input = element(mounted.host, 'input')

    expect(labels).toHaveLength(1)
    expect(labels[0].textContent?.trim()).toBe('Send a confirmation email')
    expect(labels[0].getAttribute('for')).toBe(input.id)
    expect(labels[0].contains(input)).toBe(true)
  })

  // A wrapping label already names the box. A second name pointing at that
  // same label is what made the old shape announce itself twice.
  it('is named once, by that label alone', async () => {
    const mounted = await mount(Host, '/', { label: 'Send a confirmation email', control: 'checkbox' })

    expect(element(mounted.host, 'input').getAttribute('aria-labelledby')).toBeNull()
  })

  it('still takes the hint and the error from the field', async () => {
    const mounted = await mount(Host, '/', {
      label: 'Send a confirmation email',
      hint: 'It goes out as soon as the booking is saved.',
      error: 'Confirm how the client should be told.',
      control: 'checkbox',
    })

    const input = element(mounted.host, 'input')

    expect(input.getAttribute('aria-invalid')).toBe('true')
    expect((input.getAttribute('aria-describedby') ?? '').split(' ')).toHaveLength(2)
  })
})
