<template>
  <div class="min-h-screen">
    <a
      class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-30 focus:rounded-control focus:bg-accent focus:px-4 focus:py-2 focus:text-text-on-accent"
      href="#main"
    >{{ t('nav.skip_to_content') }}</a>

    <AppSidebar @create="createOpen = true" />

    <AppTopBar @create="createOpen = true" />

    <div class="lg:pl-72">
      <!--
        Two variants rather than one utility overriding another, because the
        two are mutually exclusive and neither then depends on which order
        Tailwind emits them in. The lg half is page-top unchanged: the desktop
        has no top bar and its spacing does not move.
      -->
      <main
        id="main"
        class="mx-auto w-full max-w-4xl px-6 pb-12 max-lg:page-under-bar max-lg:page-bottom lg:page-top"
      >
        <RouterView />
      </main>
    </div>

    <AppTabBar />

    <CreateMenu v-model:open="createOpen" />
  </div>
</template>

<script setup lang="ts">
// The shell every signed-in page sits in: one sidebar for a wide screen, a top
// bar and a tab bar for a phone, one main region and one router view. Which
// navigation shows is decided by Tailwind's lg variant and nothing else, so
// there is no width being watched in JavaScript and no user agent being
// sniffed.
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterView } from 'vue-router'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTabBar from '@/components/layout/AppTabBar.vue'
import AppTopBar from '@/components/layout/AppTopBar.vue'
import CreateMenu from '@/components/layout/CreateMenu.vue'

const { t } = useI18n()

// The create sheet is the shell's only piece of state, so it lives here
// rather than in a store: both triggers are children of this component. On a
// phone that trigger is the top bar's accent button; the tab bar is five
// destinations and opens nothing.
const createOpen = ref(false)
</script>
