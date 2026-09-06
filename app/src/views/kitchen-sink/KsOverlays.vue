<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Sheet. Below the lg breakpoint it is a sheet along the bottom edge;
        at lg it is a small panel in the sidebar's column, at one of its two
        anchors. This is the default one, below the New button.
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

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Sheet again, at its other anchor: above the account row at the foot of
        the sidebar, which is where AccountMenu opens. The panel below is the
        anchor on its own, so that it can be compared with the one above
        without signing anybody out.
      </p>
      <AppButton
        variant="secondary"
        @click="anchoredOpen = true"
      >
        Open the sheet above the account row
      </AppButton>
      <Sheet
        v-model:open="anchoredOpen"
        anchor="above-account"
        label="An example sheet at the lower anchor"
      >
        <div class="space-y-4 pb-2">
          <p>
            Below lg this is the same bottom sheet as the one above: the
            anchor only means anything from lg up.
          </p>
          <AppButton
            variant="secondary"
            @click="anchoredOpen = false"
          >
            Close
          </AppButton>
        </div>
      </Sheet>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        AnchoredSheet, the other panel shape. Below lg it is the same bottom
        sheet as the Sheet above; at lg it hangs under the button that opened
        it, at a position measured when it opened rather than at one of two
        fixed geometries. That measurement is why it is a separate component
        from Sheet rather than a variant of it. Open both at a wide window to
        see the difference.
      </p>
      <div class="flex flex-wrap gap-2">
        <AppButton
          ref="leftTrigger"
          variant="secondary"
          @click="leftOpen = true"
        >
          Open one aligned left
        </AppButton>
        <AppButton
          ref="rightTrigger"
          variant="secondary"
          @click="rightOpen = true"
        >
          Open one aligned right
        </AppButton>
      </div>
      <AnchoredSheet
        v-model:open="leftOpen"
        label="An anchored panel, aligned left"
        :anchor-to="leftElement"
        align="left"
        width-class="lg:w-80"
      >
        <div class="space-y-4">
          <p>
            Aligned left, so the panel's left edge sits under the button's and
            it grows rightwards. This is the month jump sheet's alignment: its
            trigger is the month title, near the left of the calendar.
          </p>
          <AppButton
            variant="secondary"
            @click="leftOpen = false"
          >
            Close
          </AppButton>
        </div>
      </AnchoredSheet>
      <AnchoredSheet
        v-model:open="rightOpen"
        label="An anchored panel, aligned right"
        :anchor-to="rightElement"
        align="right"
        width-class="lg:w-75"
      >
        <div class="space-y-4">
          <p>
            Aligned right, so the panel's right edge sits under the button's
            and it grows leftwards. This is the contacts view menu's alignment,
            which keeps three hundred pixels of panel over the list column
            rather than spilling it across the detail.
          </p>
          <AppButton
            variant="secondary"
            @click="rightOpen = false"
          >
            Close
          </AppButton>
        </div>
      </AnchoredSheet>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        AccountMenu, which is that lower anchor with the way out in it. The
        sidebar's account row opens the real one; this is a second copy and
        its Sign out really does sign out, so it is here to be looked at
        rather than clicked.
      </p>
      <AppButton
        variant="secondary"
        @click="accountOpen = true"
      >
        Open the account menu
      </AppButton>
      <AccountMenu v-model:open="accountOpen" />
    </div>
  </div>
</template>

<script setup lang="ts">
// Everything that opens over the page, each behind a button rather than left
// open, because how it arrives and where focus goes is most of what it is.
import { computed, ref, useTemplateRef } from 'vue'
import AccountMenu from '@/components/layout/AccountMenu.vue'
import CreateMenu from '@/components/layout/CreateMenu.vue'

const sheetOpen = ref(false)
const menuOpen = ref(false)
const anchoredOpen = ref(false)
const accountOpen = ref(false)

const leftOpen = ref(false)
const rightOpen = ref(false)

// AnchoredSheet measures a real rectangle, so its trigger has to be a real
// element rather than a ref to a component. AppButton's root is its <button>,
// so $el is that element.
const leftTrigger = useTemplateRef<{ $el: HTMLElement }>('leftTrigger')
const rightTrigger = useTemplateRef<{ $el: HTMLElement }>('rightTrigger')

const leftElement = computed(() => leftTrigger.value?.$el ?? null)
const rightElement = computed(() => rightTrigger.value?.$el ?? null)
</script>
