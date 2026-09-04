import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import FormField from '@/components/form/FormField.vue'
import TextInput from '@/components/form/TextInput.vue'
import { mount, unmount, type Mounted } from '@/lib/testMount'

interface SlotProps {
  id: string
  labelledBy: string
  describedBy?: string
  invalid: boolean
}

// A real field: FormField around the control it is meant to wire up.
const Host = defineComponent({
  props: {
    label: { type: String, required: true },
    hint: { type: String, default: undefined },
    error: { type: String, default: undefined },
  },
  setup(props) {
    const value = ref('')

    return () => h(FormField, { label: props.label, hint: props.hint, error: props.error }, {
      default: (slotProps: SlotProps) => h(TextInput, {
        'id': slotProps.id,
        'labelledBy': slotProps.labelledBy,
        'describedBy': slotProps.describedBy,
        'invalid': slotProps.invalid,
        'modelValue': value.value,
        'onUpdate:modelValue': (next: string) => {
          value.value = next
        },
      }),
    })
  },
})

let mounted: Mounted | null = null

function element(host: HTMLElement, selector: string): HTMLElement {
  const found = host.querySelector<HTMLElement>(selector)

  if (found === null) {
    throw new Error(`The test expected to find ${selector}`)
  }

  return found
}

afterEach(() => {
  if (mounted) {
    unmount(mounted)
    mounted = null
  }
})

describe('a form field', () => {
  it('ties its label to the control by id', async () => {
    mounted = await mount(Host, '/', { label: 'Base postcode' })

    const label = element(mounted.host, 'label')
    const input = element(mounted.host, 'input')

    expect(input.id).not.toBe('')
    expect(label.getAttribute('for')).toBe(input.id)
    expect(label.textContent).toBe('Base postcode')
  })

  it('announces the hint with the control', async () => {
    mounted = await mount(Host, '/', { label: 'Base postcode', hint: 'The postcode you set off from.' })

    const input = element(mounted.host, 'input')
    const describedBy = input.getAttribute('aria-describedby') ?? ''

    expect(describedBy).not.toBe('')

    const hint = element(mounted.host, `#${describedBy}`)

    expect(hint.textContent?.trim()).toBe('The postcode you set off from.')
    expect(input.getAttribute('aria-invalid')).toBeNull()
  })

  it('marks the control invalid and announces the error as well as the hint', async () => {
    mounted = await mount(Host, '/', {
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
