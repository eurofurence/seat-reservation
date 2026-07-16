<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'

const props = defineProps({
    eventId: [Number, String],
    booking: Object,
})

const open = defineModel('open', { type: Boolean, default: false })

const form = useForm({
    name: '',
    comment: '',
})

// Re-seed the editable fields whenever a different booking is opened for editing.
watch(() => props.booking, (booking) => {
    if (!booking) return
    form.name = booking.guest_name || booking.name || (booking.user ? booking.user.name : '')
    form.comment = booking.comment || ''
    form.clearErrors()
}, { immediate: true })

const save = () => {
    if (!form.name.trim()) return

    form.put(route('admin.events.update-booking', [props.eventId, props.booking.id]), {
        onSuccess: () => {
            open.value = false
        },
    })
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Edit Booking</DialogTitle>
            </DialogHeader>
            <div class="space-y-4 py-4">
                <div>
                    <Label for="edit-name">Name</Label>
                    <Input
                        id="edit-name"
                        v-model="form.name"
                        placeholder="Name or team"
                        class="mt-1"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <Label for="edit-comment">Comment</Label>
                    <Textarea
                        id="edit-comment"
                        v-model="form.comment"
                        placeholder="Additional notes..."
                        class="mt-1"
                        rows="3"
                    />
                    <p v-if="form.errors.comment" class="mt-1 text-sm text-red-600">{{ form.errors.comment }}</p>
                </div>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    @click="open = false"
                    :disabled="form.processing"
                >
                    Cancel
                </Button>
                <Button
                    @click="save"
                    :disabled="form.processing || !form.name.trim()"
                >
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
