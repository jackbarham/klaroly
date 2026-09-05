<template>
  <div class="space-y-12">
    <!--
      The grid on its own, at both densities, side by side at one width. This
      is what the density prop is for: in the app it is 'auto' and the
      container query decides, and there is no way to see the two shapes
      together without pinning them.
    -->
    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          MonthGrid, both densities
        </h3>
        <p class="text-xs text-text-muted">
          The same component and the same marks. Comfortable is the phone
          shape: square cells, never under the 44px tap minimum. Compact is
          what a wide screen gets, where the calendar is a column beside the
          list and does not need the height. In the app neither is passed and
          the container query at --container-split chooses.
        </p>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <div
          v-for="density in (['comfortable', 'compact'] as const)"
          :key="density"
          class="space-y-2"
        >
          <code class="font-mono text-xs text-text-muted">density="{{ density }}"</code>
          <MonthGrid
            :month="month"
            :marks="marks"
            :selected="selected"
            :today="today"
            :density="density"
            label="Demonstration month"
            @select="onSelect"
          />
        </div>
      </div>
    </div>

    <!--
      Every mark on one row, because the whole argument for this set is that
      they differ by shape before they differ by colour.
    -->
    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          The three marks, and the two states that carry none
        </h3>
        <p class="text-xs text-text-muted">
          Filled, outlined, and a badge that appears alongside either rather
          than instead of it. Roughly one man in twelve cannot separate these
          by hue, so shape does the work and colour reinforces it. The last
          cell is the case the screen exists for: one confirmed booking and
          three live enquiries on the same Saturday, both visible at once.
        </p>
      </div>

      <div class="flex flex-wrap items-start gap-6">
        <div
          v-for="sample in markSamples"
          :key="sample.label"
          class="w-24 space-y-2"
        >
          <MonthGrid
            :month="sample.date"
            :marks="sample.marks"
            :today="sample.date"
            density="comfortable"
            :label="sample.label"
          />
          <p class="text-xs text-text-muted">
            {{ sample.label }}
          </p>
        </div>
      </div>
      <p class="text-xs text-text-muted">
        Those are single-day grids, so every cell but the middle one is
        outside its month and fades its contents rather than its background.
      </p>
    </div>

    <!-- The whole calendar, live. -->
    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          BookingsCalendar
        </h3>
        <p class="text-xs text-text-muted">
          The header, the swipe track and the jump sheet. Tap the month title
          to open the sheet: a bottom sheet below lg and a panel under the
          title above it, with a dot on every month that has work in it. Today
          and Week are the same control with different words. Drag the grid
          sideways to change month.
        </p>
      </div>

      <div class="rounded-card border border-border p-4">
        <BookingsCalendar
          v-model:month="month"
          v-model:mode="mode"
          :marks="marks"
          :months-with-work="monthsWithWork"
          :selected="selected"
          :today="today"
          @select="onSelect"
        />
      </div>
      <p class="text-xs text-text-muted">
        Selected: {{ selected ? selected.toDateString() : 'nothing' }}. Mode:
        {{ mode }}.
      </p>
    </div>

    <!-- The rows. -->
    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          BookingRow
        </h3>
        <p class="text-xs text-text-muted">
          One event, not one booking, so a client with a trial and a wedding
          appears twice. The spine is the state at a glance and the pill is
          the state in words. The last two rows are the fallbacks: a venue
          that is only a city, and an enquiry with neither, which says so
          rather than leaving a gap. Only an enquiry carries how long ago it
          was last touched, and it is quiet meta rather than a third pill,
          because the waiting-on pill is the authority on whether it has gone
          cold.
        </p>
      </div>

      <ul class="rounded-card border border-border">
        <BookingRow
          v-for="row in rowSamples"
          :key="row.id"
          :event="row"
          :today="today"
        />
      </ul>
    </div>

    <!-- The list, with its groups and both empty states. -->
    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          BookingList
        </h3>
        <p class="text-xs text-text-muted">
          Unfiltered it is upcoming work under sticky headings, urgent end
          first. Filtered to a day it is that day, including work already
          done, with a way back out.
        </p>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-2">
          <code class="font-mono text-xs text-text-muted">unfiltered</code>
          <div class="rounded-card border border-border">
            <BookingList
              :events="events"
              :today="today"
            />
          </div>
        </div>
        <div class="space-y-2">
          <code class="font-mono text-xs text-text-muted">a day with nothing on it</code>
          <div class="rounded-card border border-border">
            <BookingList
              :events="events"
              :selected="quietDay"
              :today="today"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// The bookings screen's components, in every state they have.
//
// The demo events are written out here rather than taken from
// src/lib/bookingFixtures.ts, because a component may not reach the fixtures
// and this page is no exception: src/lib/bookings.guards.test.ts would fail,
// and it would be right to.
import { computed, ref } from 'vue'
import BookingList from '@/components/bookings/BookingList.vue'
import BookingRow from '@/components/bookings/BookingRow.vue'
import BookingsCalendar from '@/components/bookings/BookingsCalendar.vue'
import MonthGrid from '@/components/bookings/MonthGrid.vue'
import { marksFor, useDayMarks } from '@/lib/dayMarks'
import { dayKey } from '@/lib/monthGrid'
import type { GridMode } from '@/lib/monthGrid'
import type { BookingEvent, BookingStage } from '@/types/bookings'

