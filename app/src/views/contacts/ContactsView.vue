<template>
  <div class="@container">
    <PageHeader
      :title="t('contacts.title')"
      :description="t('contacts.description')"
    />

    <p
      v-if="contacts.status === 'failed'"
      class="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-border bg-surface-raised p-4 text-body text-text"
      role="status"
    >
      {{ t('contacts.failed') }}
      <AppButton
        variant="secondary"
        size="small"
        :pending="retrying"
        @click="onRetry"
      >
        {{ t('contacts.retry') }}
      </AppButton>
    </p>

    <!--
      One layout, switched on a container query at --container-split, and never
      on a media query. The container is <main>, which is the viewport minus
      the sidebar once the sidebar appears, so what decides the shape is how
      much room this screen actually has rather than how wide the window is.

      Below the split the two columns are one at a time: the list is the screen
      until a row is tapped, and then the detail is. Above it they are side by
      side. Both states are done by hiding one column rather than by a second
      route or a second component, so a deep link, a reload and the back button
      all land in the same place at either width.
    -->
    <div class="@split:flex @split:items-start @split:gap-6">
      <!--
        A fixed width, not a fraction: a breakpoint says which layout is live
        and never how big anything is. 400 rather than 360, and the difference
        was measured rather than judged. At 360 three of the twenty-two rows in
        the demo account cut their second line, the worst by 32 pixels, because
        a pill on the right takes about ninety of them; at 380 one row is still
        12 pixels short; at 400 nothing truncates at all. It costs the detail
        column forty pixels, which at the narrowest split, an 834px tablet with
        no sidebar, leaves it 362 and still comfortable for a reach row and a
        pair of chips.
      -->
      <div
        class="-mx-6 min-w-0 @split:mx-0 @split:w-100 @split:shrink-0"
        :class="detailOpen ? 'hidden @split:block' : ''"
      >
        <ContactFilterBar
          ref="bar"
          v-model:query="query"
          :list-id="listId"
          :active-id="activeId"
          @move="onMove"
          @choose="onChoose"
        />

        <!--
          The group headings rest under the filter bar rather than behind it.
          The bar is above the list in both layouts, so the offset is the bar's
          own height and there is nothing to branch on; it is measured rather
          than written down twice, because the bar wraps at a narrow width and
          gets taller when it does.
        -->
        <div :style="{ '--stick-offset': `${barHeight}px` }">
          <ContactList
            :groups="groups"
            :lead-with="contacts.settings.leadWith"
            :show-initials="contacts.settings.showInitials"
            :show-amounts="contacts.settings.showAmounts"
            :today="today"
            :list-id="listId"
            :active-id="activeId"
            :selected-id="selectedId"
            :filtering="query.trim() !== ''"
          />
        </div>
      </div>

      <!--
        There is one document scroll container on this screen, as there is
        everywhere else in the app. The detail is sticky with align-self start
        rather than a column with its own overflow, so the list scrolls and the
        card stays put with no height worked out from the viewport.

        It stops being sticky when it does not fit, and that is not a tidy-up.
        A sticky element taller than the window pins its own top and puts its
        own bottom out of reach: the page scroll is moving the list, and the
        card is pinned to the top of it, so there is nothing left that would
        bring the last of it into view. That is a tablet in landscape rather
        than a freak size. With `detailFits` false the card is static again and
        scrolls with the document, which is what a card longer than the screen
        should do.

        It is a boolean, not a measurement that turns into a size. Nothing here
        is ever given a height worked out from the viewport, which is the thing
        decision 201 rules out and the thing that fights Capacitor.
      -->
      <div
        ref="detailCol"
        class="min-w-0 grow"
        :class="[
          detailOpen ? '' : 'hidden @split:block',
          detailFits ? '@split:sticky @split:self-start @split:stick-top' : '',
        ]"
      >
        <RouterView />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Contacts: a list and one person's card, two views of one set of people on
// one screen.
//
// This component owns the three things both halves need, the filter query,
// the keyboard cursor and today, and the one thing neither can own, where the
// group headings come to rest.
//
// Nothing is preselected. Arriving at /contacts shows the list with an empty
// card beside it, because selecting the first row would put a real person's
// number on screen because of an accident of sort order, and would leave the
// address bar disagreeing with what is being shown.
import { computed, onBeforeUnmount, onMounted, ref, useId, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterView, useRoute, useRouter } from 'vue-router'
import ContactFilterBar from '@/components/contacts/ContactFilterBar.vue'
import ContactList from '@/components/contacts/ContactList.vue'
import { groupContacts, matches } from '@/lib/contactList'
import { useContactsStore } from '@/stores/contacts'

