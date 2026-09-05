<template>
  <div class="space-y-8">
    <div class="space-y-4 text-sm text-text">
      <p>
        AppSidebar and AppTabBar are on the screen right now, around this
        page: the sidebar from the lg breakpoint up, the tab bar below it.
        Neither is drawn again here. Both are fixed to the viewport, so a
        second copy would escape any box put around it, and the sidebar's copy
        would come with a second account row over the real one.
        Narrow and widen the window to see them swap.
      </p>
      <p>
        SettingsNav is not fixed, so it is shown below as it really is. It is
        drawn beside a settings page from the lg breakpoint up, which the
        travel settings page shows in place. Its links go to the real settings
        pages, so clicking one leaves the kitchen sink.
      </p>
      <p>
        <RouterLink
          class="font-medium text-text-strong underline focus-visible:focus-ring"
          :to="{ name: 'settings-travel' }"
        >
          Open the travel settings page
        </RouterLink>
      </p>
    </div>

    <div class="inline-block rounded-card border border-border bg-surface-raised p-4">
      <SettingsNav />
    </div>

    <div class="space-y-2">
      <p class="text-sm text-text">
        AuthCard is the frame the four signed-out screens sit in. It is its own
        main landmark and it fills the window, which is why this page could not
        show it before: one main inside another is not a page. It now takes a
        standalone prop, and with that false it renders a section at its
        natural height, which is what is below. The four real screens pass
        nothing and are unchanged.
      </p>
      <div class="rounded-card border border-border">
        <AuthCard
          title="Sign in to Klaroly"
          :standalone="false"
        >
          <p class="text-body text-text-muted">
            The real screens put the form here. This is the frame, at its own
            width, with the wordmark above it.
          </p>
        </AuthCard>
      </div>
    </div>

    <div class="space-y-4 text-sm text-text">
      <h3 class="text-sm font-medium text-text-strong">
        Two components this page still does not show
      </h3>
      <p>
        VerificationBanner only appears for an account whose email address is
        unverified, and its button really does ask the API to send another
        email. This page makes no requests, so it is left out.
      </p>
      <p>
        UpdateBar belongs to the service worker, which the mobile build does
        not have, and it appears only when a newer build is waiting. Importing
        it here would put it in a bundle it is deliberately kept out of.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
// The frame rather than the page: the parts of the shell that are around
// this page rather than in it.
import { RouterLink } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import SettingsNav from '@/components/layout/SettingsNav.vue'
</script>
