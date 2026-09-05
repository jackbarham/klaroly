<template>
  <form
    class="space-y-12"
    novalidate
    @submit.prevent
  >
    <FormSection
      title="The edge of a control"
      description="A control's edge is an inset shadow rather than a border, so focus and error recolour the edge it already has and the box never moves by a pixel. Focus cannot be drawn standing still: click into the middle field to see it, and notice the edge changes the moment you click rather than when the pointer leaves."
    >
      <div class="grid gap-6 sm:grid-cols-3">
        <FormField
          v-slot="field"
          label="At rest"
        >
          <TextInput
            v-bind="field"
            v-model="demo.edgeResting"
          />
        </FormField>

        <FormField
          v-slot="field"
          label="Focused: click in here"
        >
          <TextInput
            v-bind="field"
            v-model="demo.edgeFocused"
          />
        </FormField>

        <FormField
          v-slot="field"
          label="Invalid"
          error="It stays red while the cursor is in it."
        >
          <TextInput
            v-bind="field"
            v-model="demo.edgeInvalid"
          />
        </FormField>
      </div>
    </FormSection>

    <FormSection
      title="TextInput"
      description="A single line of text. Every control below takes its id, its description and its invalid flag from FormField, so a field is one line of markup."
    >
      <FormField
        v-slot="field"
        label="Client name"
      >
        <TextInput
          v-bind="field"
          v-model="demo.name"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Client name"
        hint="As it should appear on the invoice."
      >
        <TextInput
          v-bind="field"
          v-model="demo.nameWithHint"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Client name"
        hint="As it should appear on the invoice."
        error="Enter the client's name."
      >
        <TextInput
          v-bind="field"
          v-model="demo.nameWithError"
        />
      </FormField>

      <!--
        The live check: a tick or a cross drawn inside the control, and the
        same thing in words in a live region that only a screen reader hears.
        The pair is passed together, because a mark with no message is a shape
        nobody can hear.
      -->
      <FormField
        v-slot="field"
        label="Username"
        hint="This is the address clients see."
        status-message="elliemarsh is available."
      >
        <TextInput
          v-bind="field"
          v-model="demo.username"
          status="valid"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Username"
        hint="This is the address clients see."
        status-message="ellie is already taken."
      >
        <TextInput
          v-bind="field"
          v-model="demo.takenUsername"
          status="invalid"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Email address"
        hint="Changing this needs the settings API, which does not exist yet."
      >
        <TextInput
          v-bind="field"
          v-model="demo.email"
          type="email"
          disabled
        />
      </FormField>
    </FormSection>

    <FormSection title="TextArea">
      <FormField
        v-slot="field"
        label="Notes"
      >
        <TextArea
          v-bind="field"
          v-model="demo.notes"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Notes"
        hint="Only you can see these."
      >
        <TextArea
          v-bind="field"
          v-model="demo.notesWithHint"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Notes"
        hint="Only you can see these."
        error="Notes cannot be longer than 500 characters."
      >
        <TextArea
          v-bind="field"
          v-model="demo.notesWithError"
        />
      </FormField>
    </FormSection>

    <FormSection title="SelectInput">
      <FormField
        v-slot="field"
        label="Service"
      >
        <SelectInput
          v-bind="field"
          v-model="demo.service"
          :options="serviceOptions"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Service"
        hint="The rate card decides what this costs."
      >
        <SelectInput
          v-bind="field"
          v-model="demo.serviceWithHint"
          :options="serviceOptions"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Service"
        hint="The rate card decides what this costs."
        error="Choose a service."
      >
        <SelectInput
          v-bind="field"
          v-model="demo.serviceWithError"
          :options="serviceOptions"
        />
      </FormField>
    </FormSection>

    <FormSection
      title="CheckboxInput"
      description="Two shapes, side by side, and never both at once: a box that carries its own label, and a box inside a field that is named by the field's label. Passing both, or neither, throws while the screen is being written."
    >
      <div class="grid gap-6 sm:grid-cols-2">
        <div class="space-y-2">
          <p class="text-xs font-medium text-text-muted">
            On its own, carrying its own label
          </p>
          <CheckboxInput
            id="kitchen-sink-standalone-checkbox"
            v-model="demo.confirmed"
            label="Send the client a confirmation email"
          />
        </div>

        <div class="space-y-2">
          <p class="text-xs font-medium text-text-muted">
            Inside a FormField, named by the field alone
          </p>
          <FormField
            v-slot="field"
            label="Send the client a confirmation email"
          >
            <CheckboxInput
              v-bind="field"
              v-model="demo.confirmedWithHint"
            />
          </FormField>
        </div>
      </div>

      <FormField
        v-slot="field"
        label="Send the client a confirmation email"
        hint="The confirmation goes out as soon as the booking is saved."
        error="Confirm how the client should be told."
      >
        <CheckboxInput
          v-bind="field"
          v-model="demo.confirmedWithError"
        />
      </FormField>
    </FormSection>

    <FormSection title="RadioGroup">
      <FormField
        v-slot="field"
        label="Who is paying"
      >
        <RadioGroup
          v-bind="field"
          v-model="demo.payer"
          :options="payerOptions"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Who is paying"
        hint="Everyone in the party can pay separately if you prefer."
      >
        <RadioGroup
          v-bind="field"
          v-model="demo.payerWithHint"
          :options="payerOptions"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Who is paying"
        hint="Everyone in the party can pay separately if you prefer."
        error="Choose who is paying."
      >
        <RadioGroup
          v-bind="field"
          v-model="demo.payerWithError"
          :options="payerOptions"
        />
      </FormField>
    </FormSection>

    <FormSection title="ToggleSwitch">
      <FormField
        v-slot="field"
        label="Send reminders"
      >
        <ToggleSwitch
          v-bind="field"
          v-model="demo.reminders"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Send reminders"
        hint="A reminder goes out the week before the booking."
      >
        <ToggleSwitch
          v-bind="field"
          v-model="demo.remindersWithHint"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Send reminders"
        hint="A reminder goes out the week before the booking."
        error="Turn reminders on to choose when they are sent."
      >
        <ToggleSwitch
          v-bind="field"
          v-model="demo.remindersWithError"
        />
      </FormField>
    </FormSection>

    <FormSection title="DateInput">
      <FormField
        v-slot="field"
        label="Booking date"
      >
        <DateInput
          v-bind="field"
          v-model="demo.date"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Booking date"
        hint="The date of the event itself, not the trial."
      >
        <DateInput
          v-bind="field"
          v-model="demo.dateWithHint"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Booking date"
        hint="The date of the event itself, not the trial."
        error="Choose a date in the future."
      >
        <DateInput
          v-bind="field"
          v-model="demo.dateWithError"
        />
      </FormField>
    </FormSection>

    <FormSection title="MoneyInput">
      <FormField
        v-slot="field"
        label="Deposit"
      >
        <MoneyInput
          v-bind="field"
          v-model="demo.deposit"
          currency="£"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Deposit"
        hint="Twenty five per cent of the total, unless you say otherwise."
      >
        <MoneyInput
          v-bind="field"
          v-model="demo.depositWithHint"
          currency="£"
        />
      </FormField>

      <FormField
        v-slot="field"
        label="Deposit"
        hint="Twenty five per cent of the total, unless you say otherwise."
        error="Enter an amount."
      >
        <MoneyInput
          v-bind="field"
          v-model="demo.depositWithError"
          currency="£"
        />
      </FormField>
    </FormSection>

    <FormSection
      title="FormError"
      description="A failure that belongs to no single field, such as a rate limit or a lost connection. It borrows the invalid control's own treatment, so a form has one way of saying something is wrong."
    >
      <FormError message="Too many attempts. Try again in a few minutes." />
    </FormSection>

    <FormSection
      title="FormActions"
      description="Cancel, then save. On a phone the row sticks to the bottom of the screen above the tab bar, which is why it is against the bottom edge here as well. Nothing is saved: this page has no request behind it."
    >
      <FormActions />
    </FormSection>
  </form>
