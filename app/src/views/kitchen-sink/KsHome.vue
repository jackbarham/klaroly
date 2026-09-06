<template>
  <div class="space-y-8">
    <div>
      <p class="mb-2 text-meta text-text-muted">
        HomeHeader: a 44px bar, 52px at lg. No date and no bell, both of which
        were drawn and removed.
      </p>
      <HomeHeader
        :expanded="false"
        @adjust="() => {}"
      />
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        The attention rows, one per waiting-on value. The only colour on the
        screen is the days-late figure on the two money rows.
      </p>
      <div class="-mx-6 rounded-card border border-border">
        <AttentionList
          :rows="rows"
          :today="today"
          :limit="null"
        />
      </div>
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        The same list capped at four, which is what a phone previews. The band
        headings count what is drawn; the block's See all carries the total.
      </p>
      <div class="-mx-6 rounded-card border border-border">
        <AttentionBlock
          :rows="rows"
          :today="today"
          :limit="4"
          :total="rows.length"
        />
      </div>
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        The all-clear, which replaces the block on a quiet week rather than
        leaving a silence the artist has to interpret.
      </p>
      <AttentionBlock
        :rows="[]"
        :today="today"
        :limit="4"
        :total="0"
      />
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        Next up. The party is drawn on the wedding and not on the trial, because
        party_size is the whole booking's party.
      </p>
      <div class="-mx-6">
        <NextUpBlock
          :events="events"
          :today="today"
        />
      </div>
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        Money, in its three toggle states. snoozed_minor sits under the
        headline; outstanding.snoozed_minor is a subset of overdue and is never
        a third figure.
      </p>
      <div class="-mx-6 space-y-8">
        <MoneyBlock
          :money="money"
          period="this_month"
        />
        <MoneyBlock
          :money="{ ...money, owed_minor: null, owed_count: null, snoozed_minor: null, outstanding: null }"
          period="this_month"
        />
        <MoneyBlock
          :money="{ ...money, basis: 'booking_value', owed_minor: null, owed_count: null, snoozed_minor: null, outstanding: null }"
          period="this_month"
        />
      </div>
    </div>

    <div>
      <p class="mb-2 text-meta text-text-muted">
        The first-run state, which replaces all three blocks on an account with
        nothing in it.
      </p>
      <FirstRun @create="() => {}" />
    </div>

    <div>
      <AppButton
        variant="secondary"
        @click="adjust = true"
      >
        Open Adjust
      </AppButton>

      <AdjustSheet
        v-model:open="adjust"
        :order="order"
        :preview-count="previewCount"
        @order="order = $event"
        @count="previewCount = $event"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
// The home screen's blocks, in the states worth looking at.
import { ref } from 'vue'
import AdjustSheet from '@/components/home/AdjustSheet.vue'
import AttentionBlock from '@/components/home/AttentionBlock.vue'
import AttentionList from '@/components/home/AttentionList.vue'
import FirstRun from '@/components/home/FirstRun.vue'
import HomeHeader from '@/components/home/HomeHeader.vue'
import MoneyBlock from '@/components/home/MoneyBlock.vue'
import NextUpBlock from '@/components/home/NextUpBlock.vue'
import type { BlockKey, PreviewCount } from '@/lib/homeView'
import type { AttentionRow, HomeMoney, UpcomingEvent } from '@/types/home'

const today = new Date(2026, 8, 6)

const adjust = ref(false)
const order = ref<BlockKey[]>(['next', 'attention', 'money'])
const previewCount = ref<PreviewCount>(4)

function row(id: number, over: Partial<AttentionRow>): AttentionRow {
  return {
    booking_id: id,
    waiting_on: 'artist_price',
    party: 'artist',
    client_name: 'Rosie Duthie',
    contact_id: id,
    stage: 'possible',
    currency: 'GBP',
    event_date: '2027-07-04',
    trial_date: null,
    last_touched_at: '2026-08-15T09:00:00.000000Z',
    created_at: '2026-09-05T09:00:00.000000Z',
    converted_at: null,
    sent_at: null,
    hold_expires_at: null,
    outstanding_minor: null,
    invoice_total_minor: null,
    due_on: null,
    ...over,
  }
}

const rows: AttentionRow[] = [
  row(1, { waiting_on: 'artist_not_held', client_name: 'Georgia Bramwell', stage: 'provisional', converted_at: '2026-08-21T09:00:00.000000Z', hold_expires_at: '2026-09-04', event_date: '2026-10-17' }),
  row(2, { waiting_on: 'client_balance', party: 'client', client_name: 'Amelia Fenwick', stage: 'completed', outstanding_minor: 34000, invoice_total_minor: 68000, due_on: '2026-08-28' }),
  row(3, { waiting_on: 'client_deposit', party: 'client', client_name: 'Priya Ramanathan', stage: 'confirmed', outstanding_minor: 17000, due_on: '2026-09-02', event_date: '2026-09-26' }),
  row(4, { waiting_on: 'artist_enquiry_cold', client_name: 'Kit Ardern', stage: 'in_conversation', event_date: '2026-10-18' }),
  row(5, { waiting_on: 'artist_price', client_name: 'Rosie Duthie' }),
  row(6, { waiting_on: 'client_signature', party: 'client', client_name: 'Elspeth Rowntree', stage: 'confirmed', sent_at: '2026-08-26T09:00:00.000000Z', event_date: '2027-06-12' }),
]

function event(id: number, over: Partial<UpcomingEvent>): UpcomingEvent {
  return {
    event_id: id,
    booking_id: id,
    type: 'main',
    label: null,
    date: '2026-09-12',
    start_time: '06:30',
    location_type: 'venue',
    venue_name: 'Penbury Manor, Hitchin',
    city: 'Hitchin',
    client_name: 'Nadia Kerrigan',
    stage: 'confirmed',
    party_size: 5,
    travel_duration_s: 2520,
    travel_distance_m: 41400,
    ...over,
  }
}

const events: UpcomingEvent[] = [
  event(1, {}),
  event(2, { type: 'trial', date: '2026-09-17', start_time: '18:00', location_type: 'base', venue_name: null, city: null, client_name: 'Bea Ashworth', party_size: 1, travel_duration_s: null }),
  event(3, { date: '2026-09-26', start_time: '07:00', venue_name: 'The Old Corn Exchange, Saffron Walden', city: 'Saffron Walden', client_name: 'Priya Ramanathan', party_size: 7, travel_duration_s: 3900 }),
]

const money: HomeMoney = {
  currency: 'GBP',
  basis: 'payments',
  excludes_other_currencies: false,
  owed_minor: 34000,
  owed_count: 1,
  snoozed_minor: 20000,
  outstanding: { due_minor: 51000, overdue_minor: 138000, snoozed_minor: 20000 },
  booked_ahead_minor: 219000,
  booked_ahead_count: 3,
  provisional_minor: 62000,
  provisional_count: 1,
  periods: {
    this_month: { from: '2026-09-01', to: '2026-09-06', value_minor: 45000, booking_count: 1, average_value_minor: 45000 },
    three_months: { from: '2026-06-07', to: '2026-09-06', value_minor: 109000, booking_count: 3, average_value_minor: 36333 },
    twelve_months: { from: '2025-09-07', to: '2026-09-06', value_minor: 940000, booking_count: 22, average_value_minor: 42727 },
    business_year: { from: '2026-04-06', to: '2026-09-06', value_minor: 520000, booking_count: 12, average_value_minor: 43333 },
  },
}
</script>
