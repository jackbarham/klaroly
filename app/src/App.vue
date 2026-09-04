<template>
  <RouterView />
  <component
    :is="UpdateBar"
    v-if="UpdateBar"
  />
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import { RouterView } from 'vue-router'

// The update bar exists only on the web target, where there is a service
// worker to update. Same pattern as the billing route: the import stays
// dynamic and inside the branch so the mobile bundle never contains it.
const UpdateBar = __WEB_TARGET__
  ? defineAsyncComponent(() => import('@/components/UpdateBar.vue'))
  : null
</script>
