<template>
  <!--
    A 44px bar rather than the kit's PageHeader, which is a 2xl heading with
    space under it and no rule. This is a compact bar, and a "compact" variant
    on PageHeader used by one screen is a prop that reads as an offer.

    **There is no date in it.** It was drawn and removed: every countdown on the
    screen already says "in 6 days" or "next week", which is the useful half of
    knowing what day it is, and the calendar is one tap away for the other half.
    Taking it out took the bar from 56px to 44px and brought the second
    attention row above the fold with it, which is twelve pixels buying a whole
    row.

    **There is still no bell, and now for a better reason.** The phone has a top
    bar, AppTopBar, and the bell is in it, once, above every screen rather than
    added to each view's own header. Decision 106 is still open about what
    notifications are; where the bell lives is not.

    **On a phone this bar is the second row of chrome**, under the top bar's
    52px, and that is deliberate. The top bar says the business name on this
    screen alone and this says what the screen is, so the two are not repeating
    each other, and Adjust needs somewhere to live. It is 44px against the
    twelve this bar was cut by to gain a row, so it is worth looking at again
    once Home has been used on a phone.
  -->
  <header class="-mx-6 mb-4 flex h-11 items-center justify-between gap-4 border-b border-border px-6 lg:h-13">
    <h1 class="text-lg font-semibold text-text-strong lg:text-xl">
      {{ t('home.summary_title') }}
    </h1>

    <button
      ref="adjust"
      class="chip focus-visible:focus-ring"
      type="button"
      aria-haspopup="dialog"
      :aria-expanded="expanded"
      @click="emit('adjust', adjust)"
    >
      <Icon
        name="sliders"
        class="size-4"
        aria-hidden="true"
      />
      {{ t('home.adjust') }}
    </button>
  </header>
</template>

<script setup lang="ts">
// The top of the home screen: what it is, and the one control that changes how
// it is drawn.
import { useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import Icon from '@/components/ui/Icon.vue'

defineProps<{
  expanded: boolean
}>()

const emit = defineEmits<{
  // The button's own element, so AnchoredSheet can measure what to hang under
  // at lg. Measuring is the sheet's job; handing it the rectangle's owner is
  // this component's.
  adjust: [anchor: HTMLElement | null]
}>()

const { t } = useI18n()

const adjust = useTemplateRef<HTMLElement>('adjust')
</script>
