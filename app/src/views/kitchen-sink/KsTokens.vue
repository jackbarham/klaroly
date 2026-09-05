<template>
  <div class="space-y-12">
    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Colour
      </h3>
      <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <li
          v-for="colour in colourTokens"
          :key="colour.token"
          class="space-y-2"
        >
          <div
            class="h-12 rounded-control border border-neutral-200"
            :class="colour.className"
          />
          <p class="font-mono text-xs text-neutral-900">
            {{ colour.token }}
          </p>
          <p class="font-mono text-xs text-neutral-500">
            {{ values[colour.token] }}
          </p>
          <p class="text-xs text-neutral-500">
            {{ colour.use }}
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Type
      </h3>
      <ul class="divide-y divide-neutral-200 border-y border-neutral-200">
        <li
          v-for="(step, index) in typeSteps"
          :key="step.className"
          class="flex flex-wrap items-baseline gap-x-6 gap-y-2 py-4"
        >
          <code class="w-48 shrink-0 font-mono text-xs text-neutral-500">{{ step.className }}</code>
          <span
            :ref="(element) => keepSample(index, element)"
            class="text-neutral-900"
            :class="step.className"
          >{{ step.sample }}</span>
          <span class="font-mono text-xs text-neutral-500">{{ typeDetails[index] }}</span>
          <span class="text-xs text-neutral-500">{{ step.use }}</span>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Spacing
      </h3>
      <ul class="space-y-2">
        <li
          v-for="(space, index) in spacingSteps"
          :key="space.step"
          class="flex items-center gap-4"
        >
          <code class="w-16 shrink-0 font-mono text-xs text-neutral-500">{{ space.step }}</code>
          <div
            :ref="(element) => keepBar(index, element)"
            class="h-4 bg-neutral-300"
            :class="space.className"
          />
          <span class="font-mono text-xs text-neutral-500">{{ spacingWidths[index] }}</span>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Radius
      </h3>
      <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <li
          v-for="radius in radiusTokens"
          :key="radius.token"
          class="space-y-2"
        >
          <div
            class="h-16 border border-neutral-300 bg-neutral-0"
            :class="radius.className"
          />
          <p class="font-mono text-xs text-neutral-900">
            {{ radius.token }}
          </p>
          <p class="font-mono text-xs text-neutral-500">
            {{ values[radius.token] }}
          </p>
          <p class="text-xs text-neutral-500">
            {{ radius.use }}
          </p>
        </li>
        <!--
          The fourth radius is Tailwind's own rather than a token, which is
          why it is written out here instead of sitting in the list.
        -->
        <li class="space-y-2">
          <div class="h-16 rounded-full border border-neutral-300 bg-neutral-0" />
          <p class="font-mono text-xs text-neutral-900">
            rounded-full
          </p>
          <p class="font-mono text-xs text-neutral-500">
            not a token
          </p>
          <p class="text-xs text-neutral-500">
            the toggle switch, the tab bar pill, the create button
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Border width
      </h3>
      <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <li
          v-for="width in borderWidths"
          :key="width.className"
          class="space-y-2"
        >
          <div
            class="h-16 rounded-control border-neutral-900 bg-neutral-0"
            :class="width.className"
          />
          <p class="font-mono text-xs text-neutral-900">
            {{ width.className }}
          </p>
          <p class="text-xs text-neutral-500">
            {{ width.use }}
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-neutral-700">
        Shadow
      </h3>
      <ul class="space-y-4">
        <li
          v-for="shadow in shadowTokens"
          :key="shadow.token"
          class="space-y-2"
        >
          <div
            class="h-16 rounded-card bg-neutral-0"
            :class="shadow.className"
          />
          <p class="font-mono text-xs text-neutral-900">
            {{ shadow.token }}
          </p>
          <p class="font-mono text-xs break-all text-neutral-500">
            {{ values[shadow.token] }}
          </p>
          <p class="text-xs text-neutral-500">
            {{ shadow.use }}
          </p>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
// The raw material, so that a token change is visible here before any
// component is looked at.
//
// Nothing on this page writes a value down. The colours, radii and shadows
// are read back from the custom properties themselves, and the type and
// spacing steps are measured from the samples on the page, so a number here
// cannot go stale against src/assets/app.css. A token the stylesheet does not
// carry reads as "not generated", which is worth knowing: Tailwind only emits
// a theme variable that something uses.
import { onMounted, ref, type ComponentPublicInstance } from 'vue'
import { borderWidths, colourTokens, radiusTokens, shadowTokens, spacingSteps, typeSteps } from '@/views/kitchen-sink/tokens'

const values = ref<Record<string, string>>({})
const typeDetails = ref<string[]>([])
const spacingWidths = ref<string[]>([])

// The samples and the bars are only ever read once, after the page is drawn,
// so they are plain arrays rather than refs.
const samples: (HTMLElement | null)[] = []
const bars: (HTMLElement | null)[] = []

function keepSample(index: number, element: Element | ComponentPublicInstance | null): void {
  samples[index] = element instanceof HTMLElement ? element : null
}

function keepBar(index: number, element: Element | ComponentPublicInstance | null): void {
  bars[index] = element instanceof HTMLElement ? element : null
}

function readVariable(name: string): string {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim()

  return value === '' ? 'not generated' : value
}

function describeType(element: HTMLElement | null): string {
  if (!element) {
    return 'not measured'
  }

  const style = getComputedStyle(element)

  return `${style.fontSize} / ${style.lineHeight} / ${style.fontWeight}`
}

onMounted(() => {
  const found: Record<string, string> = {}

  for (const item of [...colourTokens, ...radiusTokens, ...shadowTokens]) {
    found[item.token] = readVariable(item.token)
  }

  values.value = found
  typeDetails.value = samples.map((element) => describeType(element))
  spacingWidths.value = bars.map((element) => (element ? `${element.offsetWidth}px` : 'not measured'))
})
</script>
