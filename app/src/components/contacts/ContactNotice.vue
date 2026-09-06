<template>
  <Teleport to="body">
    <!--
      The live region is always in the page and only the panel inside it comes
      and goes. A region inserted at the same moment as its text is a region a
      screen reader may never announce, because there was nothing there to
      change; a region that is already there announces what appears in it.

      It takes no pointer events while it is empty, so an invisible strip
      cannot swallow a tap meant for whatever is underneath it.
    -->
    <div
      class="pointer-events-none fixed inset-x-4 z-30 max-lg:above-bar lg:right-6 lg:bottom-6 lg:left-auto lg:w-100"
      role="status"
      aria-live="polite"
    >
      <Transition name="notice">
        <div
          v-if="open"
          class="pointer-events-auto flex items-start gap-4 rounded-card border border-border bg-surface-overlay p-4 shadow-raised"
        >
          <p class="min-w-0 grow text-body text-text">
            {{ message }}
          </p>
          <button
            class="chip shrink-0 focus-visible:focus-ring"
            type="button"
            @click="open = false"
          >
            {{ t('contacts.delete.dismiss') }}
          </button>
        </div>
      </Transition>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
// A reply to something just tapped: it reports, it needs no answer, and it
// goes away on its own.
//
// It takes no focus, which is why it is a polite live region rather than a
// dialog: interrupting somebody to tell them a button did nothing is worse
// than the button doing nothing. Anything that needs an answer, and in
// particular anything destructive, is a dialog instead. See
// ContactDeleteDialog, which is the other half of this pair and deliberately
// not this component with a second button bolted on.
//
// On a phone it sits clear of the floating tab bar and its safe area, through
// the same utility a sticky row of form actions uses. On a wide screen it is
// capped and anchored bottom right, because a full-width bar across a 1200px
// frame reads as something the system is telling you rather than as an answer
// to a button you pressed a second ago.
//
// It is local to Contacts. If a second screen wants one, that is the moment it
// moves to components/ui, with both callers changing together.
import { onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps<{
  message: string
}>()

const { t } = useI18n()

const open = defineModel<boolean>('open', { required: true })

// Long enough to read a sentence twice, short enough that it is gone before
// anybody wonders how to get rid of it. The OK button is there for whoever
// would rather not wait.
const dismissAfter = 4500

let timer: number | null = null

function stop(): void {
  if (timer !== null) {
    window.clearTimeout(timer)
    timer = null
  }
}

// Restarted rather than left running, so tapping Delete twice gives the second
// message its own full four and a half seconds instead of the remainder of the
// first one's.
watch(open, (isOpen) => {
  stop()

  if (isOpen) {
    timer = window.setTimeout(() => {
      open.value = false
    }, dismissAfter)
  }
})

onBeforeUnmount(stop)
</script>

<style scoped>
.notice-enter-active,
.notice-leave-active {
  transition:
    opacity var(--duration-base) var(--ease-out),
    transform var(--duration-base) var(--ease-out);
}

.notice-enter-from,
.notice-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

@media (prefers-reduced-motion: reduce) {
  .notice-enter-active,
  .notice-leave-active {
    transition: none;
  }

  .notice-enter-from,
  .notice-leave-to {
    transform: none;
  }
}
</style>
