<template>
  <RouterLink
    :id="optionId"
    class="contact-row group flex min-h-16 items-center gap-3 border-b border-border px-6 py-3 transition-colors hover:border-accent focus-visible:focus-ring focus-visible:-outline-offset-2 @split:px-4"
    :class="stateClasses"
    role="option"
    :aria-selected="active"
    :aria-current="current ? 'true' : undefined"
    :to="{ name: 'contact', params: { id: contact.id } }"
  >
    <span
      v-if="showInitials"
      class="grid size-10 shrink-0 place-items-center rounded-pill bg-accent-subtle text-body font-medium text-accent-text"
      aria-hidden="true"
    >{{ initials(contact) }}</span>

    <span class="min-w-0 grow">
      <span
        class="contact-row__lead block truncate text-control font-medium text-text-strong transition-colors"
      >{{ leadWith === 'booking' ? booking : name }}</span>
      <span class="block truncate text-meta text-text-muted">{{ leadWith === 'booking' ? name : booking }}</span>
    </span>

    <StatusPill
      v-if="pill"
      :tone="pillTone"
    >
      {{ pillLabel }}
    </StatusPill>

    <Icon
      name="chevron-right"
      class="size-5 shrink-0 text-text-placeholder"
    />
  </RouterLink>
</template>

<script setup lang="ts">
// One contact in the list.
//
// The row is an option inside a listbox rather than a plain link, because the
// filter field above it arrows through these while keeping focus, and that is
// what aria-activedescendant needs on the other end. It is still a real link,
// so a click, a middle click and Enter on a focused row all behave.
//
// Two different marks, deliberately: `active` is where the keyboard cursor is
// and `current` is whose card is open beside the list. They are separate
// because on a wide screen both are on screen at once and they mean different
// things.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import StatusPill, { type PillTone } from '@/components/ui/StatusPill.vue'
import { fullName, initials, nearestBooking, pillFor, secondLine, type PillKind } from '@/lib/contactList'
import type { LeadWith } from '@/lib/contactView'
import type { Contact } from '@/types/contacts'

const props = defineProps<{
  contact: Contact
  leadWith: LeadWith
  showInitials: boolean
  showAmounts: boolean
  today: Date
  // The DOM id the filter field points at with aria-activedescendant.
  optionId: string
  active: boolean
  current: boolean
}>()

const { t, n } = useI18n()

const name = computed(() => fullName(props.contact))

// Always the nearest booking, never the phone number. A number is what you
// need once you have found somebody, and this line is for finding them.
const booking = computed(() => {
  const nearest = nearestBooking(props.contact)

  return nearest ? secondLine(nearest, props.today, t) : t('contacts.list.no_bookings')
})

const pill = computed(() => pillFor(props.contact, props.showAmounts))

// Which pill reads as which tone is this screen's decision; StatusPill knows
// nothing about contacts. Overdue is the only genuinely bad one. Owing is a
// warning because it is money that has not arrived and may need chasing, and
// Upcoming is the accent because it is not a status at all: it is the reason
// this row is worth looking at today.
const toneByKind: Record<PillKind, PillTone> = {
  overdue: 'danger',
  owes: 'warning',
  upcoming: 'accent',
}

const pillTone = computed(() => (pill.value ? toneByKind[pill.value.kind] : 'neutral'))

const pillLabel = computed(() => {
  const current = pill.value

  if (!current) {
    return ''
  }

  if (!current.amount) {
    return t('contacts.pill.upcoming')
  }

  // Money never becomes a string by putting a pound sign in front of a number.
  // The minor units are divided here rather than stored as a decimal: the
  // integer is the value, and this is the last moment before it is drawn. A
  // round amount drops its pence, because "£450.00" at 12px inside a pill is
  // two characters of noise; anything else keeps them, because a rounded
  // balance is the wrong balance.
  const amount = n(current.amount.minor / 100, {
    key: current.amount.minor % 100 === 0 ? 'currency_whole' : 'currency',
    currency: current.amount.currency,
  })

  return t(`contacts.pill.${current.kind}`, { amount })
})

// The open contact keeps a tint so a wide screen says which card is showing.
// The keyboard cursor is the fainter row hover, so the two never look the same
// even when they are on different rows.
const stateClasses = computed(() => {
  if (props.current) {
    return 'bg-accent-subtle border-accent'
  }

  return props.active ? 'bg-row-hover border-accent' : 'hover:bg-row-hover'
})
</script>

<style scoped>
/* The lead line follows the row into the accent, so a pointed-at row says so
   in words as well as in fill. It is the app's hover rule everywhere else, and
   it is written here rather than as group-hover:text-accent-text so that the
   open row, which is already wearing the accent, does not fight it. */
.contact-row:hover .contact-row__lead {
  color: var(--accent-text);
}
</style>
