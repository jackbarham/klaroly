<template>
  <Sheet
    v-model:open="open"
    anchor="above-account"
    :label="t('account.menu_label')"
  >
    <ul :class="sheetListClasses">
      <li>
        <button
          :class="sheetRowClasses"
          type="button"
          @click="signOut"
        >
          <Icon
            name="sign-out"
            :class="sheetRowIconClasses"
          />
          {{ t('auth.sign_out_action') }}
        </button>
      </li>
    </ul>
  </Sheet>
</template>

<script setup lang="ts">
// What the account row at the foot of the sidebar opens. It is Sheet, like
// the create menu, so a phone gets a bottom sheet and a wide screen gets a
// small menu above the row that opened it.
//
// Sign out is the only row so far. The account and store rows this will also
// carry are waiting on the screens behind them.
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { sheetListClasses, sheetRowClasses, sheetRowIconClasses } from '@/components/ui/Sheet.vue'
import { useAuthStore } from '@/stores/auth'

const open = defineModel<boolean>('open', { required: true })

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

async function signOut(): Promise<void> {
  open.value = false
  await auth.signOut()
  await router.push({ name: 'login' })
}
</script>
