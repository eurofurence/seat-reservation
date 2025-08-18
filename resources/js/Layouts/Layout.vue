<script setup>
import { ref } from 'vue'

// Responsive viewport configuration
const viewportMeta = ref({
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
  userScalable: 'no'
})
</script>

<template>
  <div class="layout-container">
    <slot></slot>
  </div>
</template>

<style scoped>
.layout-container {
  width: 100%;
  min-height: 100vh;
  min-height: 100dvh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}
</style>
