<template>
  <!--
    Standalone is what the four authentication screens are: the page itself,
    the main landmark, filling the window. Anything showing this component
    inside a page of its own passes false, so that one main landmark does not
    end up inside another.
  -->
  <component
    :is="standalone ? 'main' : 'section'"
    class="flex flex-col items-center justify-center p-6"
    :class="standalone ? 'min-h-screen' : ''"
  >
    <p class="mb-6 text-2xl font-bold">
      {{ t('app.name') }}
    </p>
    <Card class="w-full max-w-sm">
      <div class="space-y-6">
        <h1 class="text-xl font-semibold">
          {{ title }}
        </h1>
        <slot />
      </div>
    </Card>
  </component>
</template>

<script setup lang="ts">
// The centred card every authentication screen sits in, with the Klaroly
// wordmark above it. One column at every width (decision 10); the card
// simply narrows on a phone.
//
// It is the UI kit's Card, so a signed-out page and a signed-in one share a
// border, a radius and a padding step. It stays a component of its own
// because it is also the main landmark and the heading of four screens, and
// that is four copies of the same eight lines otherwise. That landmark is why
// it takes a standalone prop: on a page that already has a main, it renders a
// section instead.
import { useI18n } from 'vue-i18n'
import Card from '@/components/ui/Card.vue'

withDefaults(defineProps<{
  title: string
  standalone?: boolean
}>(), {
  standalone: true,
})

const { t } = useI18n()
</script>
