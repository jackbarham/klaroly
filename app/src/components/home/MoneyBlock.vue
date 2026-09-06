<template>
  <section aria-labelledby="money-heading">
    <div class="mb-1 flex items-baseline justify-between gap-4 px-6 @split:px-4">
      <h2
        id="money-heading"
        class="text-lg font-semibold text-text-strong"
      >
        {{ t('home.money.title') }}
      </h2>

      <RouterLink
        class="flex shrink-0 items-center gap-1 text-meta font-medium text-accent-text focus-visible:focus-ring"
        :to="{ name: 'settings' }"
      >
        {{ t('home.money.export') }}
        <Icon
          name="chevron-right"
          class="size-4"
          aria-hidden="true"
        />
      </RouterLink>
    </div>

    <div class="space-y-4 px-6 @split:px-4">
      <!--
        Decision 27's headline: money already earned and not collected. For a
        sole trader it is the single most useful figure this software reports,
        and it is exactly what slips when a business runs on a notes app and a
        diary, so it is a band rather than one of the four figures below.

        It is the sum of the client_balance attention rows, computed once on the
        server and used twice, so the total and the rows above it cannot
        disagree.
      -->
      <RouterLink
        v-if="money.owed_minor !== null && money.owed_minor > 0"
        class="flex w-full items-center gap-3 rounded-card border border-danger bg-danger-subtle p-3 text-left focus-visible:focus-ring"
        :to="{ name: 'attention' }"
      >
        <Icon
          name="alert"
          class="size-5 shrink-0 text-danger-text"
          aria-hidden="true"
        />
        <span class="min-w-0 grow">
          <span class="block font-semibold text-danger-text">{{ t('home.money.owed', { amount: amount(money.owed_minor) }) }}</span>
          <span class="block text-meta text-text-muted">
            {{ t('home.money.owed_from', { count: money.owed_count ?? 0 }, money.owed_count ?? 0) }}
          </span>
          <!--
            **What a snooze took out of the headline, named rather than
            silent.** A snoozed overdue balance leaves the attention list and
            takes its money with it, which is correct: the headline is the size
            of the problem the artist has not already handled. Decision 27's own
            reasoning is that an escape hatch which quietly shrinks a figure
            teaches artists to distrust the figure, so the money is still said.
            Drawn only above zero.
          -->
          <span
            v-if="money.snoozed_minor !== null && money.snoozed_minor > 0"
            class="block text-meta text-text-subtle"
          >{{ t('home.money.snoozed', { amount: amount(money.snoozed_minor) }) }}</span>
        </span>
        <Icon
          name="chevron-right"
          class="size-5 shrink-0 text-text-subtle"
          aria-hidden="true"
        />
      </RouterLink>

      <!--
        The period selector. A view setting on the device, and deliberately not
        in Adjust: one value with two homes is two places to keep in step, and a
        setting earns a second home when it is hard to reach. This one is on
        screen.

        Two by two rather than a row of four, and measured rather than chosen:
        four across is 79px a cell at 375px and "12 months" needs 86, so it
        either truncates or wraps unevenly. It is also two by two in the 360px
        rail at lg, which is the same arithmetic.
      -->
      <div
        class="grid grid-cols-2 gap-1 rounded-control bg-surface-sunken p-1"
        role="group"
        :aria-label="t('home.money.period.label')"
      >
        <button
          v-for="key in periodKeys"
          :key="key"
          class="rounded-control px-2 py-1.5 text-meta font-medium transition-colors focus-visible:focus-ring"
          :class="key === period ? 'bg-surface-raised text-text-strong shadow-raised' : 'text-text-muted hover:text-text-strong'"
          type="button"
          :aria-pressed="key === period"
          @click="emit('period', key)"
        >
          {{ t(`home.money.period.${key}`) }}
        </button>
      </div>

      <!--
        An odd number of figures leaves a hole in a two-column grid, so the last
        one takes the whole row rather than half of an empty one.
      -->
      <dl class="grid grid-cols-2 gap-3">
        <div
          v-for="(figure, index) in figures"
          :key="figure.key"
          class="rounded-card border border-border bg-surface-raised p-3"
          :class="figures.length % 2 === 1 && index === figures.length - 1 ? 'col-span-2' : ''"
        >
          <dt class="text-meta text-text-muted">
            {{ t(figure.labelKey) }}
          </dt>
          <dd class="mt-0.5 text-lg font-semibold text-text-strong">
            {{ figure.value }}
            <span class="mt-0.5 block text-meta font-normal text-text-muted">
              <span
                v-if="figure.emphasis"
                class="font-medium text-danger-text"
              >{{ figure.emphasis }}</span><template v-if="figure.emphasis && figure.under">, </template>{{ figure.under }}
            </span>
          </dd>
        </div>
      </dl>

      <!--
        **The selector governs three figures and not five**, and this line is
        what stops the likeliest misreading of the most-scrutinised numbers in
        the app. A selector sitting above all of them implies it governs all of
        them.

        With payment tracking off it also says what turning it on would add,
        which is discovery arriving where it is useful rather than in a settings
        screen nobody has opened.
      -->
      <p class="text-meta text-text-subtle">
        {{ t(money.basis === 'payments' ? 'home.money.foot_payments' : 'home.money.foot_value') }}
        <template v-if="money.excludes_other_currencies">
          {{ t('home.money.excludes') }}
        </template>
      </p>
    </div>
  </section>
