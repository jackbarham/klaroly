<template>
  <div>
    <header class="mb-8 space-y-2">
      <h1 class="text-2xl font-semibold text-neutral-900">
        Kitchen sink
      </h1>
      <p class="text-neutral-500">
        Every component the app owns, in every variant and state it supports,
        on one page. It is a development page: nothing links to it, it makes
        no requests, and it is removed before launch.
      </p>
    </header>

    <!--
      The index and the theme control stay put, because this page is long and
      is meant to be scrolled. The negative margin lets the bar reach the
      edges of the column, which has px-6 from the layout.
    -->
    <div class="sticky top-0 z-10 -mx-6 mb-12 flex flex-wrap items-center gap-4 border-b border-neutral-200 bg-surface px-6 py-4">
      <nav
        class="min-w-0 sm:flex-1"
        aria-label="Sections"
      >
        <ul class="flex flex-wrap gap-x-4 gap-y-2">
          <li
            v-for="section in sections"
            :key="section.id"
          >
            <a
              class="text-sm text-neutral-700 underline hover:text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-900"
              :href="`#${section.id}`"
            >{{ section.title }}</a>
          </li>
        </ul>
      </nav>

      <div class="ml-auto">
        <AppButton
          variant="secondary"
          size="small"
          @click="toggleTheme"
        >
          {{ dark ? 'Dark theme on, inert' : 'Dark theme off, inert' }}
        </AppButton>
      </div>
    </div>

    <div class="space-y-16">
      <!--
        The key carries the theme, so every section is built again when the
        theme changes and the tokens section reads the new values back.
      -->
      <KsSection
        v-for="section in sections"
        :id="section.id"
        :key="`${section.id}-${theme}`"
        :title="section.title"
        :note="section.note"
      >
        <component :is="section.component" />
      </KsSection>
    </div>
  </div>
</template>

<script setup lang="ts">
// The whole UI kit and form kit on one route, so that a change to a token
// can be judged in one place and a new component can be reviewed without
// hunting for a screen that happens to use it.
//
// This is the one page in the app whose strings are written inline rather
// than kept in src/locales/en-GB.json. Everything on it is demo content, it
// is deleted before launch, and two hundred keys for labels such as "Client
// name" would sit in the locale file forever.
//
// The sections are listed once, as data, so the index at the top and the
// page itself cannot drift apart.
import { computed, onBeforeUnmount, ref, type Component } from 'vue'
import AppButton from '@/components/ui/AppButton.vue'
import KsButtons from '@/views/kitchen-sink/KsButtons.vue'
import KsCards from '@/views/kitchen-sink/KsCards.vue'
import KsForm from '@/views/kitchen-sink/KsForm.vue'
import KsHeaders from '@/views/kitchen-sink/KsHeaders.vue'
import KsIcons from '@/views/kitchen-sink/KsIcons.vue'
import KsOverlays from '@/views/kitchen-sink/KsOverlays.vue'
import KsSection from '@/views/kitchen-sink/KsSection.vue'
import KsShell from '@/views/kitchen-sink/KsShell.vue'
import KsTokens from '@/views/kitchen-sink/KsTokens.vue'

interface Section {
  id: string
  title: string
  note?: string
  component: Component
}

const sections: Section[] = [
  {
    id: 'tokens',
    title: 'Tokens',
    note: 'The raw material. Every value below is read back from the stylesheet or measured from the sample beside it, so nothing here can go stale against src/assets/app.css.',
    component: KsTokens,
  },
  {
    id: 'buttons',
    title: 'AppButton',
    note: 'Pending disables the button and says so with aria-busy. Nothing in the app draws a spinner.',
    component: KsButtons,
  },
  {
    id: 'icons',
    title: 'IconButton and Icon',
    component: KsIcons,
  },
  {
    id: 'form',
    title: 'The form kit',
    note: 'Every control with a label, with a hint and with an error, so that FormField putting the hint and the error into aria-describedby, and aria-invalid on the control, can be seen rather than taken on trust.',
    component: KsForm,
  },
  {
    id: 'page-header',
    title: 'PageHeader',
    note: 'Each example brings its own h1, so this page has more than one. That is the price of showing the component instead of drawing it again.',
    component: KsHeaders,
  },
  {
    id: 'cards',
    title: 'Card and EmptyState',
    component: KsCards,
  },
  {
    id: 'overlays',
    title: 'Sheet and CreateMenu',
    component: KsOverlays,
  },
  {
    id: 'shell',
    title: 'The shell pieces',
    component: KsShell,
  },
]

// The dark class does nothing today: the app has one theme. The control is
// here because the restyle brings a second one, and on the day it lands this
// page has to be able to show both without being edited.
const dark = ref(false)

const theme = computed(() => (dark.value ? 'dark' : 'light'))

function toggleTheme(): void {
  dark.value = !dark.value
  document.documentElement.classList.toggle('dark', dark.value)
}

// The class is on the html element, which outlives this page, so leaving the
// kitchen sink takes it off again.
onBeforeUnmount(() => {
  document.documentElement.classList.remove('dark')
})
</script>
