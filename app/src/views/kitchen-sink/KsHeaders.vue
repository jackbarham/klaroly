<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        Title only
      </p>
      <div class="rounded-card border border-neutral-200 p-6">
        <PageHeader title="Bookings" />
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        With a description
      </p>
      <div class="rounded-card border border-neutral-200 p-6">
        <PageHeader
          title="Bookings"
          description="Everything you have in the diary, soonest first."
        />
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        With actions
      </p>
      <div class="rounded-card border border-neutral-200 p-6">
        <PageHeader title="Bookings">
          <template #actions>
            <IconButton
              icon="more"
              label="More options"
            />
            <AppButton size="small">
              New booking
            </AppButton>
          </template>
        </PageHeader>
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        With a description, actions and a back link. The back link is drawn
        below the lg breakpoint only, because a wide screen has the sidebar
        to go back to instead.
      </p>
      <div class="rounded-card border border-neutral-200 p-6">
        <PageHeader
          title="Travel"
          description="Where you are based and what you charge to get to a booking."
          :back-to="{ name: 'settings' }"
        >
          <template #actions>
            <AppButton
              size="small"
              variant="secondary"
            >
              Save
            </AppButton>
          </template>
        </PageHeader>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// PageHeader is the top of every page, so each example below brings its own
// h1 with it. That is four more h1 elements than a page should have, and it
// is the price of showing the component rather than re-drawing it.
//
// The actions slot is shown here for the first time: no screen in the app
// passes one yet.
import AppButton from '@/components/ui/AppButton.vue'
import IconButton from '@/components/ui/IconButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
</script>