</template>

<script setup lang="ts">
// The money block, business logic 18.3.
//
// **It is never removed by a feature toggle, and it is the one block with no
// empty state.** Business logic 21.2 says switching invoicing or payment
// tracking off "removes the related items from the home screen's attention
// block" — the attention block, named, and nothing about the figures. A booking
// carries a price whether or not anybody raised an invoice for it, so what a
// diary is worth is knowable on every account. What the toggles take away is
// the cash half, one figure at a time.
//
// **It takes no feature flags at all, and that is the strongest form of
// "reads meta and never the auth store".** The response has already applied
// them: `outstanding` and `owed_minor` are null when invoicing is off, and
// `basis` says whether the period figure is cash or value. So there is nothing
// here to consult a store about, and a stale auth store cannot make this block
// draw a figure the server did not compute. Passing the flags in would only
// create a second opinion about what to draw.
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import Icon from '@/components/ui/Icon.vue'
import { formatMoney } from '@/lib/money'
import type { HomeMoney, PeriodKey } from '@/types/home'

const props = defineProps<{
  money: HomeMoney
  period: PeriodKey
}>()

const emit = defineEmits<{
  period: [key: PeriodKey]
}>()

const { n, t } = useI18n()

const periodKeys: PeriodKey[] = ['this_month', 'three_months', 'twelve_months', 'business_year']

function amount(minor: number): string {
  return formatMoney(n, minor, props.money.currency)
}

interface Figure {
  key: string
  labelKey: string
  value: string
  // The part of the caption that is money genuinely late, which is the only
  // thing on this screen that takes a colour.
  emphasis?: string
  under?: string
}

const figures = computed<Figure[]>(() => {
  const money = props.money
  const totals = money.periods[props.period]
  const list: Figure[] = []

  // **The period figure, and its label says which basis it is on.** With
  // payment tracking off it stops being cash and becomes value: those are
  // different numbers, so the block says which it is showing rather than
  // drawing "Received" over a figure that is nothing of the sort.
  list.push({
    key: 'period',
    labelKey: money.basis === 'payments' ? 'home.money.received' : 'home.money.booked_in_period',
    value: amount(totals.value_minor),
    under: t('home.money.under_period', {
      count: totals.booking_count,
      average: amount(totals.average_value_minor),
    }, totals.booking_count),
  })

  // Outstanding goes with invoicing: with it off, nothing was ever given a due
  // date, so the API sends null rather than nought and there is no figure to
  // draw.
  if (money.outstanding !== null) {
    const outstanding = money.outstanding

    list.push({
      key: 'outstanding',
      labelKey: 'home.money.outstanding',
      // **due plus overdue is the whole of outstanding.** snoozed_minor is a
      // SUBSET of overdue, not a third bucket, so it is never added here and
      // never drawn as a third figure of equal standing: a snoozed invoice is
      // still late, the artist has only asked to stop hearing about it.
      value: amount(outstanding.due_minor + outstanding.overdue_minor),
      // The overdue half is the only fragment on this screen that takes a
      // colour, because it is money that is genuinely late.
      emphasis: outstanding.overdue_minor > 0
        ? t('home.money.under_outstanding_overdue', { overdue: amount(outstanding.overdue_minor) })
        : undefined,
      under: t('home.money.under_outstanding_due', { due: amount(outstanding.due_minor) }),
    })
  }

  // Never removed by a toggle: a diary has a value on every account.
  list.push({
    key: 'booked_ahead',
    labelKey: 'home.money.booked_ahead',
    value: amount(money.booked_ahead_minor),
    under: t('home.money.under_booked_ahead', { count: money.booked_ahead_count }),
  })

  // Shown separately and **never added to the figure above** (business logic
  // 18.3): a held date is not money, and a figure mixing the two would be the
  // most optimistic number in the app.
  list.push({
    key: 'provisional',
    labelKey: 'home.money.provisional',
    value: amount(money.provisional_minor),
    under: t('home.money.under_provisional', { count: money.provisional_count }),
  })

  return list
})
</script>
