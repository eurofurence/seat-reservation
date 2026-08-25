import { reactive, computed } from 'vue'

export interface Toast {
  id: string
  title?: string
  description?: string
  variant?: 'default' | 'destructive' | 'warning'
  duration?: number
}

interface ToastState {
  toasts: Toast[]
}

const state = reactive<ToastState>({
  toasts: []
})

let toastIdCounter = 0

export function useToast() {
  const toasts = computed(() => state.toasts)

  const addToast = (toast: Omit<Toast, 'id'>) => {
    const id = `toast-${++toastIdCounter}`
    const newToast: Toast = {
      id,
      duration: 5000,
      ...toast
    }

    // Keep only one info/success toast so rapid actions don't stack toasts over the UI.
    if ((newToast.variant ?? 'default') === 'default' && newToast.duration && newToast.duration > 0) {
      state.toasts = state.toasts.filter(
        t => t.variant === 'destructive' || t.variant === 'warning' || !t.duration
      )
    }

    state.toasts.push(newToast)

    if (newToast.duration && newToast.duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, newToast.duration)
    }

    return id
  }

  const removeToast = (id: string) => {
    const index = state.toasts.findIndex(t => t.id === id)
    if (index > -1) {
      state.toasts.splice(index, 1)
    }
  }

  const toast = (options: Omit<Toast, 'id'>) => {
    return addToast(options)
  }

  const success = (title: string, description?: string) => {
    return addToast({
      title,
      description,
      variant: 'default',
      duration: 2500,
    })
  }

  const error = (title: string, description?: string) => {
    return addToast({
      title,
      description,
      variant: 'destructive',
      duration: 0, // stays until dismissed
    })
  }

  const warning = (title: string, description?: string) => {
    return addToast({
      title,
      description,
      variant: 'warning',
      duration: 0, // stays until dismissed
    })
  }

  const info = (title: string, description?: string) => {
    return addToast({
      title,
      description,
      variant: 'default',
      duration: 2500,
    })
  }

  return {
    toasts,
    toast,
    success,
    error,
    warning,
    info,
    removeToast
  }
}
