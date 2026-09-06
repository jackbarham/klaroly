<template>
  <Teleport to="body">
    <Transition name="viewmenu">
      <div
        v-if="open"
        class="fixed inset-0 z-20"
        @keydown="onKeydown"
      >
        <div
          class="absolute inset-0 bg-scrim lg:bg-transparent"
          @click="open = false"
        />

        <div
          ref="panel"
          class="viewmenu__panel absolute inset-x-0 bottom-0 rounded-t-sheet border-t border-border bg-surface-overlay px-4 pt-2 pb-4 shadow-raised sheet-bottom lg:inset-auto lg:w-75 lg:rounded-card lg:border lg:p-4 lg:shadow-menu"
          :style="panelStyle"
          role="dialog"
          aria-modal="true"
          :aria-label="t('contacts.view.title')"
          tabindex="-1"
        >
          <div
            class="mx-auto mb-3 h-1 w-10 rounded-full bg-border-strong lg:hidden"
            aria-hidden="true"
          />

          <!--
            Two segmented groups separated by space and nothing else. The only
            hairline in this panel is the one between the switches, because
            that is the only place two things of the same shape sit next to
            each other and need telling apart.
          -->
          <p
            :id="sortLabelId"
            class="mb-2 text-body font-medium text-text-strong"
          >
            {{ t('contacts.view.sort_label') }}
          </p>
          <div
            class="mb-6 flex gap-1 rounded-control bg-surface-sunken p-1"
            role="group"
            :aria-labelledby="sortLabelId"
          >
            <button
              v-for="option in sortOptions"
              :key="option.value"
              :class="[segmentClasses, settings.sort === option.value ? segmentOnClasses : segmentOffClasses]"
              type="button"
              :aria-pressed="settings.sort === option.value"
              @click="contacts.update({ sort: option.value })"
            >
              {{ t(option.labelKey) }}
            </button>
          </div>

          <p
            :id="leadLabelId"
            class="mb-2 text-body font-medium text-text-strong"
          >
            {{ t('contacts.view.lead_label') }}
          </p>
          <div
            class="mb-6 flex gap-1 rounded-control bg-surface-sunken p-1"
            role="group"
            :aria-labelledby="leadLabelId"
          >
            <button
              v-for="option in leadOptions"
              :key="option.value"
              :class="[segmentClasses, settings.leadWith === option.value ? segmentOnClasses : segmentOffClasses]"
              type="button"
              :aria-pressed="settings.leadWith === option.value"
              @click="contacts.update({ leadWith: option.value })"
            >
              {{ t(option.labelKey) }}
            </button>
          </div>

          <div class="flex min-h-11 items-center justify-between gap-4">
            <span
              :id="initialsLabelId"
              class="text-body font-medium text-text-strong"
            >{{ t('contacts.view.initials_label') }}</span>
            <ToggleSwitch
              :id="initialsSwitchId"
              v-model="showInitials"
              :labelled-by="initialsLabelId"
            />
          </div>

          <div class="mt-2 flex min-h-11 items-center justify-between gap-4 border-t border-border pt-2">
            <span
              :id="amountsLabelId"
              class="text-body font-medium text-text-strong"
            >{{ t('contacts.view.amounts_label') }}</span>
            <ToggleSwitch
              :id="amountsSwitchId"
              v-model="showAmounts"
              :labelled-by="amountsLabelId"
            />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
