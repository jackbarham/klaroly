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

    <!--
      TEMPORARY: the tab bar trial. It sits here because the bar being trialled
      is on this same screen, so a treatment is judged the instant it is
      chosen rather than after a navigation.

      Its strings are literals rather than locale keys, which /kitchen-sink
      does too and for the same reason: the block is deleted whole, and keys
      would spread that deletion across the locale file as well. Every string
      on this page that is staying is a key.
    -->
    <Card class="mt-6">
      <FormSection
        title="Tab bar trial"
        description="Temporary. Changes the bar at the bottom of this screen straight away, and is remembered on this device."
      >
        <FormField
          v-slot="field"
          label="Treatment"
        >
          <RadioGroup
            v-bind="field"
            :model-value="tabBarStyle"
            :options="tabBarStyleOptions"
            @update:model-value="chooseTabBarStyle"
          />
        </FormField>
      </FormSection>
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
// TEMPORARY, the tab bar trial. Goes with the block at the bottom of the
// template. See src/lib/tabBarTrial.ts.
import { setTabBarStyle, tabBarStyle, tabBarStyleOptions, type TabBarStyle } from '@/lib/tabBarTrial'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

// TEMPORARY, the tab bar trial. The cast is sound because the only values the
// group can emit are the ones tabBarStyleOptions put in it.
function chooseTabBarStyle(value: string): void {
  setTabBarStyle(value as TabBarStyle)
}

async function signOut(): Promise<void> {
  await auth.signOut()
  await router.push({ name: 'login' })
}
</script>
