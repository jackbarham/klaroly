import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { loadContacts } from '@/lib/contactFixtures'
import { defaultSettings, readSettings, writeSettings, type ViewSettings } from '@/lib/contactView'
import type { Contact } from '@/types/contacts'

// The contacts the screen draws, and how this device likes to read them.
// Components read this store and never call the data layer themselves.
//
// One payload, no pagination, no infinite scroll and no virtualisation. Two
// hundred contacts after five years of trading is about fifty kilobytes, so
// the list arrives complete and every sort, group and filter happens in the
// browser with nothing going over the wire. That is what lets the filter box
// be instant and have no debounce, no spinner and no page size: there is
// nothing to wait for. If the list ever does outgrow one payload, the thing to
// change is this store and not the screen.

export type ContactsStatus = 'idle' | 'loading' | 'ready' | 'failed'

export const useContactsStore = defineStore('contacts', () => {
  const contacts = ref<Contact[]>([])
  const status = ref<ContactsStatus>('idle')

  // Read once, when the store is created, so the first render is already in
  // the shape this person left it in and no setting is seen to change after
  // the list has drawn. readSettings copes with a storage that throws.
  const settings = ref<ViewSettings>(readSettings())

  /**
   * Fetches once. Coming back to Contacts from another tab does not refetch,
   * and two components mounting together do not fetch twice.
   */
  async function load(): Promise<void> {
    if (status.value === 'loading' || status.value === 'ready') {
      return
    }

    status.value = 'loading'

    try {
      contacts.value = await loadContacts()
      status.value = 'ready'
    } catch {
      status.value = 'failed'
    }
  }

  async function retry(): Promise<void> {
    status.value = 'idle'

    await load()
  }

  const byId = computed(() => new Map(contacts.value.map((contact) => [contact.id, contact])))

  function find(id: number): Contact | null {
    return byId.value.get(id) ?? null
  }

  // A patch rather than a whole object, because the menu changes one setting
  // at a time and stays open while it does, so the list redraws underneath and
  // the setting is judged by its effect rather than by its name.
  function update(patch: Partial<ViewSettings>): void {
    settings.value = { ...settings.value, ...patch }

    writeSettings(settings.value)
  }

  function reset(): void {
    settings.value = { ...defaultSettings }

    writeSettings(settings.value)
  }

  // Local only, until there is an endpoint to call. It is here rather than in
  // the screen so that the list and the detail cannot disagree about who
  // exists, and so the day this becomes a request the change is one function.
  function remove(id: number): void {
    contacts.value = contacts.value.filter((contact) => contact.id !== id)
  }

  return {
    contacts,
    status,
    settings,
    load,
    retry,
    find,
    update,
    reset,
    remove,
  }
})
