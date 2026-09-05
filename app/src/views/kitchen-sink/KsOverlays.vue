<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Sheet. Below the lg breakpoint it is a sheet along the bottom edge;
        at lg it is a small panel under the sidebar's New button, which is
        the geometry it is built to sit in.
      </p>
      <AppButton
        variant="secondary"
        @click="sheetOpen = true"
      >
        Open the sheet
      </AppButton>
      <Sheet
        v-model:open="sheetOpen"
        label="An example sheet"
      >
        <div class="space-y-4 pb-2">
          <p class="text-sm font-medium text-text-muted">
            An example sheet
          </p>
          <p>
            Focus starts in here and stays in here. Escape closes it, so does
            a click on the scrim, and either way focus goes back to the button
            that opened it.
          </p>
          <AppButton
            variant="secondary"
            @click="sheetOpen = false"
          >
            Close
          </AppButton>
        </div>
      </Sheet>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        CreateMenu, which is the Sheet with the app's three create actions in
        it. This is a second copy of the one the shell already owns, so it
        opens in the same place the New button's does. None of the three rows
        goes anywhere yet.
      </p>
      <AppButton
        variant="secondary"
        @click="menuOpen = true"
      >
        Open the create menu
      </AppButton>
      <CreateMenu v-model:open="menuOpen" />
    </div>
  </div>
</template>

<script setup lang="ts">
// The two things that open over the page. Both are shown behind a button
// rather than left open, because how they arrive and where focus goes is
// most of what they are.
import { ref } from 'vue'
import CreateMenu from '@/components/layout/CreateMenu.vue'

const sheetOpen = ref(false)
const menuOpen = ref(false)
</script>
