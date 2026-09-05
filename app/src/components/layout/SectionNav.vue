<template>
  <!--
    Nothing at all on the section's index, because the index is this list. The
    check lives here rather than in the layout so that a section is one route
    record plus its group array, with no per-section logic anywhere.
  -->
  <nav
    v-if="route.name !== indexRouteName"
    class="w-56 shrink-0"
    :aria-label="t(labelKey)"
  >
    <ul class="space-y-2">
      <li
        v-for="group in groups"
        :key="group.key"
      >
        <RouterLink
          class="flex items-center rounded-control px-4 py-2 text-sm transition-colors focus-visible:focus-ring"
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
// The column of links beside a page in a section that is a list of pages, on
// a wide screen. On a phone there is no room for it and the section's index
// is the list instead.
//
// Settings and My account are the same shape, so this is one component given
// a different array. A label key rather than a translated label, because the
// group arrays in navigation.ts already hold keys and the route record that
// names this section holds one too.
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import type { SectionGroup } from '@/lib/navigation'

defineProps<{
  groups: SectionGroup[]
  indexRouteName: string
  labelKey: string
}>()

const { t } = useI18n()
const route = useRoute()
</script>