// The four things that change how this list reads: how it is sorted, which of
// a row's two lines is the strong one, whether initials are drawn and whether
// money is shown at all.
//
// It stays open while any of them change, which is the point: the list redraws
// underneath and each setting is judged by its effect rather than by its name.
//
// It does not use ui/Sheet.vue, and that is the same call MonthJumpSheet made
// for the same reason. Sheet's desktop anchor is a closed set of two fixed
// sidebar geometries; this panel hangs off a button whose position has to be
// measured, and right-aligned to it so that three hundred pixels of panel stay
// over the list column rather than spilling across the detail. What the two do
// share, the focus trap, Escape, the scrim and giving focus back to the
// trigger, is useDialogBehaviour, which both call, so there is one definition
// of how a dialog behaves rather than two that drift.
//
// There are now two panels of this shape. If a third arrives, or if these two
// turn out to be the same panel, that is the moment to extract one and move
// both callers together.
import { computed, nextTick, ref, useId, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import { useDialogBehaviour } from '@/lib/dialog'
import { useContactsStore } from '@/stores/contacts'
import type { LeadWith, SortMode } from '@/lib/contactView'

const props = defineProps<{
  // The button this hangs under at lg and up.
  anchorTo?: HTMLElement | null
}>()

const { t } = useI18n()
const contacts = useContactsStore()

const open = defineModel<boolean>('open', { required: true })

const panel = useTemplateRef<HTMLElement>('panel')

const { onKeydown } = useDialogBehaviour(panel, open)

const settings = computed(() => contacts.settings)

const sortLabelId = useId()
const leadLabelId = useId()
const initialsLabelId = useId()
const initialsSwitchId = useId()
const amountsLabelId = useId()
const amountsSwitchId = useId()

const sortOptions: { value: SortMode, labelKey: string }[] = [
  { value: 'recent', labelKey: 'contacts.view.sort_recent' },
  { value: 'alpha', labelKey: 'contacts.view.sort_alpha' },
]

const leadOptions: { value: LeadWith, labelKey: string }[] = [
  { value: 'name', labelKey: 'contacts.view.lead_name' },
  { value: 'booking', labelKey: 'contacts.view.lead_booking' },
]

// A switch writes straight through to the store, which persists it, so there
// is no local copy that could be a setting behind what the list is drawing.
const showInitials = computed({
  get: () => contacts.settings.showInitials,
  set: (value: boolean) => contacts.update({ showInitials: value }),
})

const showAmounts = computed({
  get: () => contacts.settings.showAmounts,
  set: (value: boolean) => contacts.update({ showAmounts: value }),
})

// The selected segment is a filled accent carrying a white label. That is a
// deliberate departure from the segmented control in docs/style-guide.md,
// which is quieter: here the two groups sit inside a panel that is itself over
// a scrim, and the quieter treatment could not be read as chosen at a glance.
const segmentClasses = 'h-11 grow rounded-control text-body font-medium transition-colors focus-visible:focus-ring'
const segmentOnClasses = 'bg-accent text-text-on-accent'
const segmentOffClasses = 'text-text-muted hover:text-accent-text'

// Where the panel sits at lg and up. It is fixed and teleported to the body,
// so these are viewport coordinates and nothing between here and the root can
// shift them.
//
// The right edge is what is pinned, not the left, so the panel grows leftwards
// away from the detail column whatever its width turns out to be.
const panelStyle = ref<Record<string, string>>({})

watch(open, async (isOpen) => {
  if (!isOpen) {
    return
  }

  await nextTick()

  const trigger = props.anchorTo

  if (trigger) {
    const anchor = trigger.getBoundingClientRect()

    panelStyle.value = {
      '--menu-top': `${Math.round(anchor.bottom + 8)}px`,
      '--menu-right': `${Math.round(window.innerWidth - anchor.right)}px`,
    }
  }
})
</script>

<style scoped>
/*
  The scrim fades and the panel moves. Transform and opacity only, so the whole
  thing stays on the compositor, and both directions run at --duration-base on
  the app's one curve: no component in this codebase writes a duration.
*/
.viewmenu-enter-active,
.viewmenu-leave-active {
  transition: opacity var(--duration-base) var(--ease-out);
}

.viewmenu-enter-active .viewmenu__panel,
.viewmenu-leave-active .viewmenu__panel {
  transition: transform var(--duration-base) var(--ease-out);
}

.viewmenu-enter-from,
.viewmenu-leave-to {
  opacity: 0;
}

/* Below lg it is a bottom sheet and travels its own height up from the bottom
   edge, which is the one place a long move is right. */
.viewmenu-enter-from .viewmenu__panel,
.viewmenu-leave-to .viewmenu__panel {
  transform: translateY(100%);
}

/* At lg the panel hangs under the button, at the position measured when it
   opened, and drops out of that button rather than up from the floor. */
@media (width >= 64rem) {
  .viewmenu__panel {
    top: var(--menu-top);
    right: var(--menu-right);
    transform-origin: top right;
  }

  .viewmenu-enter-from .viewmenu__panel,
  .viewmenu-leave-to .viewmenu__panel {
    transform: translateY(calc(var(--spacing) * -4));
  }
}

@media (prefers-reduced-motion: reduce) {
  .viewmenu-enter-active,
  .viewmenu-leave-active,
  .viewmenu-enter-active .viewmenu__panel,
  .viewmenu-leave-active .viewmenu__panel {
    transition: none;
  }

  .viewmenu-enter-from .viewmenu__panel,
  .viewmenu-leave-to .viewmenu__panel {
    transform: none;
  }
}
</style>