// A fixed month, so this page looks the same whenever it is opened.
const today = new Date(2026, 8, 5)
const month = ref(new Date(2026, 8, 1))
const mode = ref<GridMode>('month')
const selected = ref<Date | null>(null)
const quietDay = new Date(2026, 8, 15)

let nextId = 0

function event(day: number, over: Partial<BookingEvent> = {}): BookingEvent {
  nextId += 1

  return {
    id: nextId,
    booking_id: nextId,
    type: 'main',
    label: null,
    date: dayKey(new Date(2026, 8, day)),
    start_time: '06:30',
    venue_name: 'Thornleigh Hall',
    city: 'Marlow',
    client_name: 'Hannah Wallace',
    stage: 'confirmed',
    total_minor: 78000,
    currency: 'GBP',
    waiting_on: null,
    last_touched_at: new Date(2026, 7, 27).toISOString(),
    ...over,
  }
}

const events: BookingEvent[] = [
  event(5, { client_name: 'Hannah Wallace' }),
  event(8, { client_name: 'Priya Raman', type: 'trial', venue_name: null, city: 'Reading', total_minor: 6000, start_time: '10:00' }),
  event(12, { client_name: 'Priya Raman', venue_name: 'Marlbrook Court', city: 'Guildford', total_minor: 94000 }),
  event(19, { client_name: 'Sophie Ellis', stage: 'provisional', waiting_on: 'client_signature', venue_name: 'Wraysbury Mill', total_minor: 68000 }),
  event(26, { client_name: 'Amelia Trent', venue_name: 'Ashcombe Barn', city: 'Ware', total_minor: 112000 }),
  event(26, { client_name: 'Rosie Kerr', stage: 'possible', venue_name: null, city: null, total_minor: 0, start_time: null, last_touched_at: new Date(2026, 8, 1).toISOString() }),
  event(26, { client_name: 'Nadia Iqbal', stage: 'possible', venue_name: 'Pentreath House', city: 'Bath', total_minor: 0, start_time: null, last_touched_at: new Date(2026, 7, 24).toISOString() }),
  event(26, { client_name: 'Charlotte Dean', stage: 'quoted', venue_name: null, city: 'Swansea', total_minor: 54000, start_time: null, waiting_on: 'artist_enquiry_cold', last_touched_at: new Date(2026, 6, 26).toISOString() }),
  event(30, { client_name: 'Jo Fenwick', stage: 'cancelled', total_minor: 66000 }),
]

const { marks } = useDayMarks(() => events)

// Its own, because the store's copy comes from GET /api/events/months and this
// page makes no requests. Three lines here is cheaper than a demo page that
// needs a store.
const monthsWithWork = computed(() => new Set(events.map((one) => one.date.slice(0, 7))))

// One-day grids, so each mark can be looked at on its own.
function sampleMarks(stages: BookingStage[]) {
  return marksFor(stages.map((stage, index) => ({
    ...event(16, { stage }),
    id: 900 + index,
  })))
}

const markSamples = [
  { label: 'Confirmed', date: new Date(2026, 8, 16), marks: sampleMarks(['confirmed']) },
  { label: 'Provisional', date: new Date(2026, 8, 16), marks: sampleMarks(['provisional']) },
  { label: 'Two enquiries', date: new Date(2026, 8, 16), marks: sampleMarks(['possible', 'quoted']) },
  { label: 'Cancelled, no mark', date: new Date(2026, 8, 16), marks: sampleMarks(['cancelled']) },
  { label: 'One booking and three enquiries', date: new Date(2026, 8, 16), marks: sampleMarks(['confirmed', 'possible', 'possible', 'quoted']) },
]

const rowSamples = computed<BookingEvent[]>(() => [
  event(12, { client_name: 'Priya Raman', stage: 'confirmed', total_minor: 94000 }),
  event(19, { client_name: 'Sophie Ellis', stage: 'provisional', waiting_on: 'client_signature', total_minor: 68000 }),
  event(20, { client_name: 'Bea Lawson', stage: 'provisional', waiting_on: 'client_deposit', total_minor: 86000 }),
  event(21, { client_name: 'Grace Muir', type: 'trial', label: 'Trial and hair', total_minor: 7500, start_time: '13:00' }),
  event(22, { client_name: 'Freya Lindqvist', stage: 'completed', waiting_on: 'client_balance', total_minor: 84000 }),
  event(23, { client_name: 'Jo Fenwick', stage: 'cancelled', total_minor: 66000 }),
  event(24, { client_name: 'Martha Oyelaran', stage: 'lost', total_minor: 0, start_time: null, venue_name: null, city: null }),
  event(25, { client_name: 'Nadia Iqbal', stage: 'possible', total_minor: 0, start_time: null, venue_name: null, city: 'Bath', last_touched_at: new Date(2026, 7, 24).toISOString() }),
  event(26, { client_name: 'Rosie Kerr', stage: 'quoted', total_minor: 54000, start_time: null, venue_name: null, city: null, waiting_on: 'artist_enquiry_cold', last_touched_at: new Date(2026, 6, 26).toISOString() }),
])

function onSelect(date: Date): void {
  selected.value = selected.value && dayKey(selected.value) === dayKey(date) ? null : date
}
</script>
