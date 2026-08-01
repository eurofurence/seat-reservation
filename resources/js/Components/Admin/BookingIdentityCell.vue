<script setup lang="ts">
// Shared "who booked this" cell for the Dashboard recent-bookings table and the
// EventShow booking table: display name + creator/booker badge. Use the default
// slot for page-specific extras (e.g. EventShow's comment popover).
import { getGuestName, getBookerType, getBookerName } from '@/lib/bookingDisplay'
import { computed } from 'vue'
import type { Booking } from '@/types/layout'

const props = defineProps<{
  booking: Booking
}>()

const info = computed(() => ({
  displayName: getGuestName(props.booking),
  bookerType: getBookerType(props.booking),
  identityName: getBookerName(props.booking),
}))
</script>

<template>
  <div class="text-sm font-medium">{{ info.displayName }}</div>
  <div class="text-xs text-muted-foreground flex items-center gap-1">
    <span v-if="info.identityName">{{ info.identityName }} <span class="text-muted-foreground">({{ info.bookerType }})</span></span>
    <span v-else>{{ info.bookerType }}</span>
    <slot />
  </div>
</template>
