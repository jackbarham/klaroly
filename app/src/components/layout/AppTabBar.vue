<template>
  <!--
    The phone navigation: a bar that floats clear of the bottom edge rather
    than sitting on it, above the safe-area inset, with a translucent
    background so the content behind it shows through.
  -->
  <nav
    class="fixed inset-x-4 z-10 bar-bottom lg:hidden"
    :aria-label="t('nav.primary_label')"
  >
    <div
      ref="bar"
      class="relative flex h-16 items-center rounded-sheet border border-border bg-surface-raised/75 px-2 shadow-raised backdrop-blur"
    >
      <!--
        The active indicator. It is one element that moves, rather than a
        style on each item, so that it can slide from one to the next. Its
        position and width are measured and handed over as custom properties;
        the style block below turns those into a transform and a width.
      -->
      <span
        v-show="activeIndex >= 0"
        class="pill h-12 rounded-full bg-surface-sunken"
        :style="pillStyle"
        aria-hidden="true"
      />

      <div
        v-for="(item, index) in tabBarItems"
        :key="item.key"
        :ref="(element) => setItemElement(index, element)"
        class="flex flex-1 justify-center"
      >
        <!--
          The create button is not a destination: it never takes the pill and
          it never marks itself as the current page. It is larger than the
          other items, it lifts above the top edge of the bar, and it carries
          the accent, because it is the primary action on a phone in the same
          way the sidebar's New button is on a wide screen.
        -->
        <button
          v-if="item.routeName === null"
          class="-translate-y-4 flex h-16 w-16 items-center justify-center rounded-full bg-accent text-text-on-accent shadow-raised focus-visible:focus-ring"
          type="button"
          :aria-label="t(item.labelKey)"
          @click="emit('create')"
        >
          <Icon
            :name="item.icon"
            class="h-6 w-6"
          />
        </button>

        <RouterLink
          v-else
          class="relative flex h-full w-full flex-col items-center justify-center gap-1 rounded-full text-xs focus-visible:focus-ring"
          :class="isCurrent(item) ? 'font-medium text-accent-text' : 'text-text-muted'"
          :to="{ name: item.routeName }"
          :aria-current="isCurrent(item) ? 'page' : undefined"
        >
          <Icon
            :name="item.icon"
            class="h-6 w-6"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
// The tab bar builds itself from the navigation config, in the order the
// config lists: Home, Bookings, the create button, Enquiries, More.
//
// The pill behind the current item is measured rather than laid out, because
// animating a flex layout is expensive and animating left and right cannot
// be done on the compositor. The measurement runs on the first render, so a
// deep link such as /enquiries lands with the pill in the right place, and
// again whenever the route changes or the bar changes size.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { activeTabIndex, activeTabKey, tabBarItems, type NavItem } from '@/lib/navigation'

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
const route = useRoute()

const bar = useTemplateRef<HTMLElement>('bar')
const itemElements = ref<(HTMLElement | null)[]>([])

const pillLeft = ref(0)
const pillWidth = ref(0)

const activeIndex = computed(() => activeTabIndex(route.name))

const pillStyle = computed(() => ({
  '--pill-x': `${pillLeft.value}px`,
  '--pill-w': `${pillWidth.value}px`,
}))

function isCurrent(item: NavItem): boolean {
  return activeTabKey(route.name) === item.key
}

function setItemElement(index: number, element: Element | ComponentPublicInstance | null): void {
  itemElements.value[index] = element instanceof HTMLElement ? element : null
}

// offsetLeft is measured from the bar, because the bar is the nearest
// positioned ancestor.
function measure(): void {
  const element = activeIndex.value >= 0 ? itemElements.value[activeIndex.value] : null

  if (!element) {
    return
  }

  pillLeft.value = element.offsetLeft
  pillWidth.value = element.offsetWidth
}

let observer: ResizeObserver | null = null

onMounted(async () => {
  await nextTick()
  measure()

  // Rotating the phone or opening the keyboard changes the bar's width, and
  // the pill has to follow. ResizeObserver is missing in some test
  // environments, which is the only reason for the check.
  if (bar.value && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(() => measure())
    observer.observe(bar.value)
  }
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})

watch(activeIndex, async () => {
  await nextTick()
  measure()
})
</script>

<style scoped>
/*
  Transform and width, never left and right, so that the browser can move the
  pill without laying the bar out again.
*/
.pill {
  position: absolute;
  top: 50%;
  left: 0;
  width: var(--pill-w);
  transform: translate(var(--pill-x), -50%);
  transition: transform 250ms ease, width 250ms ease;
}

@media (prefers-reduced-motion: reduce) {
  .pill {
    transition: none;
  }
}
</style>
