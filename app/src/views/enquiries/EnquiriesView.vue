<template>
  <div class="@container">
    <PageHeader
      :title="t('enquiries.title')"
      :description="t('enquiries.description')"
    />

    <p
      v-if="enquiries.status === 'failed'"
      class="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-border bg-surface-raised p-4 text-body text-text"
      role="status"
    >
      {{ t('enquiries.failed') }}
      <AppButton
        variant="secondary"
        size="small"
        :pending="retrying"
        @click="onRetry"
      >
        {{ t('enquiries.retry') }}
      </AppButton>
    </p>

    <!--
      One layout, switched on a container query at --container-split and never
      on a media query. The container is <main>, which is the viewport minus
      the sidebar once the sidebar appears, so what decides the shape is how
      much room this screen actually has rather than how wide the window is.
      The same rule the contacts screen uses, for the same reason.
    -->
    <div class="@split:flex @split:items-start @split:gap-6">
      <!--
        400px, measured on the contacts screen rather than re-derived here.
        The enquiries prototype ran at 380 and two of its fourteen rows still
        truncated their second line, which is the same answer arriving a second
        time.
      -->
      <div
        class="-mx-6 min-w-0 @split:mx-0 @split:w-100 @split:shrink-0"
        :class="detailOpen ? 'hidden @split:block' : ''"
      >
        <EnquiryFilterBar
          ref="bar"
          v-model:query="query"
        />

        <!--
          The band headings rest under the filter bar rather than behind it.
          The bar is above the list in both layouts, so the offset is the bar's
          own height and there is nothing to branch on; it is measured because
          the bar wraps at a narrow width and gets taller when it does.
        -->
        <div :style="{ '--stick-offset': `${barHeight}px` }">
          <EnquiryList
            :groups="groups"
            :settings="enquiries.settings"
            :today="today"
            :list-id="listId"
            :selected-id="selectedId"
            :filtering="query.trim() !== ''"
            @stage="onStage"
          />
        </div>
      </div>

      <!--
        One document scroll container, as everywhere else. The detail is sticky
        with align-self start rather than a column with its own overflow, so
        the list scrolls and the card stays put with no height worked out from
        the viewport. It stops being sticky when it does not fit, because a
        sticky element taller than the window pins its own top and puts its own
        bottom out of reach.
      -->
      <div
        ref="detailCol"
        class="min-w-0 grow"
        :class="[
          detailOpen ? '' : 'hidden @split:block',
          detailFits ? '@split:sticky @split:self-start @split:stick-top' : '',
        ]"
      >
        <RouterView v-slot="{ Component }">
          <component
            :is="Component"
            :today="today"
            @action="onAction"
          />
        </RouterView>
      </div>
    </div>

    <EnquiryStageSheet
      v-model:open="sheetOpen"
      :enquiry="sheetFor"
      :anchor-to="sheetAnchor"
      @move="onMove"
    />

    <Notice
      v-model:open="noticeOpen"
      :message="notice"
    />
  </div>
</template>

<script setup lang="ts">
// Enquiries: a list and one enquiry's card, two views of one set of records on
// one screen.
//
// This component owns the three things both halves need, the filter query,
// today and the stage sheet, and the one thing neither can own, where the band
// headings come to rest.
//
// The stage sheet is here rather than in the row, because one sheet serves
// every row and the detail's actions as well: a sheet per row would be forty
// teleported panels in the document, and the detail's Convert button would
// need a second copy of it.
//
// Nothing is preselected. Arriving at /enquiries shows the list with an empty
// card beside it, because selecting the first row would open somebody's
// enquiry because of an accident of sort order and leave the address bar
// disagreeing with what is being shown.
import { computed, onBeforeUnmount, onMounted, ref, useId, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterView, useRoute } from 'vue-router'
import EnquiryFilterBar from '@/components/enquiries/EnquiryFilterBar.vue'
import EnquiryList from '@/components/enquiries/EnquiryList.vue'
import EnquiryStageSheet from '@/components/enquiries/EnquiryStageSheet.vue'
import { groupEnquiries, matches } from '@/lib/enquiryList'
import { useEnquiriesStore } from '@/stores/enquiries'
import type { BookingStage } from '@/types/bookings'
import type { Enquiry, LostReason } from '@/types/enquiries'

