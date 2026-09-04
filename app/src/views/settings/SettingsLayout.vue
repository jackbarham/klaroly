<template>
  <div class="lg:flex lg:gap-12">
    <!--
      On a wide screen a settings page keeps the list of groups beside it. The
      index needs no such column, because the index is that list.
    -->
    <SettingsNav
      v-if="route.name !== 'settings'"
      class="hidden lg:block"
    />
    <div class="min-w-0 flex-1">
      <RouterView />
    </div>
  </div>
</template>

<script setup lang="ts">
// The frame around every settings page. It holds the second column of links
// and nothing else.
import { RouterView, useRoute } from 'vue-router'
import SettingsNav from '@/components/layout/SettingsNav.vue'

const route = useRoute()
</script>
