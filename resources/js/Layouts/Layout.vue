<script setup>
import { ref } from 'vue'

// Mobile viewport configuration
const viewportMeta = ref({
  width: 'device-width',
  initialScale: 1,
  maximumScale: 1,
  userScalable: 'no'
})
</script>

<template>
  <div class="app-container">
    <!-- Mobile-first container with Vant styling -->
    <div class="mobile-viewport">
      <slot></slot>
    </div>
  </div>
</template>

<style scoped>
.app-container {
  width: 100%;
  min-height: 100vh;
  min-height: 100dvh;
  background-color: #f7f8fa;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
}

.mobile-viewport {
  max-width: 414px;
  margin: 0 auto;
  background-color: #fff;
  min-height: 100vh;
  min-height: 100dvh;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  position: relative;
}

@media (max-width: 414px) {
  .mobile-viewport {
    max-width: 100%;
    box-shadow: none;
  }
}
</style>
