<template>
  <nav
    class="w-56 shrink-0"
    :aria-label="t('settings.title')"
  >
    <ul class="space-y-2">
      <li
        v-for="group in settingsGroups"
        :key="group.key"
      >
        <RouterLink
          class="flex items-center rounded-control px-4 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus"
          :class="route.name === group.routeName ? 'bg-surface-sunken font-medium text-accent-text' : 'text-text hover:bg-surface-sunken'"
          :to="{ name: group.routeName }"
          :aria-current="route.name === group.routeName ? 'page' : undefined"
        >
          {{ t(group.labelKey) }}
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>

<script setup lang="ts">
// The column of settings links beside a settings page on a wide screen. On a
// phone there is no room for it: the settings index is the list instead.
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { settingsGroups } from '@/lib/navigation'

const { t } = useI18n()
const route = useRoute()
</script>
