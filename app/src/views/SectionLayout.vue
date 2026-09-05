<template>
  <div class="lg:flex lg:gap-12">
    <SectionNav
      class="hidden lg:block"
      :groups="groups"
      :index-route-name="indexRouteName"
      :label-key="labelKey"
    />
    <div class="min-w-0 flex-1">
      <RouterView />
    </div>
  </div>
</template>

<script setup lang="ts">
// The frame around every page in a section that is a list of pages. It holds
// the second column of links and nothing else.
//
// Settings and My account both use it, and the three things that differ are
// static props on the route record in router/index.ts, which is already the
// one place every destination in the app is written down.
import { RouterView } from 'vue-router'
import SectionNav from '@/components/layout/SectionNav.vue'
import type { SectionGroup } from '@/lib/navigation'

defineProps<{
  groups: SectionGroup[]
  indexRouteName: string
  labelKey: string
}>()
</script>
