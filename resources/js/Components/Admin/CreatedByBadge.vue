<script setup lang="ts">
// Shows which admin created a booking (manual entry or CSV import), e.g. "Admin Booking\n[Jane Admin]".
// Renders nothing if the booking has no created_by_name (e.g. online/self-service bookings).
defineProps<{
  name?: string | null
  label?: string
}>()
</script>

<template>
  <span v-if="name"><template v-if="label">{{ label }}</template><br>[{{ name }}]</span>
</template>
