<template>
  <div>
    <PageHeader :title="t('more.title')" />

    <Card>
      <ul class="-my-2 divide-y divide-border">
        <li
          v-for="item in moreItems"
          :key="item.key"
        >
          <RouterLink
            class="flex items-center justify-between gap-4 py-4 text-text-strong focus-visible:focus-ring"
            :to="{ name: item.routeName }"
          >
            <span class="flex items-center gap-4">
              <Icon
                :name="item.icon"
                class="h-5 w-5 text-text-muted"
              />
              {{ t(item.labelKey) }}
            </span>
            <Icon
              name="chevron-right"
              class="h-5 w-5 text-text-subtle"
            />
          </RouterLink>
        </li>
        <li>
          <button
            class="flex w-full items-center gap-4 py-4 text-left text-text-strong focus-visible:focus-ring"
            type="button"
            @click="signOut"
          >
            <Icon
              name="sign-out"
              class="h-5 w-5 text-text-muted"
            />
            {{ t('auth.sign_out_action') }}
          </button>
        </li>
      </ul>
    </Card>
  </div>
</template>

<script setup lang="ts">
// The phone's overflow: the sections the tab bar has no room for, and the
// way out. The list comes from the navigation config, so a new section
// appears here without this file changing.
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { moreItems } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

async function signOut(): Promise<void> {
  await auth.signOut()
  await router.push({ name: 'login' })
}
</script>
