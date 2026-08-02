<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import type { Booking } from '@/types/booking'

const props = defineProps<{
    eventId: number | string
    booking: Booking | null
    displayName: string
    seatInfo: string
}>()

const open = defineModel('open', { type: Boolean, default: false })

const isProcessing = ref(false)

const remove = () => {
    if (!props.booking) return

    const form = useForm({})

    isProcessing.value = true

    form.delete(route('admin.events.delete-booking', [props.eventId, props.booking.id]), {
        onSuccess: () => {
            open.value = false
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
                <DialogTitle>Delete Booking</DialogTitle>
            </DialogHeader>
            <div class="py-4">
                <p class="text-sm text-muted-foreground">
                    Are you sure you want to delete this booking?
                </p>
                <div v-if="booking" class="mt-4 p-3 bg-gray-50 rounded-md">
                    <div class="text-sm">
                        <div><strong>Name:</strong> {{ displayName }}</div>
                        <div><strong>Seat:</strong> {{ seatInfo }}</div>
                    </div>
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
                    variant="destructive"
                    @click="remove"
                    :disabled="isProcessing"
                >
                    {{ isProcessing ? 'Deleting...' : 'Delete Booking' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
