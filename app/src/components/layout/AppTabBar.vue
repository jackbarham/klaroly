<template>
  <!--
    The phone navigation: a bar that floats clear of the bottom edge rather
    than sitting on it, above the safe-area inset, with a translucent
    background so the content behind it shows through.

    Five destinations and nothing else. The create action used to be a raised
    accent circle in the middle of this bar and is now the accent button in
    AppTopBar, so the bar is five equal items with no interruption: it is a
    list of places you can go, which is the one thing it is for.

    **Icons alone, with no labels under them.** Five words at 375px is what
    held the icon down to 20px, and without them the picture can be the size it
    wants to be. What the words were doing is done by the top bar instead,
    which names the screen you are on: this bar says where you can go, and the
    bar above says where you are.

    Inset 32px rather than 16 for the same reason. A row of five pictures does
    not need the width that five pictures over five words did.
  -->
  <nav
    class="fixed inset-x-8 z-10 bar-bottom lg:hidden"
    :aria-label="t('nav.primary_label')"
  >
    <!--
      Fully rounded rather than the sheet radius, which is what a bar of five
      round items wants: the pill behind the current one and the bar's own
      corners are then the same shape.
    -->
    <div
      ref="bar"
      :class="[barGlassClasses, 'relative flex h-16 items-center rounded-full border border-border px-2 shadow-raised']"
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
          The icon is 28px. The item is still h-12 inside an h-16 bar, so the
          tap target has not moved and the pill behind it is unchanged: what
          dropping the labels bought is spent on the picture rather than on the
          target.

          **The name moves to aria-label rather than going away.** A bar of
          five unlabelled pictures says nothing at all to a screen reader, and
          the label was the only thing that ever said which one this is.
        -->
        <RouterLink
          class="relative flex h-full w-full items-center justify-center rounded-full transition-colors focus-visible:focus-ring"
          :class="isCurrent(item) ? 'text-accent-text' : 'text-text-muted'"
          :to="{ name: item.routeName }"
          :aria-current="isCurrent(item) ? 'page' : undefined"
          :aria-label="t(item.labelKey)"
        >
          <Icon
            :name="item.icon"
            class="h-7 w-7"
          />
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
// The tab bar builds itself from the navigation config, in the order the
// config lists: Summary, Bookings, Enquiries, Contacts, More.
//
// It holds no list of its own and it has no branch for an item that goes
// nowhere: tabBarItems is Destination[], so every entry has a route and the
// bar is five links. That is a type guarantee rather than a convention.
//
// The pill behind the current item is measured rather than laid out, because
// animating a flex layout is expensive and animating left and right cannot
// be done on the compositor. The measurement runs on the first render, so a
// deep link such as /enquiries lands with the pill in the right place, and
// again whenever the route changes or the bar changes size. Five equal items
// with nothing raised in the middle needs no arithmetic the four-and-a-button
// version did not already have: offsetLeft and offsetWidth are read off
// whichever item is current, whatever it is.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { barGlassClasses } from '@/components/layout/barGlass'
import { activeTabIndex, activeTabKey, tabBarItems, type Destination } from '@/lib/navigation'

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

function isCurrent(item: Destination): boolean {
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
  transition: transform var(--duration-base) var(--ease-out),
              width var(--duration-base) var(--ease-out);
}

@media (prefers-reduced-motion: reduce) {
  .pill {
    transition: none;
  }
}
</style>
