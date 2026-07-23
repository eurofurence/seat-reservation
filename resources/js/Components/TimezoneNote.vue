<script setup lang="ts">
import { computed } from 'vue'
import dayjs, { APP_TIMEZONE } from '@/lib/datetime';
import { InfoIcon } from 'lucide-vue-next'

// Europe/Berlin is CET (+01:00) in winter and CEST (+02:00) under DST.
const abbreviation = computed(() =>
  dayjs().tz(APP_TIMEZONE).utcOffset() === 120 ? 'CEST' : 'CET'
)
</script>

<template>
  <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
    <InfoIcon class="h-3.5 w-3.5 shrink-0" />
    <span>All times are in Eurofurence Time ({{ APP_TIMEZONE }} {{ abbreviation }}).</span>
  </div>
</template>
