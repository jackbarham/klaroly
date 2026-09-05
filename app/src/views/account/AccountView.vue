<template>
  <div>
    <PageHeader
      :title="t('account.title')"
      :description="t('account.description')"
      :back-to="{ name: 'more' }"
    />

    <Card>
      <ul class="-my-2 divide-y divide-border">
        <li
          v-for="group in accountGroups"
          :key="group.key"
        >
          <RouterLink
            class="flex items-center justify-between gap-4 py-4 text-text-strong focus-visible:focus-ring"
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
// The My account index. It is a list of links and it always will be, which is
// why it is a page of its own rather than a placeholder. The same four
// entries are the column beside each page on a wide screen, because both come
// from accountGroups.
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { accountGroups } from '@/lib/navigation'

const { t } = useI18n()
</script>
