<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import { GUEST_NAME_MAX, ADMIN_COMMENT_MAX } from '@/lib/validation'
import type { Booking } from '@/types/booking'

const props = defineProps<{
    eventId: number | string
    booking?: Booking | null
}>()

const open = defineModel('open', { type: Boolean, default: false })

const form = useForm({
    name: '',
    comment: '',
})

// The name this booking had when the dialog opened - CSV re-imports recognize a returning
// guest by matching this exact name, so changing it here breaks that match for next time.
const originalName = ref('')

const nameChanged = computed(() => form.name.trim().toLowerCase() !== originalName.value.trim().toLowerCase())

// Re-seed the editable fields whenever a different booking is opened for editing.
watch(() => props.booking, (booking) => {
    if (!booking) return
    form.name = booking.guest_name || booking.name || (booking.user ? booking.user.name : '')
    form.comment = booking.comment || ''
    originalName.value = form.name
    form.clearErrors()
}, { immediate: true })

const save = () => {
    if (!form.name.trim() || !props.booking) return

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
                        :maxlength="GUEST_NAME_MAX"
                        class="mt-1"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    <p v-else-if="nameChanged" class="mt-1 text-sm text-amber-600">
                        Renaming this guest means a re-imported CSV using their old name won't
                        recognize them as already booked, and may book them again.
                    </p>
                </div>
                <div>
                    <Label for="edit-comment">Comment</Label>
                    <Textarea
                        id="edit-comment"
                        v-model="form.comment"
                        placeholder="Additional notes..."
                        :maxlength="ADMIN_COMMENT_MAX"
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
