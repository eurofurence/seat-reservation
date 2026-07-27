<script setup lang="ts">
import { ref } from 'vue'

withDefaults(defineProps<{
    error?: boolean
}>(), {
    error: false,
})

const file = defineModel<File | null>({ default: null })

const inputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

const setFile = (selected: File | undefined | null) => {
    file.value = selected || null
}

const onChange = (event: Event) => setFile((event.target as HTMLInputElement).files?.[0])

const onDrop = (event: DragEvent) => {
    isDragging.value = false
    setFile(event.dataTransfer?.files?.[0])
}

defineExpose({
    focus: () => inputRef.value?.focus(),
})
</script>

<template>
    <label
        :class="[
            'flex flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed px-4 py-6 text-center text-sm cursor-pointer transition-colors',
            'focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
            isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/40 hover:border-muted-foreground/60 hover:bg-accent/50',
            error ? 'border-destructive ring-2 ring-destructive/20' : '',
        ]"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
    >
        <input
            ref="inputRef"
            type="file"
            accept=".csv,.txt"
            class="sr-only"
            @change="onChange"
        />
        <template v-if="file">
            <span class="font-medium text-foreground">{{ file.name }}</span>
            <span class="text-xs text-muted-foreground">Click or drop another file to replace it</span>
        </template>
        <template v-else>
            <span class="font-medium text-foreground">Click to choose a CSV file</span>
            <span class="text-xs text-muted-foreground">or drag and drop it here</span>
        </template>
    </label>
</template>
