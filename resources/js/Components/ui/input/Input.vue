<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { nextTick, ref, watch } from "vue"
import { cn } from "@/lib/utils"
import { coerceInputValue } from "./coerceInputValue"

const props = defineProps<{
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

// Ignore input events while an IME composition is in progress (mirrors Vue's native
// v-model behavior) and process the final composed value once it ends.
const composing = ref(false)

const commit = (target: HTMLInputElement) => {
  const castNumber = props.modelModifiers?.number || target.type === "number"
  const value = coerceInputValue(target.value, { trim: props.modelModifiers?.trim, number: castNumber })
  emits("update:modelValue", value)
  nextTick(syncFromModel)
}

const onInput = (event: Event) => {
  if (composing.value) return
  commit(event.target as HTMLInputElement)
}

const onCompositionStart = () => {
  composing.value = true
}

const onCompositionEnd = (event: Event) => {
  composing.value = false
  commit(event.target as HTMLInputElement)
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
    @compositionstart="onCompositionStart"
    @compositionend="onCompositionEnd"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>
