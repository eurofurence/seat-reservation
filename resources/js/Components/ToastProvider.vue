<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Toaster, useToast } from '@/Components/ui/toast'

const page = usePage()
const { success, error, warning, info } = useToast()

// Show toast for flash messages
const showFlashMessages = () => {
  const flash = page.props.flash
  
  if (flash?.success) {
    success('Success', flash.success)
  }
  if (flash?.error) {
    error('Error', flash.error)
  }
  if (flash?.warning) {
    warning('Warning', flash.warning)
  }
  if (flash?.info) {
    info('Info', flash.info)
  }
}

// Check for flash messages on mount
onMounted(() => {
  showFlashMessages()
})

// Watch for page prop changes to show flash messages
watch(() => page.props.flash, (newFlash, oldFlash) => {
  // Show toast if there's any flash message, regardless of whether it changed
  // This ensures toasts show on every form submission
  if (newFlash && (
    newFlash.success || 
    newFlash.error || 
    newFlash.warning || 
    newFlash.info
  )) {
    setTimeout(() => {
      showFlashMessages()
    }, 100)
  }
}, { deep: true })

</script>

<template>
  <Toaster />
</template>