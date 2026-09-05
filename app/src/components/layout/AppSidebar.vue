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

    <ul class="mt-8 space-y-1">
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
            :class="[iconClasses, isCurrent(item) ? '' : iconIdleClasses]"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </li>
    </ul>

    <hr class="my-6 border-t border-border">

    <ul class="space-y-1">
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
            :class="[iconClasses, isCurrent(item) ? '' : iconIdleClasses]"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </li>
    </ul>

    <!--
      Who is signed in, and the way out. The row is h-10 and the column's
      padding below it is 6, which is the arithmetic the menu above it is
      positioned from. See Sheet.vue.
    -->
    <div class="mt-auto pt-6">
      <button
        class="flex h-10 w-full items-center gap-3 rounded-control px-4 text-body font-medium text-text-strong hover:bg-surface-sunken focus-visible:focus-ring"
        type="button"
        aria-haspopup="dialog"
        :aria-expanded="accountOpen"
        @click="accountOpen = true"
      >
        <span class="grow truncate text-left">{{ accountName }}</span>
        <Icon
          name="more"
          :class="[iconClasses, iconIdleClasses]"
        />
      </button>
    </div>

    <AccountMenu v-model:open="accountOpen" />
  </nav>
</template>

<script setup lang="ts">
// The sidebar builds itself from the navigation config; it has no list of
// its own. It does not link to More, because More exists only to reach the
// sections a phone's tab bar cannot show and every one of them is here.
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AccountMenu from '@/components/layout/AccountMenu.vue'
import { createItem, sectionKey, sidebarMain, sidebarSecondary, type Destination } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const accountOpen = ref(false)

// The business name, because that is what the person thinks of this account
// as. Their own name is the fallback for an account that has not been given
// one, and the empty string keeps the row from collapsing before the first
// GET /api/me answers.
const accountName = computed(() => auth.me?.account.name || auth.me?.user.name || '')

// The weight does not change when a row becomes the current one: only the
// fill and the colour do. A label that thickens on the way past is a label
// that moves.
const linkClasses = 'flex h-10 items-center gap-3 rounded-control px-4 text-body font-medium transition-colors focus-visible:focus-ring'
const currentClasses = 'bg-surface-sunken text-accent-text'
const idleClasses = 'text-text hover:bg-surface-sunken hover:text-accent-text'

// The icon is quieter than its label, and takes the accent only on the current
// row. It deliberately does not follow the label on hover: with the fill and
// the accent label now on both, the icon is the one thing left that says which
// row you are actually on rather than which one you are pointing at. It is a lighter token
// rather than an opacity, because half opacity is how this app says
// unavailable.
//
// text-placeholder is the lightest step on the grey ramp and is named for the
// text inside an empty control, which is not what this is. It is used here
// because it is the value the column wants and nothing else on the ramp is
// that light; a token named for a quiet icon would be the better home for it.
const iconClasses = 'h-6 w-6'
const iconIdleClasses = 'text-text-placeholder'

// A booking's page marks Bookings, a settings group marks Settings, and so
// on, which is what sectionKey works out.
function isCurrent(item: Destination): boolean {
  return sectionKey(route.name) === item.key
}
</script>
