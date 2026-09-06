<template>
  <Teleport to="body">
    <Transition name="confirm">
      <div
        v-if="open"
        class="fixed inset-0 z-30"
        @keydown="onKeydown"
      >
        <div
          class="absolute inset-0 bg-scrim"
          @click="open = false"
        />

        <div
          ref="panel"
          class="confirm__panel absolute inset-x-4 rounded-card border border-border bg-surface-overlay p-4 shadow-raised max-lg:above-bar lg:right-6 lg:bottom-6 lg:left-auto lg:w-100"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :aria-describedby="bodyId"
          tabindex="-1"
        >
          <h2
            :id="titleId"
            class="text-body font-medium text-text-strong"
          >
            {{ t('contacts.delete.title') }}
          </h2>
          <p
            :id="bodyId"
            class="mt-1 text-body text-text-muted"
          >
            {{ t('contacts.delete.confirm', { name }) }}
          </p>

          <div class="mt-4 flex justify-end gap-2">
            <button
              class="chip focus-visible:focus-ring"
              type="button"
              @click="open = false"
            >
              {{ t('contacts.delete.cancel') }}
            </button>
            <button
              class="chip chip-danger focus-visible:focus-ring"
              type="button"
              @click="confirm"
            >
              {{ t('contacts.delete.confirm_action') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
// The one thing on this screen that asks a question rather than answering one.
//
// It is a dialog and not the notice with a second button, and the difference
// is the point. A notice is a polite live region: a screen reader is told this
// is a passive announcement, and handing somebody an irreversible action
// inside one is the wrong markup for it. A notice also clears itself on a
// timer, and a confirm that disappears while you are still reading it is a
// control that decides for you.
//
// So this traps focus, closes on Escape and on the scrim, gives focus back to
// the chip that opened it, and waits for as long as it takes.
import { useId, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDialogBehaviour } from '@/lib/dialog'

defineProps<{
  name: string
}>()

const emit = defineEmits<{
  confirm: []
}>()

const { t } = useI18n()

const open = defineModel<boolean>('open', { required: true })

const panel = useTemplateRef<HTMLElement>('panel')
const titleId = useId()
const bodyId = useId()

const { onKeydown } = useDialogBehaviour(panel, open)

function confirm(): void {
  open.value = false
  emit('confirm')
}
</script>

<style scoped>
.confirm-enter-active,
.confirm-leave-active {
  transition: opacity var(--duration-base) var(--ease-out);
}

.confirm-enter-active .confirm__panel,
.confirm-leave-active .confirm__panel {
  transition: transform var(--duration-base) var(--ease-out);
}

.confirm-enter-from,
.confirm-leave-to {
  opacity: 0;
}

.confirm-enter-from .confirm__panel,
.confirm-leave-to .confirm__panel {
  transform: translateY(calc(var(--spacing) * 4));
}

@media (prefers-reduced-motion: reduce) {
  .confirm-enter-active,
  .confirm-leave-active,
  .confirm-enter-active .confirm__panel,
  .confirm-leave-active .confirm__panel {
    transition: none;
  }

  .confirm-enter-from .confirm__panel,
  .confirm-leave-to .confirm__panel {
    transform: none;
  }
}
</style>
