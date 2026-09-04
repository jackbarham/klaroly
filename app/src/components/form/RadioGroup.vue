<template>
  <div
    :id="id"
    class="space-y-2"
    role="radiogroup"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
    <label
      v-for="option in options"
      :key="option.value"
      class="flex items-start gap-2 text-neutral-900"
    >
      <input
        v-model="model"
        class="mt-1 h-5 w-5 shrink-0 accent-neutral-900"
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
</script>
