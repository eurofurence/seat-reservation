<script setup>
// Full width layout for components that need the entire viewport
</script>

<template>
  <div class="fullwidth-container">
    <div class="fullwidth-viewport">
      <slot></slot>
    </div>
  </div>
</template>

<style scoped>
.fullwidth-container {
  width: 100%;
  min-height: 100vh;
  min-height: 100dvh;
  background-color: #f7f8fa;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica, Segoe UI, Arial, Roboto, 'PingFang SC', 'miui', 'Hiragino Sans GB', 'Microsoft Yahei', sans-serif;
}

.fullwidth-viewport {
  width: 100%;
  background-color: #fff;
  min-height: 100vh;
  min-height: 100dvh;
  position: relative;
}
</style>
