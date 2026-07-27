<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { useCsvDownload } from '@/composables/useCsvDownload'
import ImportFileDropzone from '@/Components/Admin/ImportFileDropzone.vue'

// Without eventId: global import (all events, CSV grouped by an Event column).
// With eventId: single-event import.
const props = defineProps<{
    eventId?: number | string
}>()

const open = defineModel('open', { type: Boolean, default: false })

const { download } = useCsvDownload()

const file = ref<File | null>(null)
const isProcessing = ref(false)
const fileInputError = ref(false)
const dropzoneRef = ref<InstanceType<typeof ImportFileDropzone> | null>(null)
const form = useForm({ file: null as File | null })

const isGlobal = computed(() => !props.eventId)
const templateRoute = computed(() => isGlobal.value
    ? route('admin.import-bookings.template')
    : route('admin.events.import-bookings.template', props.eventId))
const proposeRoute = computed(() => isGlobal.value
    ? route('admin.import-bookings.propose')
    : route('admin.events.import-bookings.propose', props.eventId))

watch(file, (selected) => {
    if (selected) {
        fileInputError.value = false
        form.clearErrors('file')
    }
})

const downloadTemplate = () => {
    download(templateRoute.value, isGlobal.value ? 'booking-import-template-all-events.csv' : 'booking-import-template.csv')
}

const handleSubmitClick = () => {
    if (!file.value) {
        fileInputError.value = true
        dropzoneRef.value?.focus()
        return
    }

    submit()
}

const submit = () => {
    form.file = file.value

    isProcessing.value = true

    form.post(proposeRoute.value, {
        forceFormData: true,
        onSuccess: () => {
            open.value = false
            file.value = null
        },
        onFinish: () => {
            isProcessing.value = false
        },
    })
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ isGlobal ? 'Import Bookings (All Events)' : 'Import Bookings' }}</DialogTitle>
            </DialogHeader>
            <div class="py-4 space-y-4">
                <p v-if="isGlobal" class="text-sm text-muted-foreground">
                    Upload a CSV with columns <code>Event, Guest Name, Comment, Block, Row, Seat</code> -
                    covering multiple events at once (e.g. one shared sign-up form). Rows are grouped by
                    the <code>Event</code> column (must match an existing event's name) and processed
                    <strong>one event at a time</strong>: you'll review and confirm each event's seat
                    assignment before it's booked, then automatically continue to the next event.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Upload a CSV with columns <code>Guest Name, Comment, Block, Row, Seat</code>.
                    Fill in Block/Row/Seat for an exact seat, or leave all three blank and repeat
                    the same guest name across multiple rows to auto-assign that many seats.
                    You'll review and can adjust the seat assignment before anything is booked.
                </p>
                <button type="button" class="text-sm text-primary underline" @click="downloadTemplate">
                    Download CSV Template
                </button>
                <div>
                    <Label for="import-file">CSV File</Label>
                    <ImportFileDropzone
                        id="import-file"
                        ref="dropzoneRef"
                        v-model="file"
                        :error="fileInputError || !!form.errors.file"
                        class="mt-1"
                    />
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">{{ form.errors.file }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    @click="open = false"
                    :disabled="isProcessing"
                >
                    Cancel
                </Button>
                <Button
                    @click="handleSubmitClick"
                    :disabled="isProcessing"
                    :class="!file && 'opacity-50'"
                >
                    {{ isProcessing ? 'Uploading...' : 'Upload & Preview' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
