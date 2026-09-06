<template>
  <div>
    <p
      v-if="contacts.status === 'loading'"
      class="py-12 text-center text-body text-text-muted"
      role="status"
    >
      {{ t('contacts.loading') }}
    </p>

    <EmptyState
      v-else-if="!contact"
      icon="contacts"
      :text="t('contacts.detail.not_found')"
    />

    <ContactDetail
      v-else
      :contact="contact"
      @delete="onDelete"
    />

    <ContactNotice
      v-model:open="noticeOpen"
      :message="t('contacts.delete.blocked')"
    />

    <ContactDeleteDialog
      v-model:open="confirmOpen"
      :name="name"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup lang="ts">
// One contact, resolved from the id in the address bar rather than from
// whatever the list happened to have selected, so a deep link and a hard
// reload both land on the right person.
//
// Deleting is decided here rather than inside the card, because what happens
// next is a navigation and a card should not know about the router. The two
// answers are a refusal and a question, and each gets the control that suits
// it: schema 5.7 restricts bookings.contact_id, so somebody with work against
// them cannot be removed, and saying so at the moment Delete is tapped is
// better than a disabled button nobody can explain or an error after the fact.
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import ContactDeleteDialog from '@/components/contacts/ContactDeleteDialog.vue'
import ContactDetail from '@/components/contacts/ContactDetail.vue'
import ContactNotice from '@/components/contacts/ContactNotice.vue'
import { fullName } from '@/lib/contactList'
import { useContactsStore } from '@/stores/contacts'

const { t } = useI18n()
const contacts = useContactsStore()
const route = useRoute()
const router = useRouter()

const contact = computed(() => {
  const id = Number(route.params.id)

  return Number.isFinite(id) ? contacts.find(id) : null
})

const name = computed(() => (contact.value ? fullName(contact.value) : ''))

const noticeOpen = ref(false)
const confirmOpen = ref(false)

function onDelete(): void {
  if (!contact.value) {
    return
  }

  if (contact.value.booking_count > 0) {
    noticeOpen.value = true

    return
  }

  confirmOpen.value = true
}

function onConfirm(): void {
  const current = contact.value

  if (!current) {
    return
  }

  contacts.remove(current.id)
  router.push({ name: 'contacts' })
}
</script>
