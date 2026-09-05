<template>
  <div>
    <PageHeader
      :title="t('account.devices')"
      :description="t('account.devices_description')"
      :back-to="{ name: 'account' }"
    />

    <div class="space-y-6">
      <FormError :message="error" />

      <Card>
        <p
          v-if="loading"
          class="py-4 text-text-muted"
          role="status"
        >
          {{ t('account.devices_loading') }}
        </p>

        <!--
          An empty list is the ordinary state for someone who only ever uses
          the web app: a browser signs in with a session, and a session is not
          a device here. Saying nothing would read as a page that failed.
        -->
        <EmptyState
          v-else-if="devices.length === 0"
          :text="t('account.devices_empty')"
        />

        <ul v-else>
          <ListRow
            v-for="device in devices"
            :key="device.id"
          >
            <template #title>
              {{ device.name }}
            </template>

            <template #supporting>
              {{ supporting(device) }}
            </template>

            <template #trailing>
              <!--
                The current device is marked and has no revoke: signing
                yourself out from a list of devices is Sign out, and a button
                that ends the session you are using does not belong in a list
                of other people's phones.
              -->
              <StatusPill
                v-if="device.current"
                tone="info"
              >
                {{ t('account.device_current') }}
              </StatusPill>
              <AppButton
                v-else
                variant="secondary"
                size="small"
                :pending="revoking === device.id"
                :aria-label="t('account.device_revoke_label', { device: device.name })"
                @click="revoke(device)"
              >
                {{ t('account.device_revoke_action') }}
              </AppButton>
            </template>
          </ListRow>
        </ul>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
// Every phone and tablet signed in to this account. A web session is not a
// token, so a browser sees only the mobile devices and nothing is marked as
// the current one; the list says what the API says and invents no row for
// the session reading it.
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Device } from '@/types/auth'

const { t, d } = useI18n()
const auth = useAuthStore()

const devices = ref<Device[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const revoking = ref<number | null>(null)

onMounted(async () => {
  try {
    devices.value = await auth.listDevices()
  } catch {
    error.value = t('common.request_failed')
  } finally {
    loading.value = false
  }
})

// When it was last used, and when it expires. A token that has never been
// used signed in a moment ago, so the created date is the useful thing to
// show rather than an absence, which tells the reader nothing to act on.
//
// Every token this app issues has an expiry, but the column allows none, and
// a missing date is left out rather than filled in with a different one.
function supporting(device: Device): string {
  const used = device.last_used_at === null
    ? t('account.device_signed_in', { date: date(device.created_at) })
    : t('account.device_last_used', { date: date(device.last_used_at) })

  if (device.expires_at === null) {
    return used
  }

  return `${used} \u00b7 ${t('account.device_expires', { date: date(device.expires_at) })}`
}

function date(value: string): string {
  return d(new Date(value), 'date')
}

async function revoke(device: Device): Promise<void> {
  if (revoking.value !== null) {
    return
  }

  revoking.value = device.id
  error.value = null

  try {
    await auth.revokeDevice(device.id)

    devices.value = devices.value.filter((row) => row.id !== device.id)
  } catch (caught) {
    // The row stays, because it is still signed in. Removing it would show
    // an access this account no longer has, which is the wrong way round.
    error.value = caught instanceof ApiError && caught.status === 429
      ? t('common.too_many_attempts')
      : t('account.device_revoke_failed', { device: device.name })
  } finally {
    revoking.value = null
  }
}
</script>
