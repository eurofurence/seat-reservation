<script setup lang="ts">
import { computed } from 'vue'
import { cva, type VariantProps } from 'class-variance-authority'
import { X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import type { Toast } from './use-toast'

interface Props {
  toast: Toast
  onClose?: (id: string) => void
}

const props = defineProps<Props>()

const toastVariants = cva(
  'group pointer-events-auto relative flex w-full items-center justify-between space-x-4 overflow-hidden rounded-md border p-6 pr-8 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-[var(--radix-toast-swipe-end-x)] data-[swipe=move]:translate-x-[var(--radix-toast-swipe-move-x)] data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-bottom-full',
  {
    variants: {
      variant: {
        default: 'border bg-background text-foreground',
        destructive:
          'destructive group border-destructive bg-destructive text-destructive-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

const toastClasses = computed(() => {
  return cn(toastVariants({ variant: props.toast.variant }))
})

const handleClose = () => {
  props.onClose?.(props.toast.id)
}
</script>

<template>
  <div :class="toastClasses">
    <div class="grid gap-1">
      <div v-if="toast.title" class="text-sm font-semibold">
        {{ toast.title }}
      </div>
      <div v-if="toast.description" class="text-sm opacity-90">
        {{ toast.description }}
      </div>
    </div>
    <button
      @click="handleClose"
      class="absolute right-2 top-2 rounded-md p-1 text-foreground/50 opacity-0 transition-opacity hover:text-foreground focus:opacity-100 focus:outline-none focus:ring-2 group-hover:opacity-100"
    >
      <X class="h-4 w-4" />
    </button>
  </div>
</template>