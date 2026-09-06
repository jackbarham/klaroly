<template>
  <div>
    <!--
      The way back to the list, and only where the list is not on screen. It
      answers the same container query the layout does rather than a viewport
      breakpoint, so it appears exactly when the list has gone and never while
      it is sitting beside this card.
    -->
    <RouterLink
      class="mb-4 inline-flex items-center gap-1 rounded-xs text-body text-text-muted transition-colors hover:text-accent-text focus-visible:focus-ring @split:hidden"
      :to="{ name: 'contacts' }"
    >
      <Icon
        name="chevron-left"
        class="size-4"
      />
      {{ t('contacts.detail.back') }}
    </RouterLink>

    <div class="mb-6 flex items-center gap-4">
      <span
        class="grid size-14 shrink-0 place-items-center rounded-pill bg-accent-subtle text-lead font-medium text-accent-text"
        aria-hidden="true"
      >{{ initials(contact) }}</span>
      <h2 class="min-w-0 text-title font-medium break-words text-text-strong">
        {{ fullName(contact) }}
      </h2>
    </div>

    <!--
      The reach block. The same actions at both sizes, from the same markup,
      with nothing asking what device this is: every one of them is an ordinary
      link that the operating system already knows what to do with, and most
      desktops place a call one way or another now.
    -->
    <div class="border-t border-border">
      <div class="flex items-start justify-between gap-4 border-b border-border py-4">
        <div class="min-w-0">
          <p class="text-meta text-text-muted">
            {{ t('contacts.detail.phone_label') }}
          </p>
          <p
            class="text-control"
            :class="contact.phone ? 'text-text-strong' : 'text-text-muted'"
          >
            {{ contact.phone ?? t('contacts.detail.no_phone') }}
          </p>
        </div>
        <div
          v-if="contact.phone"
          class="flex shrink-0 items-center gap-2"
        >
          <a
            class="chip focus-visible:focus-ring"
            :href="`tel:${dialable}`"
            :aria-label="t('contacts.detail.action_call_label', { name: fullName(contact) })"
          >{{ t('contacts.detail.action_call') }}</a>
          <!-- sms: takes &body= on iOS and ?body= on Android, if a message
               template is ever wanted here. -->
          <a
            class="chip focus-visible:focus-ring"
            :href="`sms:${dialable}`"
            :aria-label="t('contacts.detail.action_text_label', { name: fullName(contact) })"
          >{{ t('contacts.detail.action_text') }}</a>
        </div>
      </div>

      <div class="flex items-start justify-between gap-4 border-b border-border py-4">
        <div class="min-w-0">
          <p class="text-meta text-text-muted">
            {{ t('contacts.detail.email_label') }}
          </p>
          <p
            class="truncate text-control"
            :class="contact.email ? 'text-text-strong' : 'text-text-muted'"
          >
            {{ contact.email ?? t('contacts.detail.no_email') }}
          </p>
        </div>
        <a
          v-if="contact.email"
          class="chip shrink-0 focus-visible:focus-ring"
          :href="`mailto:${contact.email}`"
          :aria-label="t('contacts.detail.action_email_label', { name: fullName(contact) })"
        >{{ t('contacts.detail.action_email') }}</a>
      </div>

      <div class="flex items-start justify-between gap-4 border-b border-border py-4">
        <div class="min-w-0">
          <p class="text-meta text-text-muted">
            {{ t('contacts.detail.address_label') }}
          </p>
          <p
            v-if="addressLines.length > 0"
            class="text-control text-text-strong"
          >
            <span
              v-for="line in addressLines"
              :key="line"
              class="block"
            >{{ line }}</span>
          </p>
          <p
            v-else
            class="text-control text-text-muted"
          >
            {{ t('contacts.detail.no_address') }}
          </p>
        </div>
        <a
          v-if="addressLines.length > 0"
          class="chip shrink-0 focus-visible:focus-ring"
          :href="mapsUrl"
          target="_blank"
          rel="noreferrer"
          :aria-label="t('contacts.detail.action_maps_label', { name: fullName(contact) })"
        >{{ t('contacts.detail.action_maps') }}</a>
      </div>
    </div>

    <h3 class="mt-8 mb-2 text-body font-medium text-text-strong">
      {{ t('contacts.detail.bookings_title') }}
    </h3>

    <p
      v-if="bookings.length === 0"
      class="border-t border-border py-4 text-body text-text-muted"
    >
      {{ t('contacts.detail.no_bookings') }}
    </p>

    <ul
      v-else
      class="border-t border-border"
    >
      <li
        v-for="booking in bookings"
        :key="booking.id"
      >
        <RouterLink
          class="booking-line flex items-center gap-3 border-b border-border py-3 transition-colors hover:border-accent focus-visible:focus-ring focus-visible:-outline-offset-2"
          :to="{ name: 'booking', params: { id: booking.id } }"
        >
          <span class="min-w-0 grow">
            <span class="booking-line__lead block truncate text-body font-medium text-text-strong transition-colors">
              {{ t(`bookings.event_type.${booking.event_type}`) }}, {{ dateOf(booking) }}
            </span>
            <span class="block truncate text-meta text-text-muted">{{ venueShort(booking) ?? t('bookings.list.no_venue') }}</span>
          </span>
          <span class="shrink-0 text-body font-medium tabular-nums text-text-strong">{{ totalOf(booking) }}</span>
          <Icon
            name="chevron-right"
            class="size-5 shrink-0 text-text-placeholder"
          />
        </RouterLink>
      </li>
    </ul>

    <!--
      Both start their flow with this contact already attached, which is the
      whole reason they are on this card rather than only in the create menu.
    -->
    <div class="mt-6 flex flex-wrap gap-2">
      <AppButton
        size="small"
        @click="onCreate"
      >
        {{ t('create.enquiry') }}
      </AppButton>
      <AppButton
        size="small"
        @click="onCreate"
      >
        {{ t('create.booking') }}
      </AppButton>
    </div>

    <div class="mt-6 flex flex-wrap gap-2 border-t border-border pt-6">
      <button
        class="chip focus-visible:focus-ring"
        type="button"
        @click="onSaveToPhone"
      >
        {{ t('contacts.detail.save_to_phone') }}
      </button>
      <button
        class="chip focus-visible:focus-ring"
        type="button"
        @click="onEdit"
      >
        {{ t('contacts.detail.edit') }}
      </button>
      <!--
        Always active. There is no disabled state and no paragraph under these
        buttons explaining when it will not work: what stops a deletion is
        schema 5.7's restrict on bookings.contact_id, and that is said as one
        line at the moment it matters rather than as standing small print.
      -->
      <button
        class="chip chip-danger focus-visible:focus-ring"
        type="button"
        :aria-label="t('contacts.detail.delete_label', { name: fullName(contact) })"
        @click="emit('delete')"
      >
        {{ t('contacts.detail.delete') }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
// One contact's card: their name, one number, their email, their address and
// their bookings.
//
// There is no summary line, no note stream and no rolled-up money here, and
// that was chosen rather than left out. Every invoice, agreement and message
// in this product belongs to a BOOKING, so putting any of them on a contact
// would create a second screen that has to agree with the booking screen for
// ever. What a contact is for is reaching the person and getting to their
// work, and that is what this is.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { format, parseISO } from 'date-fns'
import Icon from '@/components/ui/Icon.vue'
import { digitsOf, fullName, initials, venueShort } from '@/lib/contactList'
import type { Contact, ContactBooking } from '@/types/contacts'

const props = defineProps<{
  contact: Contact
}>()

const emit = defineEmits<{
  delete: []
}>()

const { t, n } = useI18n()

// tel: and sms: want the number without the spaces it is written with.
const dialable = computed(() => digitsOf(props.contact.phone ?? ''))

const addressLines = computed(() => [
  props.contact.address_line_1,
  props.contact.address_line_2,
  props.contact.city,
  props.contact.postcode,
].filter((line): line is string => Boolean(line)))

// The country goes into the query and is not drawn as a line: on a UK-only
// account it would put "GB" under every address on the screen.
const mapsUrl = computed(() => {
  const query = [...addressLines.value, props.contact.country].filter(Boolean).join(', ')

  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`
})

// Newest first, which is the order somebody thinks about their own work in.
const bookings = computed(() => [...props.contact.bookings].sort((a, b) => b.date.localeCompare(a.date)))

function dateOf(booking: ContactBooking): string {
  return format(parseISO(booking.date), t('contacts.format.full_date'))
}

function totalOf(booking: ContactBooking): string {
  return n(booking.total_minor / 100, { key: 'currency', currency: booking.currency })
}

function onCreate(): void {
  // TODO: both flows are wired up by the prompt that builds the create flow,
  // the same one that wires up the three rows of CreateMenu.
}

function onSaveToPhone(): void {
  // TODO: a vCard the browser or the native shell hands to the address book.
}

function onEdit(): void {
  // TODO: editing a contact needs the write endpoint that does not exist yet.
}
</script>

<style scoped>
/* A booking line follows the row into the accent on hover, the same way a
   contact row and a booking row already do. */
.booking-line:hover .booking-line__lead {
  color: var(--accent-text);
}
</style>
