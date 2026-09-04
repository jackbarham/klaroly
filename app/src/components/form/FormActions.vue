<template>
  <!--
    On a phone the row sticks to the bottom of the screen, directly above the
    floating tab bar, so that Save is reachable without scrolling to the end
    of a long form. On a wide screen there is no tab bar and the row simply
    ends the form.
  -->
  <div class="above-bar sticky -mx-6 flex items-center justify-end gap-2 border-t border-neutral-200 bg-surface px-6 py-4 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0 lg:pb-0">
    <AppButton
      variant="secondary"
      @click="emit('cancel')"
    >
      {{ t('common.cancel') }}
    </AppButton>
    <AppButton
      variant="primary"
      :disabled="pending"
      @click="emit('save')"
    >
      {{ t('common.save') }}
    </AppButton>
  </div>
</template>

<script setup lang="ts">
// The end of a form: cancel, then save.
import { useI18n } from 'vue-i18n'
import AppButton from '@/components/ui/AppButton.vue'

withDefaults(defineProps<{
  pending?: boolean
}>(), {
  pending: false,
})

const emit = defineEmits<{
  save: []
  cancel: []
}>()

const { t } = useI18n()
</script>
