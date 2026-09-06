<template>
  <EmptyState
    icon="enquiries"
    :text="t('enquiries.detail.choose')"
  />
</template>

<script setup lang="ts">
// What sits in the detail column when nobody is chosen.
//
// A real route rather than a fallback inside the parent's RouterView, for the
// reason ContactsIndexView gives: /enquiries is a place you can be, and a
// parent deciding to draw something when its child is missing would give that
// state no name.
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>
