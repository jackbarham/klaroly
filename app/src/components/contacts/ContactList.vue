<template>
  <div>
    <!--
      Two empty states, and which one it is matters. Nobody at all is a new
      account and the useful thing to do is add somebody. Nobody matching is a
      query that is too narrow, and the useful thing to do is change it, which
      is why that one carries no action: a button here would take you away from
      the field you were typing in.
    -->
    <EmptyState
      v-if="groups.length === 0"
      icon="contacts"
      :text="filtering ? t('contacts.list.no_matches') : t('contacts.list.empty')"
    >
      <template
        v-if="!filtering"
        #action
      >
        <AppButton @click="onCreate">
          {{ t('create.contact') }}
        </AppButton>
      </template>
    </EmptyState>

    <!--
      A listbox rather than a ul, because the filter field arrows through these
      while keeping focus and points at one of them with aria-activedescendant,
      which is what that pattern needs on this end.

      Each band is its own group, and that is not only for the ARIA: a sticky
      element is bounded by its containing block, so in one flat list every
      heading pins to the same line at once and This year, Last year and 2024
      end up stacked on top of each other with only paint order deciding which
      one you can see. Bounded by its own band, a heading is pushed out by the
      next one the way a sticky heading should be. The group's name comes from
      the heading it already has.
    -->
    <div
      v-else
      :id="listId"
      role="listbox"
      :aria-label="t('contacts.list.label')"
    >
      <div
        v-for="group in groups"
        :key="group.key"
        role="group"
        :aria-labelledby="headingId(group.key)"
      >
        <h2
          :id="headingId(group.key)"
          class="sticky stick-top z-2 border-y border-border bg-surface-sunken px-6 py-2 text-caption font-medium tracking-wide text-text-muted uppercase @split:px-4"
        >
          {{ group.labelKey ? t(group.labelKey) : group.labelText }}
        </h2>

        <ContactRow
          v-for="contact in group.contacts"
          :key="contact.id"
          :contact="contact"
          :lead-with="leadWith"
          :show-initials="showInitials"
          :show-amounts="showAmounts"
          :today="today"
          :option-id="optionId(contact.id)"
          :active="activeId === optionId(contact.id)"
          :current="selectedId === contact.id"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// The list half of the screen: the contacts that survived the filter, in the
// groups the sort put them in.
//
// It does no sorting, grouping or filtering of its own. All of that is plain
// functions in src/lib/contactList.ts and the screen hands the result down, so
// each rule can be tested without mounting anything and this component stays
// about what a group of rows looks like.
import { useI18n } from 'vue-i18n'
import ContactRow from '@/components/contacts/ContactRow.vue'
import type { ContactGroup } from '@/lib/contactList'
import type { LeadWith } from '@/lib/contactView'

const props = defineProps<{
  groups: ContactGroup[]
  leadWith: LeadWith
  showInitials: boolean
  showAmounts: boolean
  today: Date
  // The listbox's own id, which the filter field points at with aria-controls,
  // and the stem every option id is built from.
  listId: string
  // Where the keyboard cursor is, as an option id, or null when there is none.
  activeId: string | null
  // Whose card is open beside the list, which is a different thing.
  selectedId: number | null
  // Whether the filter box has anything in it, which decides which of the two
  // empty states is the true one.
  filtering: boolean
}>()

const { t } = useI18n()

// Both ids are built from the listbox's own, so the filter field can work out
// the id of the row it is pointing at without this component telling it.
function optionId(id: number): string {
  return `${props.listId}-contact-${id}`
}

function headingId(key: string): string {
  return `${props.listId}-group-${key}`
}

function onCreate(): void {
  // TODO: adding a contact is wired up by the prompt that builds the create
  // flow, the same one that wires up the three rows of CreateMenu.
}
</script>
