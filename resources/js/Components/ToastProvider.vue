<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Toaster, useToast } from '@/Components/ui/toast'

const page = usePage()
const { success, error, warning, info } = useToast()

type FlashMessages = {
  id?: string
  success?: string
  error?: string
  warning?: string
  info?: string
}

// track the last flash id we showed so we can skip showing if it appears again due to a reloads
let lastFlashId: string | undefined

// Show toast for flash messages
const showFlashMessages = () => {
  const flash = page.props.flash as FlashMessages | null | undefined

  if (!flash?.id || flash.id === lastFlashId) {
    return
  }
  lastFlashId = flash.id

  if (flash.success) {
    success('Success', flash.success)
  }
  if (flash.error) {
    error('Error', flash.error)
  }
  if (flash.warning) {
    warning('Warning', flash.warning)
  }
  if (flash.info) {
    info('Info', flash.info)
  }
}

// Check for flash messages on mount
onMounted(() => {
  showFlashMessages()
})

// fires on every reload/refresh/redirect, so we check if we got a new page prop message
const removeListener = router.on('finish', () => {
  showFlashMessages()
})

onUnmounted(() => {
  removeListener()
})

</script>

<template>
  <Toaster />
</template>