</template>

<script setup lang="ts">
// The form kit as a working form: every control, each with a label, with a
// hint and with an error, so that FormField's wiring is visible rather than
// described. Nothing here validates anything and nothing is submitted.
import { reactive } from 'vue'

// Every control needs somewhere to put what is typed. One object rather than
// thirty separate refs, because none of it means anything: it is demo
// content, and the page is deleted before launch.
const demo = reactive({
  edgeResting: '',
  edgeFocused: '',
  edgeInvalid: '',
  name: 'Ellie Marsh',
  nameWithHint: '',
  nameWithError: '',
  username: 'elliemarsh',
  takenUsername: 'ellie',
  email: 'ellie@example.com',
  notes: '',
  notesWithHint: '',
  notesWithError: '',
  service: 'bridal',
  serviceWithHint: 'bridal',
  serviceWithError: 'bridal',
  confirmed: true,
  confirmedWithHint: false,
  confirmedWithError: false,
  payer: 'client',
  payerWithHint: 'client',
  payerWithError: 'client',
  reminders: true,
  remindersWithHint: false,
  remindersWithError: false,
  date: '2026-06-13',
  dateWithHint: '',
  dateWithError: '',
  deposit: '75.00',
  depositWithHint: '',
  depositWithError: '',
})

const serviceOptions = [
  { value: 'bridal', label: 'Bridal makeup' },
  { value: 'party', label: 'Bridal party makeup' },
  { value: 'occasion', label: 'Occasion makeup' },
]

const payerOptions = [
  { value: 'client', label: 'The bride pays for everyone' },
  { value: 'party', label: 'Everyone pays for themselves' },
]
</script>
