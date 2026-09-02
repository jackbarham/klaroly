<template>
  <div class="space-y-1">
    <label
      class="block text-sm text-ink-muted"
      :for="id"
    >{{ label }}</label>
    <div class="flex gap-2">
      <input
        :id="id"
        v-model="model"
        class="w-full rounded-control border bg-surface-raised px-3 py-2"
        :class="error ? 'border-danger' : 'border-line'"
        :type="revealed ? 'text' : 'password'"
        :autocomplete="autocomplete"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? `${id}-error` : undefined"
      >
      <button
        class="shrink-0 rounded-control border border-line px-3 text-sm text-ink-muted hover:text-ink"
        type="button"
        :aria-pressed="revealed ? 'true' : 'false'"
        @click="revealed = !revealed"
      >
        {{ revealed ? t('auth.hide_password_action') : t('auth.show_password_action') }}
      </button>
    </div>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="text-sm text-danger"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup lang="ts">
// A password input with a show-password toggle. Klaroly asks for a password
// once, with the toggle in place of a confirmation field (decision 88).
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

withDefaults(defineProps<{
  id: string
  label: string
  autocomplete: 'current-password' | 'new-password'
  error?: string
}>(), {
  error: undefined,
})

const model = defineModel<string>({ required: true })

const { t } = useI18n()

const revealed = ref(false)
</script>
