<template>
  <div class="space-y-8">
    <div class="space-y-4 text-sm text-text">
      <p>
        AppSidebar, AppTabBar and AppTopBar are on the screen right now, around
        this page: the sidebar from the lg breakpoint up, the two phone bars
        below it. None of the three is drawn again here. All three are fixed to
        the viewport, so a second copy would escape any box put around it, and
        the sidebar's copy would come with a second account row over the real
        one. Narrow and widen the window to see them swap.
      </p>
      <p>
        The top bar is worth narrowing for on this page in particular: it says
        the route's own name, which here is Kitchen sink, it is transparent
        until the page moves under it and then takes the same glass as the tab
        bar, and its two buttons are the notifications sheet and the create
        sheet. Both bars are one material, exported once from barGlass.ts.
      </p>
      <p>
        SectionNav is not fixed, so it is shown below as it really is. It is
        drawn beside a page in a section that is itself a list of pages, from
        the lg breakpoint up, which the travel settings page shows in place.
        It is one component given a different array, so both sections it
        serves are below. Its links go to the real pages, so clicking one
        leaves the kitchen sink. On a section's own index it renders nothing,
        because the index is that list.
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

    <div class="flex flex-wrap gap-4">
      <div class="inline-block rounded-card border border-border bg-surface-raised p-4">
        <SectionNav
          :groups="settingsGroups"
          index-route-name="settings"
          label-key="settings.title"
        />
      </div>
      <div class="inline-block rounded-card border border-border bg-surface-raised p-4">
        <SectionNav
          :groups="accountGroups"
          index-route-name="account"
          label-key="account.title"
        />
      </div>
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
        UpdateBar
      </h3>
      <p>
        It appears only when a newer build is waiting, which is not something
        that can be arranged on demand, so this turns on the flag it watches
        instead. The bar is already in the page: App.vue renders it on the web
        target, fixed to the bottom of the window, so the real one appears
        rather than a copy drawn here.
      </p>
      <p>
        It stays on while you move around the app, which is the point of it,
        and it goes away when you turn it off or reload the page. Its own
        Reload button does what it says: it reloads.
      </p>
      <AppButton
        v-if="webTarget"
        variant="secondary"
        size="small"
        @click="toggleUpdateBar"
      >
        {{ showingUpdateBar ? 'Hide the update bar' : 'Show the update bar' }}
      </AppButton>
      <p v-else>
        Not on this build. The bar belongs to the service worker, which the
        mobile target does not have.
      </p>
    </div>

    <div class="space-y-4 text-sm text-text">
      <h3 class="text-sm font-medium text-text-strong">
        The one component this page still does not show
      </h3>
      <p>
        VerificationBanner only appears for an account whose email address is
        unverified, and its button really does ask the API to send another
        email. This page makes no requests, so it is left out.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
// The frame rather than the page: the parts of the shell that are around
// this page rather than in it.
import { ref, type Ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthCard from '@/components/AuthCard.vue'
import SectionNav from '@/components/layout/SectionNav.vue'
import { accountGroups, settingsGroups } from '@/lib/navigation'

// The update bar cannot be shown the way the other pieces are, because it is
// fixed to the window and because App.vue is already rendering it. What it
// waits for is one ref in src/lib/updates.ts, so the button sets that and the
// real bar appears, in its real place, over whatever page you are on.
//
// The import is dynamic and inside the branch for the same reason it is in
// App.vue: src/lib/updates.ts reaches for the service worker registration,
// which only the web target has, so the mobile build must never contain it.
const webTarget = __WEB_TARGET__
const showingUpdateBar = ref(false)

let updateAvailable: Ref<boolean> | null = null

async function toggleUpdateBar(): Promise<void> {
  if (__WEB_TARGET__) {
    updateAvailable ??= (await import('@/lib/updates')).updateAvailable

    showingUpdateBar.value = !showingUpdateBar.value
    updateAvailable.value = showingUpdateBar.value
  }
}
</script>
