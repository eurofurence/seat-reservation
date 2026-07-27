<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { nextTick, ref, watch } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  modelModifiers?: { number?: boolean, trim?: boolean }
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const inputRef = ref<HTMLInputElement | null>(null)

// Controlled input: force the DOM back to modelValue so a parent that rejects an edit (leaving
// modelValue unchanged, which skips the re-render) still resets the field.
const syncFromModel = () => {
  if (inputRef.value && inputRef.value.value !== String(props.modelValue ?? "")) {
    inputRef.value.value = String(props.modelValue ?? "")
  }
}

const onInput = (event: Event) => {
  let value: string | number = (event.target as HTMLInputElement).value
  if (props.modelModifiers?.trim) value = value.trim()
  if (props.modelModifiers?.number) {
    const parsed = parseFloat(value)
    value = isNaN(parsed) ? value : parsed
  }
  emits("update:modelValue", value)
  nextTick(syncFromModel)
}

watch(() => props.modelValue, () => nextTick(syncFromModel))

defineExpose({
  focus: () => inputRef.value?.focus(),
  el: inputRef,
})
</script>

<template>
  <input
    ref="inputRef"
    :value="modelValue"
    @input="onInput"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>
