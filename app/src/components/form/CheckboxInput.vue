<template>
  <input
    :id="id"
    v-model="model"
    class="check focus-visible:focus-ring"
    type="checkbox"
    :disabled="disabled"
    :aria-labelledby="labelledBy"
    :aria-describedby="describedBy"
    :aria-invalid="invalid ? 'true' : undefined"
  >
</template>

<script setup lang="ts">
// A single tick box, and nothing else. The words beside it belong to the
// field: FormField's inline shape makes its own label the row that wraps
// this box, which is what gives the words their 44px hit area and their
// hover, and what names the box through that label's "for".
//
// It used to carry a label of its own as well, for the times it stood
// outside a field, and a box that is written two ways is a box that lines up
// two ways. There is one shape now and every checkbox in the app is in a
// field, so the label lives in one place.
import type { ControlProps } from '@/components/form/field'

defineProps<ControlProps>()

const model = defineModel<boolean>({ required: true })
</script>
