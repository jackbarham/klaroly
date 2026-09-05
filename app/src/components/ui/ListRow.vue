<template>
  <!--
    The divider belongs to the row, and hovering recolours it. The first row
    carries a top one as well, so an ordinary ul gets the hairline above it
    without a wrapper component to remember.
  -->
  <li class="border-b border-border transition-colors duration-200 first:border-t hover:border-accent">
    <!--
      A row that goes somewhere is one link across its whole width, which is
      the right target for a thumb. The ring is inset because the row is as
      wide as the list: an outer ring would sit over the dividers above and
      below it.
    -->
    <component
      :is="to ? RouterLink : 'div'"
      class="flex items-center gap-4 rounded-control py-row focus-visible:focus-ring focus-visible:-outline-offset-2"
      v-bind="linkProps"
    >
      <div
        v-if="$slots.leading"
        class="shrink-0"
      >
        <slot name="leading" />
      </div>

      <div class="min-w-0 grow">
        <div class="truncate text-body font-medium text-text-strong">
          <slot name="title" />
        </div>
        <div
          v-if="$slots.supporting"
          class="truncate text-body text-text-muted"
        >
          <slot name="supporting" />
        </div>
      </div>

      <div
        v-if="$slots.trailing"
        class="shrink-0 text-right"
      >
        <slot name="trailing" />
      </div>
    </component>
  </li>
</template>

<script setup lang="ts">
// One row of a list, and the default way this product shows a list of
// anything. The table is the same data on a wide screen.
//
// It is an li, so it belongs in a ul. Everything in it comes through a slot,
// because a leading element is an avatar or a set of initials, a trailing
// block is usually a figure with a pill under it, and no set of props would
// describe either without growing one for every screen.
import { computed } from 'vue'
import { RouterLink, type RouteLocationRaw } from 'vue-router'

const props = defineProps<{
  to?: RouteLocationRaw
}>()

// A div takes no "to", so the prop is passed only when there is a link to make
// of it.
const linkProps = computed(() => (props.to ? { to: props.to } : {}))
</script>
