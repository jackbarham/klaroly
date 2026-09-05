<template>
  <div>
    <header class="mb-8 space-y-2">
      <h1 class="text-2xl font-semibold text-text-strong">
        Kitchen sink
      </h1>
      <p class="text-text-muted">
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
    <div class="sticky top-0 z-10 -mx-6 mb-12 flex flex-wrap items-center gap-4 border-b border-border bg-surface px-6 py-4">
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
              class="text-sm text-text underline hover:text-text-strong focus-visible:focus-ring"
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
          {{ dark ? 'Switch to light' : 'Switch to dark' }}
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
import KsBands from '@/views/kitchen-sink/KsBands.vue'
import KsButtons from '@/views/kitchen-sink/KsButtons.vue'
import KsCards from '@/views/kitchen-sink/KsCards.vue'
import KsForm from '@/views/kitchen-sink/KsForm.vue'
import KsHeaders from '@/views/kitchen-sink/KsHeaders.vue'
import KsIcons from '@/views/kitchen-sink/KsIcons.vue'
import KsOverlays from '@/views/kitchen-sink/KsOverlays.vue'
import KsPills from '@/views/kitchen-sink/KsPills.vue'
import KsRadioCards from '@/views/kitchen-sink/KsRadioCards.vue'
import KsRows from '@/views/kitchen-sink/KsRows.vue'
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
    note: 'Pending is still disabled and still says so with aria-busy, but it is not dimmed: half opacity is how this app says unavailable, and a button doing what it was asked is not unavailable. It draws a spinner instead, which stops moving under prefers-reduced-motion. Each disabled row sits directly above its pending twin.',
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
    id: 'radio-cards',
    title: 'RadioCard',
    note: 'The one control whose focus is a ring rather than a recoloured edge, because its edge is already saying whether it is selected.',
    component: KsRadioCards,
  },
  {
    id: 'page-header',
    title: 'PageHeader',
    note: 'Each example brings its own h1, so this page has more than one. That is the price of showing the component instead of drawing it again.',
    component: KsHeaders,
  },
  {
    id: 'cards',
    title: 'Card, EmptyState, and the thing to use instead of a card',
    note: 'A card is for a group that genuinely needs lifting off the page. The default is a heading and a hairline.',
    component: KsCards,
  },
  {
    id: 'pills',
    title: 'StatusPill',
    note: 'A tone, not a state. Which booking mark or enquiry stage reads as success and which as neutral is a screen\'s decision, and no screen has made it yet.',
    component: KsPills,
  },
  {
    id: 'rows',
    title: 'ListRow and DataTable',
    note: 'The same data twice: rows on a phone, a table on a wide screen. Both hover by recolouring the hairline they already have.',
    component: KsRows,
  },
  {
    id: 'bands',
    title: 'SectionBand',
    component: KsBands,
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

// The dark class on the html element is what flips the semantic layer. The
// app never sets it yet, so this page is the only place the second theme can
// be seen.
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
