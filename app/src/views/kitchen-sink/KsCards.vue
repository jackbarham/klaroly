<template>
  <div class="space-y-8">
    <!--
      The rule, shown rather than stated: the same content twice, once lifted
      into a card and once with nothing but a heading and a hairline.
    -->
    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        A card, and the thing to use instead of one
      </p>
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-2">
          <p class="text-xs text-text-muted">
            A card. Worth it only when a group genuinely needs lifting off the
            page.
          </p>
          <Card>
            <div class="space-y-4">
              <p class="text-body font-medium text-text-strong">
                Deposit outstanding
              </p>
              <p class="text-body text-text-muted">
                Two bookings in the next fortnight have not paid a deposit.
              </p>
              <AppButton
                size="small"
                variant="secondary"
              >
                Chase both
              </AppButton>
            </div>
          </Card>
        </div>
        <div class="space-y-2">
          <p class="text-xs text-text-muted">
            The default: a heading and a hairline. The same information, less
            furniture, and it costs nothing on a phone.
          </p>
          <div class="space-y-4 border-t border-border pt-4">
            <p class="text-body font-medium text-text-strong">
              Deposit outstanding
            </p>
            <p class="text-body text-text-muted">
              Two bookings in the next fortnight have not paid a deposit.
            </p>
            <AppButton
              size="small"
              variant="secondary"
            >
              Chase both
            </AppButton>
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Card
      </p>
      <Card>
        <p>One border, one radius, one padding step, and whatever is put inside it.</p>
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        Card, with a heading and an action
      </p>
      <Card>
        <div class="space-y-4">
          <h3 class="text-lg font-semibold text-text-strong">
            Deposit
          </h3>
          <p class="text-text-muted">
            Due fourteen days after the booking is confirmed.
          </p>
          <AppButton
            size="small"
            variant="secondary"
          >
            Send a reminder
          </AppButton>
        </div>
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        EmptyState in a Card, which is how every page that has not been built
        yet says so
      </p>
      <Card>
        <EmptyState text="This part of Klaroly has not been built yet." />
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        EmptyState with another icon and an action
      </p>
      <Card>
        <EmptyState
          icon="calendar"
          text="Nothing in the diary yet."
        >
          <template #action>
            <AppButton icon="plus">
              New booking
            </AppButton>
          </template>
        </EmptyState>
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-text-muted">
        EmptyState on its own, with no card around it
      </p>
      <EmptyState
        icon="contacts"
        text="No contacts yet."
      />
    </div>
  </div>
</template>

<script setup lang="ts">
// The standard surface, and what a panel shows when it has nothing in it.
import AppButton from '@/components/ui/AppButton.vue'
import Card from '@/components/ui/Card.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
</script>
