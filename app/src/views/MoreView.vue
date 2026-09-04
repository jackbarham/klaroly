<template>
  <div>
    <PageHeader :title="t('more.title')" />

    <Card>
      <ul class="-my-2 divide-y divide-neutral-200">
        <li
          v-for="item in moreItems"
          :key="item.key"
        >
          <RouterLink
            class="flex items-center justify-between gap-4 py-4 text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900"
            :to="{ name: item.routeName }"
          >
            <span class="flex items-center gap-4">
              <Icon
                :name="item.icon"
                class="h-5 w-5 text-neutral-500"
              />
              {{ t(item.labelKey) }}
            </span>
            <Icon
              name="chevron-right"
              class="h-5 w-5 text-neutral-400"
            />
          </RouterLink>
        </li>
        <li>
          <button
            class="flex w-full items-center gap-4 py-4 text-left text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900"
            type="button"
            @click="signOut"
          >
            <Icon
              name="sign-out"
              class="h-5 w-5 text-neutral-500"
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
import Card from '@/components/ui/Card.vue'
import Icon from '@/components/ui/Icon.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
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
