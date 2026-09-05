<template>
  <div class="space-y-8">
    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        IconButton
      </h3>
      <!--
        IconButton has one size and one appearance. Its label is required and
        becomes the accessible name, so the two buttons below are told apart
        by a screen reader even though they show the same shape.
      -->
      <div class="flex flex-wrap items-center gap-4">
        <div class="space-y-2">
          <IconButton
            icon="more"
            label="More options"
          />
          <p class="text-xs text-text-muted">
            Default
          </p>
        </div>
        <div class="space-y-2">
          <IconButton
            icon="more"
            label="More options, unavailable"
            disabled
          />
          <p class="text-xs text-text-muted">
            Disabled
          </p>
        </div>
        <div class="space-y-2">
          <IconButton
            icon="chevron-left"
            label="Previous month"
          />
          <p class="text-xs text-text-muted">
            Another icon
          </p>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <h3 class="text-sm font-medium text-text">
        Icon
      </h3>
      <ul class="grid grid-cols-3 gap-4 sm:grid-cols-4 lg:grid-cols-6">
        <li
          v-for="icon in iconNames"
          :key="icon"
          class="flex flex-col items-center gap-2 rounded-card border border-border bg-surface-raised py-6"
        >
          <Icon
            :name="icon"
            class="h-6 w-6 text-text-strong"
          />
          <p class="font-mono text-xs text-text-muted">
            {{ icon }}
          </p>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
// Every icon the app owns. There is no icon package: this is the whole set,
// and anything that needs a shape the app does not have is a conversation
// rather than an npm install.
import Icon from '@/components/ui/Icon.vue'
import IconButton from '@/components/ui/IconButton.vue'
import { iconNames } from '@/views/kitchen-sink/tokens'
</script>