const { t } = useI18n()
const contacts = useContactsStore()
const route = useRoute()
const router = useRouter()

// Read once, here, rather than calling new Date() in six places, so that every
// part of the screen agrees about what today is even across midnight.
const today = ref(new Date())

const query = ref('')
const listId = useId()

onMounted(() => {
  contacts.load()
})

const visible = computed(() => contacts.contacts.filter((contact) => matches(contact, query.value)))

const groups = computed(() => groupContacts(visible.value, contacts.settings.sort, today.value))

// The same people again, flat, which is the order the arrow keys walk. It
// comes from the groups rather than from `visible` so that the cursor moves in
// the order the rows are actually drawn in.
const ordered = computed(() => groups.value.flatMap((group) => group.contacts))

const detailOpen = computed(() => typeof route.params.id === 'string' && route.params.id !== '')

const selectedId = computed(() => {
  const id = Number(route.params.id)

  return Number.isFinite(id) && id > 0 ? id : null
})

// -- The keyboard cursor ----------------------------------------------------
//
// Minus one is no cursor, which is where it starts and where it goes back to
// whenever the list underneath changes. A cursor left on index three while the
// query narrows the list to two is a cursor pointing at somebody else.
const activeIndex = ref(-1)

const activeId = computed(() => {
  const contact = ordered.value[activeIndex.value]

  return contact ? `${listId}-contact-${contact.id}` : null
})

watch([query, () => contacts.settings.sort, () => contacts.contacts], () => {
  activeIndex.value = -1
})

function onMove(delta: number): void {
  const count = ordered.value.length

  if (count === 0) {
    return
  }

  // From nothing, either direction lands on the first row: a person who
  // presses up before down is asking to get into the list, not to wrap round
  // to the bottom of it.
  const next = activeIndex.value === -1 ? 0 : activeIndex.value + delta

  activeIndex.value = Math.min(Math.max(next, 0), count - 1)

  requestAnimationFrame(() => {
    const id = activeId.value

    if (id) {
      // block: nearest, so a row already on screen is left where it is rather
      // than the page jumping to centre it on every press.
      document.getElementById(id)?.scrollIntoView({ block: 'nearest' })
    }
  })
}

function onChoose(): void {
  const contact = ordered.value[activeIndex.value]

  if (contact) {
    router.push({ name: 'contact', params: { id: contact.id } })
  }
}

// -- Where the group headings come to rest ----------------------------------
//
// A ResizeObserver rather than a watcher, because the bar's height moves for
// reasons this component does not initiate: the button wrapping under the
// field at a narrow width, and the browser's own text size.
const bar = useTemplateRef<{ $el: HTMLElement } | null>('bar')
const barHeight = ref(0)

// Whether the card fits in the window, which is the only thing this screen
// asks the viewport. See the comment on the detail column.
const detailCol = useTemplateRef<HTMLElement>('detailCol')
const detailFits = ref(true)

let sizes: ResizeObserver | null = null

function measure(): void {
  const barElement = bar.value?.$el

  if (barElement instanceof HTMLElement) {
    barHeight.value = Math.round(barElement.getBoundingClientRect().height)
  }

  const card = detailCol.value

  if (card) {
    // offsetHeight rather than the bounding rectangle, because a sticky
    // element that is currently pinned still reports its whole height here and
    // the answer must not depend on where the page happens to be scrolled to.
    // Toggling stickiness does not change this number, so the two cannot
    // chase each other.
    detailFits.value = card.offsetHeight <= window.innerHeight
  }
}

onMounted(() => {
  measure()

  sizes = new ResizeObserver(measure)

  const barElement = bar.value?.$el

  if (barElement instanceof HTMLElement) {
    sizes.observe(barElement)
  }

  if (detailCol.value) {
    sizes.observe(detailCol.value)
  }

  // A ResizeObserver sees the card change and not the window, and the window
  // is half of the comparison.
  window.addEventListener('resize', measure, { passive: true })
})

onBeforeUnmount(() => {
  sizes?.disconnect()
  sizes = null
  window.removeEventListener('resize', measure)
})

const retrying = ref(false)

async function onRetry(): Promise<void> {
  retrying.value = true

  try {
    await contacts.retry()
  } finally {
    retrying.value = false
  }
}
</script>
