<template>
  <div class="space-y-8">
    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        ListRow, which is the default
      </h3>
      <p class="text-xs text-text-muted">
        The same three bookings as the table below. Hover a row: the hairline
        under it turns accent and nothing else moves. The first row is a link,
        so tabbing to it shows the ring, which is inset because a row is as
        wide as the list it sits in.
      </p>
      <ul>
        <ListRow
          v-for="(booking, index) in bookings"
          :key="booking.key"
          :to="index === 0 ? { name: 'bookings' } : undefined"
        >
          <template #leading>
            <span class="grid size-10 place-items-center rounded-pill bg-accent-subtle text-body font-medium text-accent-text">
              {{ booking.initials }}
            </span>
          </template>
          <template #title>
            {{ booking.client }}
          </template>
          <template #supporting>
            {{ booking.when }} · {{ booking.service }}
          </template>
          <template #trailing>
            <span class="block text-body tabular-nums text-text-strong">{{ booking.total }}</span>
            <StatusPill
              class="mt-1"
              :tone="booking.tone"
            >
              {{ booking.state }}
            </StatusPill>
          </template>
        </ListRow>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        DataTable, which is the same data on a wide screen
      </h3>
      <p class="text-xs text-text-muted">
        The header is regular weight and muted, the row hover is the same accent
        hairline, and the whole table scrolls inside its own box rather than
        widening the page. Narrow the window to see that happen.
      </p>
      <DataTable :columns="columns">
        <tr
          v-for="booking in bookings"
          :key="booking.key"
          :class="tableRowClasses"
        >
          <td :class="[tableCellClasses, 'font-medium text-text-strong']">
            {{ booking.client }}
          </td>
          <td :class="[tableCellClasses, 'text-text-muted']">
            {{ booking.when }}
          </td>
          <td :class="tableCellClasses">
            {{ booking.service }}
          </td>
          <td :class="tableCellClasses">
            <StatusPill :tone="booking.tone">
              {{ booking.state }}
            </StatusPill>
          </td>
          <td :class="[tableCellClasses, 'text-right tabular-nums']">
            {{ booking.total }}
          </td>
        </tr>
      </DataTable>
    </div>
  </div>
</template>

<script setup lang="ts">
// A list and a table of the same three bookings, with the pill in place in
// both. None of this is real: there is no bookings API and no screen behind
// these rows yet.
import DataTable from '@/components/ui/DataTable.vue'
import ListRow from '@/components/ui/ListRow.vue'
import StatusPill, { type PillTone } from '@/components/ui/StatusPill.vue'
import { tableCellClasses, tableRowClasses, type TableColumn } from '@/components/ui/table'

interface Booking {
  key: string
  initials: string
  client: string
  when: string
  service: string
  total: string
  state: string
  tone: PillTone
}

const bookings: Booking[] = [
  { key: 'hw', initials: 'HW', client: 'Hannah Whitfield', when: '14 Jun, 6:30am', service: 'Bridal, party of six', total: '£840.00', state: 'Confirmed', tone: 'success' },
  { key: 'pr', initials: 'PR', client: 'Priya Raman', when: '21 Jun, 9:00am', service: 'Trial only', total: '£95.00', state: 'Deposit due', tone: 'warning' },
  { key: 'ab', initials: 'AB', client: 'Aoife Byrne', when: '2 Aug, 7:00am', service: 'Bridal, party of three', total: '£0.00', state: 'Cancelled', tone: 'danger' },
]

const columns: TableColumn[] = [
  { key: 'client', label: 'Client' },
  { key: 'when', label: 'Date' },
  { key: 'service', label: 'Service' },
  { key: 'state', label: 'Status' },
  { key: 'total', label: 'Total', align: 'end' },
]
</script>
