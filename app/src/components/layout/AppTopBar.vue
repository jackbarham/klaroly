<template>
  <!--
    The phone's top bar: which screen this is, and the two things that are the
    same on every screen. It is fixed and pinned, and it does not hide on
    scroll: a bar that comes and goes fights every sticky block underneath it,
    which is the conflict the prototype closes with.

    It runs to the top edge and carries the inset as padding rather than as a
    position, so the glass covers the status bar instead of leaving a clear
    strip above itself for content to scroll through.
  -->
  <header
    class="fixed inset-x-0 top-0 z-10 bar-top transition-colors lg:hidden"
    :class="stuck ? [barGlassClasses, 'border-b border-border'] : 'border-b border-transparent'"
  >
    <div class="flex h-13 items-center gap-3 px-6">
      <!--
        Not an h1. The page's own h1 is still in the document, hidden from the
        screen below lg but not from a screen reader, so a second one here
        would give every route two.
      -->
      <p class="min-w-0 flex-1 truncate text-section font-medium text-text-strong">
        {{ title }}
      </p>

      <!--
        The pill is the bars' glass, so the three pieces of chrome on a phone
        are one material. It drops the glass and its edge once the bar behind
        it has them: two layers of the same translucency stacked read as a
        darker patch rather than as one surface.
      -->
      <div
        class="flex shrink-0 items-center gap-0.5 rounded-full p-0.5 transition-colors"
        :class="stuck ? '' : [barGlassClasses, 'border border-border']"
      >
        <button
          class="bar-button text-text-muted transition-colors hover:bg-surface-hover hover:text-text-strong focus-visible:focus-ring"
          type="button"
          aria-haspopup="dialog"
          :aria-expanded="notificationsOpen"
          :aria-label="t('nav.notifications')"
          @click="notificationsOpen = true"
        >
          <!--
            No badge. Nothing counts notifications yet, and a dot over nothing
            teaches people to ignore the one that eventually means something.
          -->
          <Icon
            name="bell"
            class="h-5 w-5"
          />
        </button>

        <button
          class="bar-button bg-accent text-text-on-accent transition-colors hover:bg-accent-hover focus-visible:focus-ring"
          type="button"
          :aria-label="t(createItem.labelKey)"
          @click="emit('create')"
        >
          <Icon
            :name="createItem.icon"
            class="h-5 w-5"
          />
        </button>
      </div>
    </div>
  </header>

  <!--
    The front door to notifications and nothing more: a heading and one line.
    The notification centre is its own piece of work, so there is no list, no
    store and no endpoint behind this.

    It is a sibling of the bar rather than a child of it, because the bar is
    position:fixed with a z-index and so opens a stacking context: nested, the
    sheet's own z-20 would be measured inside the bar and its scrim would come
    out underneath the tab bar. The create sheet is a sibling in AppLayout for
    the same reason.
  -->
  <Sheet
    v-model:open="notificationsOpen"
    :label="t('nav.notifications')"
  >
    <h2 class="px-4 text-body font-medium text-text-strong">
      {{ t('nav.notifications') }}
    </h2>
    <EmptyState
      icon="bell"
      :text="t('nav.notifications_empty')"
    />
  </Sheet>
</template>

<script setup lang="ts">
// What sits above every page on a phone. The desktop never sees it: it is
// display:none from lg up, in the DOM at every width, which is how the rest of
// this shell already works.
//
// It reads the route and the auth store and nothing else. Both buttons hand
// their work upwards: create is the shell's state, and the notifications sheet
// is the only state this component owns.
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { barGlassClasses } from '@/components/layout/barGlass'
import EmptyState from '@/components/ui/EmptyState.vue'
import Icon from '@/components/ui/Icon.vue'
import Sheet from '@/components/ui/Sheet.vue'
import { createItem } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits<{
  create: []
}>()

const { t } = useI18n()
const route = useRoute()
const auth = useAuthStore()

const notificationsOpen = ref(false)

// Whether the page has moved under the bar. Four pixels is enough: it is the
// difference between resting at the top and having scrolled at all.
const stuck = ref(false)

// The screen's name, from the route's own locale key, so a page added anywhere
// arrives in this bar with nothing to wire up.
//
// Home is the exception and says the business name instead. The reasoning is
// the prototype's: identity is worth one screen rather than five, and what an
// artist wants at the top of a list is which list they are in. Home is
// therefore the one screen with two rows of chrome, this bar and HomeHeader's
// own "Your summary" with Adjust beside it, and that is deliberate rather than
// an oversight: the two say different things, and moving Adjust is content
// work. Worth looking at again once Home has been seen on a phone.
//
// The screen name is also the fallback, so the bar reads "Summary" rather than
// nothing in the moment before GET /api/me answers.
const title = computed(() => {
  const name = typeof route.meta.titleKey === 'string' ? t(route.meta.titleKey) : ''

  if (route.name !== 'home') {
    return name
  }

  return auth.me?.account.name || auth.me?.user.name || name
})

function onScroll(): void {
  stuck.value = window.scrollY > 4
}

// One passive listener, at every width. Watching a media query to avoid it at
// lg would be a width read in JavaScript, which is the one thing this shell
// does not do: the bar is display:none there, so the class it toggles paints
// nothing and the handler is a comparison.
onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
})
</script>

<style scoped>
/*
  The buttons look 38px and are hit at 44px, which is the style guide's minimum
  at phone width.

  A pseudo-element rather than padding, for the reason the chip utility gives
  and one more of its own: both of these are painted circles, one of them the
  accent, so padding would grow the circle rather than the target and the bar
  would be carrying a 44px blob of accent. The arithmetic is written out of
  --tap-target-min and the button's own size, so no number in here stops being
  true when either moves.
*/
.bar-button {
  --bar-button-size: calc(var(--spacing) * 9.5);

  position: relative;
  display: grid;
  place-items: center;
  flex: none;
  inline-size: var(--bar-button-size);
  block-size: var(--bar-button-size);
  border-radius: var(--radius-pill);
}

.bar-button::before {
  content: "";
  position: absolute;
  inset: calc((var(--tap-target-min) - var(--bar-button-size)) / -2);
  border-radius: inherit;
}
</style>
