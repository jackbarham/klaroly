<template>
  <div class="@container">
    <HomeHeader
      :expanded="adjustOpen"
      @adjust="openAdjust"
    />

    <p
      v-if="verifiedMessage"
      class="mb-4 rounded-card border border-border bg-surface-raised p-4 text-sm text-text"
      role="status"
    >
      {{ t('auth.email_verified') }}
    </p>

    <VerificationBanner />

    <!-- The same failed-load shape the enquiries and bookings screens use. -->
    <p
      v-if="home.status === 'failed'"
      class="mb-4 flex flex-wrap items-center gap-3 rounded-card border border-border bg-surface-raised p-4 text-body text-text"
      role="status"
    >
      {{ t('home.load_failed') }}
      <AppButton
        variant="secondary"
        size="small"
        :pending="retrying"
        @click="onRetry"
      >
        {{ t('home.retry') }}
      </AppButton>
    </p>

    <template v-else-if="summary">
      <FirstRun
        v-if="home.isEmptyAccount"
        @create="create = true"
      />

      <!--
        **Two columns at lg on the container query, not a media query and not a
        second route.** Attention takes the main column at every desktop size,
        because a task list in a 340px rail truncates its second lines, so the
        order only rearranges the side column here and the preview count does
        nothing at all.

        **Blocks do not rearrange themselves.** On a quiet week Money does not
        move up and Next up does not become a hero: the order is fixed and
        blocks drop out of it. A screen that rearranges depending on how the
        week is going is a screen nobody can learn.
      -->
      <div
        v-else
        class="-mx-6 grid items-start gap-8 @split:mx-0 @split:home-columns"
      >
        <!--
          **One render, one DOM order, and the artist's order IS the DOM
          order.** Below the split this is a single column and the blocks are
          already in the right sequence; above it, grid placement puts Attention
          in the main column and the other two in the rail, without moving
          anything in the markup.

          That matters beyond tidiness. An earlier version put the blocks in a
          fixed DOM order and moved them with CSS `order`, which reads correctly
          and tabs wrongly: focus order and screen-reader order follow the DOM,
          so an artist who put Money first would hear Attention first. Grid
          placement changes which cell a block sits in and leaves the sequence
          alone.

          The rail is not pinned to rows: with only column 2 named, the two rail
          blocks fall into it in DOM order, which is the artist's, and a block
          that is not drawn at all simply leaves one.
        -->
        <div
          v-for="key in home.settings.order"
          :key="key"
          class="min-w-0"
          :class="splitPlacement[key]"
        >
          <AttentionBlock
            v-if="key === 'attention'"
            :rows="summary.attention"
            :today="home.today"
            :limit="previewLimit(home.settings.previewCount)"
            :total="home.attentionTotal"
          />

          <NextUpBlock
            v-else-if="key === 'next'"
            :events="summary.upcoming"
            :today="home.today"
          />

          <MoneyBlock
            v-else
            :money="summary.money"
            :period="home.settings.period"
            @period="setPeriod"
          />
        </div>
      </div>
    </template>

    <AdjustSheet
      v-model:open="adjustOpen"
      :order="home.settings.order"
      :preview-count="home.settings.previewCount"
      :anchor-to="adjustAnchor"
      @order="home.update({ order: $event })"
      @count="home.update({ previewCount: $event })"
    />

    <CreateMenu v-model:open="create" />
  </div>
</template>

<script setup lang="ts">
// Home: business logic section 18's three blocks, in the order the artist put
// them in.
//
// One document scroll container, as everywhere else. Nothing on this screen is
// its own scroller, which is what lets a band heading be sticky inside its own
// group without pinning itself to the top of the page.
import { computed, onMounted, ref, shallowRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AdjustSheet from '@/components/home/AdjustSheet.vue'
import AttentionBlock from '@/components/home/AttentionBlock.vue'
import FirstRun from '@/components/home/FirstRun.vue'
import HomeHeader from '@/components/home/HomeHeader.vue'
import MoneyBlock from '@/components/home/MoneyBlock.vue'
import NextUpBlock from '@/components/home/NextUpBlock.vue'
import CreateMenu from '@/components/layout/CreateMenu.vue'
import VerificationBanner from '@/components/VerificationBanner.vue'
import { previewLimit, type BlockKey } from '@/lib/homeView'
import { useAuthStore } from '@/stores/auth'
import { useHomeStore } from '@/stores/home'
import type { PeriodKey } from '@/types/home'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const home = useHomeStore()

const adjustOpen = ref(false)
const adjustAnchor = shallowRef<HTMLElement | null>(null)
const create = ref(false)
const verifiedMessage = ref(false)

const summary = computed(() => home.summary)


/**
 * Which grid cell each block takes above the split, and nothing below it.
 *
 * **Attention takes the main column at every desktop size** and the artist's
 * order cannot move it, because a task list in a 340px rail truncates its
 * second lines. So the order rearranges the rail at lg and nothing else, and
 * the preview count does nothing there at all, which is what the sheet's
 * one-line note says rather than leaving the artist to work it out.
 *
 * Below the split none of these apply and the grid is one column, so the
 * artist's order is simply the order of the v-for.
 */
const splitPlacement: Record<BlockKey, string> = {
  attention: '@split:col-start-1 @split:row-start-1 @split:row-span-2',
  next: '@split:col-start-2',
  money: '@split:col-start-2',
}

const retrying = ref(false)

async function onRetry(): Promise<void> {
  retrying.value = true

  try {
    await home.retry()
  } finally {
    retrying.value = false
  }
}

function openAdjust(anchor: HTMLElement | null): void {
  adjustAnchor.value = anchor
  adjustOpen.value = true
}

function setPeriod(period: PeriodKey): void {
  home.update({ period })
}

// The verification link in the email ends on this page with ?verified=1. The
// store is refreshed so the banner goes, the message is shown once, and the
// query is replaced so a reload does not show it again.
onMounted(async () => {
  void home.load()

  if (route.query.verified === '1') {
    verifiedMessage.value = true
    await router.replace({ name: 'home', query: {} })

    try {
      await auth.refresh()
    } catch {
      // A failed refresh leaves the banner as it was; the guard handles a 401.
    }
  }
})
</script>
