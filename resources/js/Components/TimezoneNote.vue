<script setup lang="ts">
import { APP_TIMEZONE } from '@/lib/datetime';
import { InfoIcon } from 'lucide-vue-next'
</script>

<template>
  <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
    <InfoIcon class="h-3.5 w-3.5 shrink-0" />
    <span>Times are shown in Eurofurence Time ({{ APP_TIMEZONE }}).</span>
  </div>
</template>
