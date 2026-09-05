<template>
  <!--
    The table scrolls inside its own box. A wide table is normal on a phone and
    the page itself must never scroll sideways.
  -->
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-body">
      <thead>
        <!--
          A header cell is regular weight and muted, not bold. The header row
          carries a divider and no fill.
        -->
        <tr class="border-b border-border text-left text-text-muted">
          <th
            v-for="column in columns"
            :key="column.key"
            class="py-3 pr-row font-normal last:pr-0"
            :class="column.align === 'end' ? 'text-right' : ''"
            scope="col"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <slot />
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
// The wide-screen form of a list. It is the same data as ListRow and it
// follows the same rules: a hairline between rows, and a hover that recolours
// that hairline rather than filling anything.
//
// The component owns the box, the table and the header. Rows are the caller's,
// written as ordinary tr and td with the class strings from table.ts, because
// what goes in a cell is a pill, a figure or a name and no prop shape would
// cover all three.
//
// A row that can be clicked puts a link in its first cell and the link takes
// the focus ring. A whole row that is one link is ListRow's job.
import type { TableColumn } from '@/components/ui/table'

defineProps<{
  columns: TableColumn[]
}>()
</script>
