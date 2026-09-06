<template>
  <div>
    <!--
      Two empty states, and which one it is matters. Nobody at all is a new
      account and there is nothing useful to do but wait for somebody to ask.
      Nobody matching is a query that is too narrow, and the useful thing is to
      change it, which is why neither carries an action: a button here would
      take you away from the field you were typing in.
    -->
    <EmptyState
      v-if="groups.length === 0"
      icon="enquiries"
      :text="filtering ? t('enquiries.list.no_matches') : t('enquiries.list.empty')"
    />

    <div
      v-else
      :id="listId"
      role="list"
      :aria-label="t('enquiries.list.label')"
      @keydown="onKeydown"
    >
      <!--
        Each band is its own wrapper, and it is not decoration. A sticky
        element is bounded by its containing block, so in one flat list every
        heading pins to the same line at once and Gone quiet, Over a week and
        This week end up stacked on top of each other with only paint order
        deciding which one you can see. Bounded by its own band, a heading is
        pushed out by the next one the way a sticky heading should be.

        The contacts list gets this free from the role="group" its listbox
        needs. This one is a plain list, so the wrapper is here for the layout
        alone and the roles are on the elements that mean something.
      -->
      <div
        v-for="group in groups"
        :key="group.key"
      >
        <h2
          class="sticky stick-top z-2 border-y border-border px-6 py-2 text-caption font-medium tracking-wide uppercase @split:px-4"
          :class="group.cold ? 'bg-warning-subtle text-warning-text' : 'bg-surface-sunken text-text-muted'"
        >
          {{ t(group.labelKey) }}
        </h2>

        <ul role="list">
          <EnquiryRow
            v-for="enquiry in group.enquiries"
            :key="enquiry.id"
            :enquiry="enquiry"
            :today="today"
            :show-source="settings.showSource"
            :show-totals="settings.showTotals"
            :show-clashes="settings.showClashes"
            :row-id="rowId(enquiry.id)"
            :focusable="focusableId === enquiry.id"
            :current="selectedId === enquiry.id"
            @stage="(record, anchor) => emit('stage', record, anchor)"
          />
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// The list half of the screen: the enquiries that survived the filter, in the
// bands the sort put them in.
//
// **This list is not a listbox, and that is the one thing here not to copy
// from ContactList.vue.**
//
// The contacts list can be a listbox because a contacts row holds nothing
// interactive: it is one link wearing role="option", so its filter field can
// be a combobox and arrow a virtual cursor through the rows with
// aria-activedescendant while focus never leaves the input.
//
// Business logic 5.1 puts a control inside this row, the stage pill, and that
// breaks the pattern twice over. An ARIA option is a leaf: it may hold
// phrasing content and not a control, so a button inside one is either
// flattened out of the accessibility tree or stops its parent being an option,
// and which of the two a browser does is not ours to choose. That is the same
// class of problem as a <button> inside a <button>, which is invalid markup
// browsers resolve by dropping one of them (decision 240). And even if the
// markup were legal, aria-activedescendant moves a VIRTUAL cursor: a control
// can only be operated by real focus, so the pattern could never reach the one
// control this screen exists for.
//
// So this is a plain role="list" of rows, each row's main target a real link,
// the pill a real button whose click never reaches it, and a roving tabindex
// moving real focus. The filter field above is an ordinary search input with
// no combobox role and nothing pointing at anything.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EnquiryRow from '@/components/enquiries/EnquiryRow.vue'
import type { EnquiryGroup } from '@/lib/enquiryList'
import type { EnquiryViewSettings } from '@/lib/enquiryView'
import type { Enquiry } from '@/types/enquiries'

const props = defineProps<{
  groups: EnquiryGroup[]
  settings: EnquiryViewSettings
  today: Date
  // The list's own id, and the stem every row id is built from.
  listId: string
  // Whose detail is open beside the list.
  selectedId: number | null
  // Whether the filter box has anything in it, which decides which of the two
  // empty states is the true one.
  filtering: boolean
}>()

const emit = defineEmits<{
  stage: [enquiry: Enquiry, anchor: HTMLElement | null]
}>()

const { t } = useI18n()

const ordered = computed(() => props.groups.flatMap((group) => group.enquiries))

function rowId(id: number): string {
  return `${props.listId}-enquiry-${id}`
}

/**
 * Which row Tab lands on.
 *
 * Exactly one row in the list is reachable by Tab, and the arrow keys move
 * between them from there. Without that, Tab walks every row in a list of
 * forty before reaching anything after it, which on a screen whose whole point
 * is a long list is worse than useless.
 *
 * It is the open row when there is one, so tabbing back into the list after
 * looking at a card returns to that card's row rather than to the top.
 */
const focusableId = computed(() => {
  const open = ordered.value.find((enquiry) => enquiry.id === props.selectedId)

  return open?.id ?? ordered.value[0]?.id ?? null
})

// Arrow keys move real focus, because a control inside a row can only be
// reached by real focus. Home and End are here because a list ordered by
// neglect has a top worth getting back to in one key.
function onKeydown(event: KeyboardEvent): void {
  const keys = ['ArrowDown', 'ArrowUp', 'Home', 'End']

  if (!keys.includes(event.key)) {
    return
  }

  const rows = ordered.value

  if (rows.length === 0) {
    return
  }

  const focused = document.activeElement?.id ?? ''
  const at = rows.findIndex((enquiry) => rowId(enquiry.id) === focused)

  // From outside the rows, either arrow lands on the first: somebody pressing
  // up before down is asking to get into the list rather than to wrap round to
  // the bottom of it.
  const next = event.key === 'Home'
    ? 0
    : event.key === 'End'
      ? rows.length - 1
      : at === -1 ? 0 : at + (event.key === 'ArrowDown' ? 1 : -1)

  const target = rows[Math.min(Math.max(next, 0), rows.length - 1)]

  event.preventDefault()

  const element = document.getElementById(rowId(target.id))

  element?.focus()
  // block: nearest, so a row already on screen is left where it is rather than
  // the page jumping to centre it on every press.
  element?.scrollIntoView({ block: 'nearest' })
}
</script>
