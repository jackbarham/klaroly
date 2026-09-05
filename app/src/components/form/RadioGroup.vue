<template>
  <div
    :id="id"
    class="space-y-2"
    role="radiogroup"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
    <!--
      Each option is the row, not the dot: a 20px control is smaller than a
      thumb, so the whole row is the hit area and the whole row responds. The
      words carry no colour of their own, so they inherit the row's and turn
      accent with it on hover.
    -->
    <label
      v-for="option in options"
      :key="option.value"
      :class="rowClasses"
    >
      <input
        v-model="model"
        class="mt-1 h-5 w-5 shrink-0 accent-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus"
        type="radio"
        :name="id"
        :value="option.value"
        :disabled="disabled"
      >
      <span>{{ option.label }}</span>
    </label>
  </div>
</template>

<script setup lang="ts">
// One of a short list, all of them visible. A group cannot be labelled by a
// label element's "for", so FormField hands over the id of its label and the
// group points at it with aria-labelledby.
interface RadioOption {
  value: string
  label: string
}

withDefaults(defineProps<{
  id: string
  options: RadioOption[]
  labelledBy?: string
  describedBy?: string
  invalid?: boolean
  disabled?: boolean
}>(), {
  labelledBy: undefined,
  describedBy: undefined,
  invalid: false,
  disabled: false,
})

const model = defineModel<string>({ required: true })

// The same option row the checkbox uses, from the style guide. The negative
// margin pulls the row's padding back out to the edge of the form, so the row
// is wider than the text without the text moving.
const rowClasses = '-mx-3 flex min-h-11 cursor-pointer items-start gap-3 rounded-control px-3 py-2.5 text-text-strong transition-colors hover:text-accent-text'
</script>
