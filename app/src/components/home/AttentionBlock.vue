<template>
  <section
    v-if="total > 0"
    aria-labelledby="attention-heading"
  >
    <div class="mb-1 flex items-baseline justify-between gap-4 px-6 @split:px-4">
      <h2
        id="attention-heading"
        class="text-lg font-semibold text-text-strong"
      >
        {{ t('home.attention.title') }}
      </h2>

      <!--
        **N is always the real total**, whatever the cap is set to. A preview
        that quietly showed four of eight would be the amounts-owed switch
        problem on the screen where it would hurt most.

        There is no second See all under the rows. It was built and removed: two
        links to the same place four rows apart reads as a mistake.

        It is hidden above the split, where the cap is not applied and the list
        is already complete, so a link to "all of them" would point at what is
        on screen.
      -->
      <RouterLink
        v-if="limit !== null && total > limit"
        class="flex shrink-0 items-center gap-1 text-meta font-medium text-accent-text focus-visible:focus-ring @split:hidden"
        :to="{ name: 'attention' }"
      >
        {{ t('home.attention.see_all', { count: total }) }}
        <Icon
          name="chevron-right"
          class="size-4"
          aria-hidden="true"
        />
      </RouterLink>
    </div>

    <!--
      Two renders, one per width, because the band headings differ in TEXT and
      not in visibility. See AttentionList.vue.
    -->
    <div
      class="@split:hidden"
      data-preview
    >
      <AttentionList
        :rows="rows"
        :today="today"
        :limit="limit"
      />
    </div>

    <div
      class="hidden @split:block"
      data-full
    >
      <AttentionList
        :rows="rows"
        :today="today"
        :limit="null"
      />
    </div>
  </section>

  <!--
    **The all-clear line, and it is one row rather than nothing.** An artist
    used to seeing four things who suddenly sees none cannot tell a clear week
    from a bug. It goes the moment anything is waiting, so it is never furniture
    on a busy screen.

    This is the block's only empty state: with nothing waiting at all on an
    account that has bookings, business logic 18.1's "the block disappears"
    would leave a silence the artist has to interpret.
  -->
  <p
    v-else
    class="flex items-center gap-2 px-6 text-meta text-text-muted @split:px-4"
  >
    <Icon
      name="check"
      class="size-4 text-success-text"
      aria-hidden="true"
    />
    {{ t('home.attention.all_clear') }}
  </p>
</template>

<script setup lang="ts">
// The attention block: business logic 18.1, what is waiting and whose court the
// ball is in.
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import AttentionList from '@/components/home/AttentionList.vue'
import Icon from '@/components/ui/Icon.vue'
import type { AttentionRow } from '@/types/home'

defineProps<{
  rows: AttentionRow[]
  today: Date
  // The preview cap below the split, or null when the artist has chosen All.
  limit: number | null
  // The account's real total, from meta, which is what "See all N" says.
  total: number
}>()

const { t } = useI18n()
</script>
