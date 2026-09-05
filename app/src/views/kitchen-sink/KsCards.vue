<template>
  <div class="space-y-8">
    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        Card
      </p>
      <Card>
        <p>One border, one radius, one padding step, and whatever is put inside it.</p>
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
        Card, with a heading and an action
      </p>
      <Card>
        <div class="space-y-4">
          <h3 class="text-lg font-semibold text-neutral-900">
            Deposit
          </h3>
          <p class="text-neutral-500">
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
      <p class="text-xs font-medium text-neutral-500">
        EmptyState in a Card, which is how every page that has not been built
        yet says so
      </p>
      <Card>
        <EmptyState text="This part of Klaroly has not been built yet." />
      </Card>
    </div>

    <div class="space-y-2">
      <p class="text-xs font-medium text-neutral-500">
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
      <p class="text-xs font-medium text-neutral-500">
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
