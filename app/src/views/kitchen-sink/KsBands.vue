<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        A band that does not collapse
      </p>
      <SectionBand title="Pricing">
        <p class="text-body text-text-muted">
          The fields belonging to this section sit here, on the page, rather
          than inside a bordered panel with a header of its own.
        </p>
      </SectionBand>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        A band that collapses. It is a button, so it takes a ring on focus:
        there is no edge of its own to recolour.
      </p>
      <SectionBand
        title="Travel and charges"
        collapsible
      >
        <p class="text-body text-text-muted">
          Click the bar. The chevron turns, the content goes away, and the
          button says which of the two it is with aria-expanded.
        </p>
      </SectionBand>
    </div>
  </div>
</template>

<script setup lang="ts">
// The sunken heading bar that a section of a page gets instead of a nested
// panel.
import SectionBand from '@/components/ui/SectionBand.vue'
</script>
