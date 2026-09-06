<template>
  <header class="mb-8">
    <!--
      The back link is for the phone, where a section such as a settings group
      is a page of its own reached from a list. On a wide screen the sidebar
      and the settings column are always visible, so there is nothing to go
      back to and the link is hidden.
    -->
    <RouterLink
      v-if="backTo"
      class="mb-4 inline-flex items-center gap-1 text-sm text-text-muted transition-colors hover:text-text-strong focus-visible:focus-ring lg:hidden"
      :to="backTo"
    >
      <Icon
        name="chevron-left"
        class="h-4 w-4"
      />
      {{ t('common.back') }}
    </RouterLink>

    <div class="flex items-start justify-between gap-4">
      <div class="space-y-2">
        <!--
          On a phone the top bar already says which screen this is, so the
          heading would say it again one line down and give the page two
          titles. It goes sr-only rather than hidden: it leaves the screen and
          stays in the accessibility tree, so every route still has exactly one
          h1 in it and the bar can be an ordinary line of text rather than a
          second competing heading.
        -->
        <h1 class="text-2xl font-semibold text-text-strong max-lg:sr-only">
          {{ title }}
        </h1>
        <p
          v-if="description"
          class="text-text-muted"
        >
          {{ description }}
        </p>
      </div>
      <div
        v-if="$slots.actions"
        class="flex shrink-0 items-center gap-2"
      >
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
// The top of every page: the heading a screen reader lands on, an optional
// line of explanation, an optional row of actions and, on a phone, an
// optional way back to wherever this page was reached from.
//
// The heading is the one thing that differs by width, because the phone has a
// top bar saying the same words. See the comment on the h1.
import { useI18n } from 'vue-i18n'
import { RouterLink, type RouteLocationRaw } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'

defineProps<{
  title: string
  description?: string
  backTo?: RouteLocationRaw
}>()

const { t } = useI18n()
</script>
