<template>
  <div>
    <PageHeader
      :title="t('settings.title')"
      :back-to="{ name: 'more' }"
    />

    <Card>
      <ul class="-my-2 divide-y divide-border">
        <li
          v-for="group in settingsGroups"
          :key="group.key"
        >
          <RouterLink
            class="flex items-center justify-between gap-4 py-4 text-text-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus"
            :to="{ name: group.routeName }"
          >
            {{ t(group.labelKey) }}
            <Icon
              name="chevron-right"
              class="h-5 w-5 text-text-subtle"
            />
          </RouterLink>
        </li>
      </ul>
    </Card>
  </div>
</template>

<script setup lang="ts">
// The settings index. It is a list of links and it always will be, which is
// why it is a page of its own rather than a placeholder.
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Icon from '@/components/ui/Icon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { settingsGroups } from '@/lib/navigation'

const { t } = useI18n()
</script>
