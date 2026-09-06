<template>
  <EmptyState
    icon="contacts"
    :text="t('contacts.detail.choose')"
  />
</template>

<script setup lang="ts">
// What sits in the detail column when nobody is chosen.
//
// It is a real route rather than a fallback inside the parent's RouterView,
// because /contacts is a place you can be: it is what the navigation links to,
// what a bookmark holds and what deleting somebody returns you to. A parent
// deciding to draw something when its child is missing would give that state
// no name.
//
// Below the split this is never seen: the list is the whole screen there, and
// the column holding this is hidden until a row is tapped.
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
