<script setup>
import { cn } from '@/lib/utils'

defineProps({
  for: String,
  class: {
    type: String,
    default: ''
  }
})
</script>

<template>
  <label
    :for="for"
    :class="cn('text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70', $props.class || '')"
  >
    <slot />
  </label>
</template>