<template>
  <!--
    The desktop navigation. It is a fixed column with labels always showing;
    it does not collapse, and it is not rendered at all below lg, where the
    tab bar takes over.

    The geometry at the top of this column is what the create menu anchors
    itself to: page-top padding, an h-8 wordmark, a gap of 8 and an h-12
    button put the bottom of the New button 136 pixels down. See Sheet.vue.
  -->
  <nav
    class="fixed inset-y-0 left-0 z-10 hidden w-72 flex-col border-r border-border bg-surface page-top px-6 pb-6 lg:flex"
    :aria-label="t('nav.primary_label')"
  >
    <p class="flex h-8 items-center text-lg font-semibold text-text-strong">
      {{ t('app.name') }}
    </p>

    <AppButton
      class="mt-8 w-full"
      variant="primary"
      :icon="createItem.icon"
      @click="emit('create')"
    >
      {{ t(createItem.labelKey) }}
    </AppButton>

    <ul class="mt-8 space-y-2">
      <li
        v-for="item in sidebarMain"
        :key="item.key"
      >
        <RouterLink
          :class="[linkClasses, isCurrent(item) ? currentClasses : idleClasses]"
          :to="{ name: item.routeName }"
          :aria-current="isCurrent(item) ? 'page' : undefined"
        >
          <Icon
            :name="item.icon"
            class="h-5 w-5"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </li>
    </ul>

    <hr class="my-6 border-t border-border">

    <ul class="space-y-2">
      <li
        v-for="item in sidebarSecondary"
        :key="item.key"
      >
        <RouterLink
          :class="[linkClasses, isCurrent(item) ? currentClasses : idleClasses]"
          :to="{ name: item.routeName }"
          :aria-current="isCurrent(item) ? 'page' : undefined"
        >
          <Icon
            :name="item.icon"
            class="h-5 w-5"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </li>
    </ul>

    <div class="mt-auto pt-6">
      <AppButton
        class="w-full justify-start"
        variant="ghost"
        icon="sign-out"
        @click="signOut"
      >
        {{ t('auth.sign_out_action') }}
      </AppButton>
    </div>
  </nav>
</template>

<script setup lang="ts">
// The sidebar builds itself from the navigation config; it has no list of
// its own. It does not link to More, because More exists only to reach the
// sections a phone's tab bar cannot show and every one of them is here.
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AppButton from '@/components/ui/AppButton.vue'
import Icon from '@/components/ui/Icon.vue'
import { createItem, sectionKey, sidebarMain, sidebarSecondary, type Destination } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const linkClasses = 'flex h-12 items-center gap-4 rounded-control px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-border-focus'
const currentClasses = 'bg-surface-sunken font-medium text-accent-text'
const idleClasses = 'text-text hover:bg-surface-sunken'

// A booking's page marks Bookings, a settings group marks Settings, and so
// on, which is what sectionKey works out.
function isCurrent(item: Destination): boolean {
  return sectionKey(route.name) === item.key
}

async function signOut(): Promise<void> {
  await auth.signOut()
  await router.push({ name: 'login' })
}
</script>
