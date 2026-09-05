<template>
  <!--
    The row is the control. A 20px box is smaller than a thumb, so when the
    box carries its own words the whole row is the hit area, at least 44px
    tall, and the row is what a click lands on. Inside a FormField there are
    no words here to click, so the row is a plain wrapper and the field's
    label does that job.
  -->
  <component
    :is="label ? 'label' : 'div'"
    :class="label ? rowClasses : 'flex items-start gap-2'"
    :for="label ? id : undefined"
  >
    <input
      :id="id"
      v-model="model"
      class="h-5 w-5 shrink-0 accent-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus"
      :class="label ? 'mt-1' : ''"
      type="checkbox"
      :disabled="disabled"
      :aria-labelledby="labelledBy"
      :aria-describedby="describedBy"
      :aria-invalid="invalid ? 'true' : undefined"
    >
    <!--
      The box carries its own label only when it is standing on its own.
      Inside a FormField the field's label names it, and a second label
      pointing at the same control is the same words said twice.

      The words carry no colour of their own, so they inherit the row's, which
      is what lets the row turn them accent on hover.
    -->
    <span v-if="label">{{ label }}</span>
  </component>
</template>

<script setup lang="ts">
// A single checkbox, in one of two shapes, and never in both.
//
// On its own it carries its own label, because a tick box reads as the
// sentence beside it rather than as a field with a heading, and that is how
// the sign-in and register screens use it. Inside a FormField it carries no
// label of its own and is named by the field's, which is what labelledBy is.
// A control with two labels pointing at it is announced twice.
//
// Exactly one of label and labelledBy is required. That would be said best as
// a union of two prop types, but it cannot be: defineProps flattens a union
// into optional properties and the exclusivity is lost, and a generic type
// parameter, which does hold it, cannot be turned into runtime props at all.
// So the rule is checked below instead, where it fails at once and in the
// open rather than sitting in a comment nobody reads.
const props = withDefaults(defineProps<{
  id: string
  label?: string
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}>(), {
  label: undefined,
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
})

// Both, or neither, is a mistake in the markup rather than something a person
// can cause, so it stops the screen while it is being written. The branch is
// compiled out of a build, so it costs nothing in the app anyone uses.
if (import.meta.env.DEV && Boolean(props.label) === Boolean(props.labelledBy)) {
  throw new Error('CheckboxInput takes either a label of its own or the labelledBy of the field around it, and never both')
}

const model = defineModel<boolean>({ required: true })

// The option row from the style guide. The negative margin pulls the row's
// padding back out to the edge of the form, so the row is wider than the text
// without the text moving. Hover is the words turning accent rather than a
// grey box: colour is set here and inherited, so anything that has claimed a
// colour of its own, such as a description, keeps it.
const rowClasses = '-mx-3 flex min-h-11 cursor-pointer items-start gap-3 rounded-control px-3 py-2.5 text-text-strong transition-colors hover:text-accent-text'
</script>