const { t } = useI18n()
const enquiries = useEnquiriesStore()
const route = useRoute()

// Read once, here, rather than calling new Date() in six places, so that every
// part of the screen agrees about what today is even across midnight.
const today = ref(new Date())

const query = ref('')
const listId = useId()

onMounted(() => {
  enquiries.load()
})

const visible = computed(() => enquiries.enquiries.filter((enquiry) => matches(enquiry, query.value, t)))

const groups = computed(() => groupEnquiries(
  visible.value,
  enquiries.settings.sort,
  today.value,
  enquiries.settings.showLost,
))

const detailOpen = computed(() => typeof route.params.id === 'string' && route.params.id !== '')

const selectedId = computed(() => {
  const id = Number(route.params.id)

  return Number.isFinite(id) && id > 0 ? id : null
})

// -- The stage sheet --------------------------------------------------------

const sheetOpen = ref(false)
const sheetFor = ref<Enquiry | null>(null)
const sheetAnchor = ref<HTMLElement | null>(null)

function onStage(enquiry: Enquiry, anchor: HTMLElement | null): void {
  sheetFor.value = enquiry
  sheetAnchor.value = anchor
  sheetOpen.value = true
}

// The detail's own buttons open the same sheet, or make the same move
// directly, so there is one path from a decision to a write.
function onAction(key: string, id: number): void {
  const enquiry = enquiries.find(id) ?? enquiries.detail

  if (!enquiry) {
    return
  }

  if (key === 'possible') {
    void move(enquiry, 'possible', null)

    return
  }

  if (key === 'convert') {
    void move(enquiry, 'provisional', null)

    return
  }

  if (key === 'reopen') {
    void move(enquiry, 'in_conversation', null)
  }

  // "quote" is the price flow, which is a later prompt.
}

function onMove(enquiry: Enquiry, stage: BookingStage, reason: LostReason | null): void {
  void move(enquiry, stage, reason)
}

const notice = ref('')
const noticeOpen = ref(false)

function say(message: string): void {
  notice.value = message
  noticeOpen.value = true
}

/**
 * One write, and the row is replaced from what comes back.
 *
 * A record that has become provisional is no longer an enquiry and leaves the
 * list, and the notice says so rather than letting a row vanish unexplained.
 * The wording differs by where it landed, because "moved to Possible" and "is
 * now a provisional booking" are different events and the second is the one
 * that needs reassuring about: 5.3 says converting copies nothing and loses
 * nothing, and the sentence says it.
 */
async function move(enquiry: Enquiry, stage: BookingStage, reason: LostReason | null): Promise<void> {
  const name = enquiry.client_name

  try {
    await enquiries.setStage(enquiry.id, stage, reason)

    if (stage === 'provisional') {
      say(t('enquiries.moved.converted', { name }))
    } else if (stage === 'lost') {
      say(t('enquiries.moved.ended', { name }))
    } else if (stage === 'possible') {
      say(t('enquiries.moved.possible', { name }))
    } else {
      say(t('enquiries.moved.stage', { name, stage: t(`bookings.stage.${stage}`) }))
    }
  } catch {
    say(t('enquiries.moved.failed'))
  }
}

// -- Where the band headings come to rest -----------------------------------
//
// A ResizeObserver rather than a watcher, because the bar's height moves for
// reasons this component does not initiate: the button wrapping under the
// field at a narrow width, and the browser's own text size.
const bar = useTemplateRef<{ $el: HTMLElement } | null>('bar')
const barHeight = ref(0)

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
    await enquiries.retry()
  } finally {
    retrying.value = false
  }
}

// A sheet left open over a row that has just left the list has nothing to act
// on, so it closes with it.
watch(() => enquiries.enquiries, () => {
  if (sheetFor.value && !enquiries.find(sheetFor.value.id)) {
    sheetOpen.value = false
    sheetFor.value = null
  }
})
</script>
