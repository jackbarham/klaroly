<template>
  <div class="space-y-12">
    <div
      v-for="group in colourGroups"
      :key="group.title"
      class="space-y-4"
    >
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          {{ group.title }}
        </h3>
        <p class="text-xs text-text-muted">
          {{ group.note }}
        </p>
      </div>
      <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        <li
          v-for="colour in group.tokens"
          :key="colour.token"
          class="space-y-2"
        >
          <div
            class="h-12 rounded-control border border-border"
            :class="colour.className"
          />
          <p class="font-mono text-xs text-text-strong">
            {{ colour.token }}
          </p>
          <p class="font-mono text-xs text-text-muted">
            {{ values[colour.token] }}
          </p>
          <p class="text-xs text-text-muted">
            {{ colour.use }}
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-text">
          Type
        </h3>
        <p class="text-xs text-text-muted">
          The theme's own scale first, then the Tailwind sizes the components
          still mostly use. Only body and meta have moved onto the scale so far;
          moving the rest is the next change.
        </p>
      </div>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="(step, index) in typeSteps"
          :key="step.className"
          class="flex flex-wrap items-baseline gap-x-6 gap-y-2 py-4"
        >
          <code class="w-48 shrink-0 font-mono text-xs text-text-muted">{{ step.className }}</code>
          <span
            :ref="(element) => keepSample(index, element)"
            class="text-text-strong"
            :class="step.className"
          >{{ step.sample }}</span>
          <span class="font-mono text-xs text-text-muted">{{ typeDetails[index] }}</span>
          <span class="text-xs text-text-muted">{{ step.use }}</span>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        Spacing
      </h3>
      <ul class="space-y-2">
        <li
          v-for="(space, index) in spacingSteps"
          :key="space.step"
          class="flex items-center gap-4"
        >
          <code class="w-16 shrink-0 font-mono text-xs text-text-muted">{{ space.step }}</code>
          <div
            :ref="(element) => keepBar(index, element)"
            class="h-4 bg-border-strong"
            :class="space.className"
          />
          <span class="font-mono text-xs text-text-muted">{{ spacingWidths[index] }}</span>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        Radius
      </h3>
      <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <li
          v-for="radius in radiusTokens"
          :key="radius.token"
          class="space-y-2"
        >
          <div
            class="h-16 border border-border-strong bg-surface-raised"
            :class="radius.className"
          />
          <p class="font-mono text-xs text-text-strong">
            {{ radius.token }}
          </p>
          <p class="font-mono text-xs text-text-muted">
            {{ values[radius.token] }}
          </p>
          <p class="text-xs text-text-muted">
            {{ radius.use }}
          </p>
        </li>
        <!--
          The fourth radius is Tailwind's own rather than a token, which is
          why it is written out here instead of sitting in the list.
        -->
        <li class="space-y-2">
          <div class="h-16 rounded-full border border-border-strong bg-surface-raised" />
          <p class="font-mono text-xs text-text-strong">
            rounded-full
          </p>
          <p class="font-mono text-xs text-text-muted">
            not a token
          </p>
          <p class="text-xs text-text-muted">
            the toggle switch, the tab bar pill, the create button
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        Border width
      </h3>
      <ul class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <li
          v-for="width in borderWidths"
          :key="width.className"
          class="space-y-2"
        >
          <div
            class="h-16 rounded-control border-border-strong bg-surface-raised"
            :class="width.className"
            :tabindex="width.className.includes('focus') ? 0 : undefined"
          />
          <p class="font-mono text-xs text-text-strong">
            {{ width.className }}
          </p>
          <p class="text-xs text-text-muted">
            {{ width.use }}
          </p>
        </li>
      </ul>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        Shadow
      </h3>
      <ul class="space-y-4">
        <li
          v-for="(shadow, index) in shadowTokens"
          :key="shadow.token"
          class="space-y-2"
        >
          <div
            :ref="(element) => keepShadow(index, element)"
            class="h-16 rounded-card bg-surface-raised"
            :class="shadow.className"
          />
          <p class="font-mono text-xs text-text-strong">
            {{ shadow.token }}
          </p>
          <p class="font-mono text-xs break-all text-text-muted">
            {{ shadowValues[index] }}
          </p>
          <p class="text-xs text-text-muted">
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
// cannot go stale against src/assets/app.css.
//
// The names read back are the custom properties in :root, not the @theme
// inline aliases above them. An inline alias is deliberately never emitted,
// which is the mechanism that lets the dark class redefine one layer and move
// the whole app; a token reading "not generated" is a real finding.
import { onMounted, ref, type ComponentPublicInstance } from 'vue'
import { borderWidths, colourGroups, radiusTokens, shadowTokens, spacingSteps, typeSteps } from '@/views/kitchen-sink/tokens'

const values = ref<Record<string, string>>({})
const typeDetails = ref<string[]>([])
const spacingWidths = ref<string[]>([])
const shadowValues = ref<string[]>([])

// The samples and the bars are only ever read once, after the page is drawn,
// so they are plain arrays rather than refs.
const samples: (HTMLElement | null)[] = []
const bars: (HTMLElement | null)[] = []
const shadowBoxes: (HTMLElement | null)[] = []

function keepSample(index: number, element: Element | ComponentPublicInstance | null): void {
  samples[index] = element instanceof HTMLElement ? element : null
}

function keepBar(index: number, element: Element | ComponentPublicInstance | null): void {
  bars[index] = element instanceof HTMLElement ? element : null
}

// A shadow is measured off its own sample rather than read from :root,
// because two of them are declared straight into @theme inline and an inline
// alias is deliberately never emitted as a variable. Measuring is right for
// all six either way: what is drawn is what is worth showing.
function keepShadow(index: number, element: Element | ComponentPublicInstance | null): void {
  shadowBoxes[index] = element instanceof HTMLElement ? element : null
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

  const colours = colourGroups.flatMap((group) => group.tokens)

  for (const item of [...colours, ...radiusTokens]) {
    found[item.token] = readVariable(item.token)
  }

  values.value = found
  typeDetails.value = samples.map((element) => describeType(element))
  spacingWidths.value = bars.map((element) => (element ? `${element.offsetWidth}px` : 'not measured'))
  shadowValues.value = shadowBoxes.map((element) => (element ? getComputedStyle(element).boxShadow : 'not measured'))
})
</script>
