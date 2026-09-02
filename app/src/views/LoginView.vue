<template>
  <main class="flex min-h-screen items-center justify-center p-6">
    <form
      class="w-full max-w-sm space-y-6 rounded-card border border-line bg-surface-raised p-8"
      @submit.prevent
    >
      <h1 class="text-2xl font-semibold">
        {{ t('auth.sign_in_title') }}
      </h1>

      <div class="space-y-2">
        <label
          class="block text-sm text-ink-muted"
          for="email"
        >{{ t('auth.email_label') }}</label>
        <input
          id="email"
          v-model="email"
          class="w-full rounded-control border border-line px-3 py-2"
          type="email"
          autocomplete="email"
        >
      </div>

      <div class="space-y-2">
        <label
          class="block text-sm text-ink-muted"
          for="password"
        >{{ t('auth.password_label') }}</label>
        <input
          id="password"
          v-model="password"
          class="w-full rounded-control border border-line px-3 py-2"
          type="password"
          autocomplete="current-password"
        >
      </div>

      <button
        class="w-full rounded-control bg-brand px-4 py-2 font-medium text-on-brand hover:bg-brand-strong"
        type="submit"
      >
        {{ t('auth.sign_in_action') }}
      </button>
    </form>
  </main>
</template>

<script setup lang="ts">
// Placeholder. Shows the shape of the sign-in screen; it does not call the
// API yet.
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const email = ref('')
const password = ref('')
</script>
