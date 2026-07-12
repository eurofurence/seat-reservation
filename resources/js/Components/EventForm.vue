<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import DateTimePicker from '@/Components/DateTimePicker.vue'

interface Event {
  id?: number
  name: string
  room_id: string | number
  starts_at: string
  reservation_ends_at: string
  booking_starts_at: string
  max_tickets: string | number
}

interface Props {
  event?: Event
  rooms: Array<{ id: number; name: string }>
}

const props = defineProps<Props>()

const emit = defineEmits<{
  submitted: []
  cancel: []
}>()

const form = useForm({
  name: props.event?.name || '',
  room_id: props.event?.room_id ? props.event.room_id.toString() : '',
  starts_at: props.event?.starts_at || '',
  reservation_ends_at: props.event?.reservation_ends_at || '',
  booking_starts_at: props.event?.booking_starts_at || '',
  max_tickets: props.event?.max_tickets || '',
})

const isEditMode = computed(() => !!props.event?.id)

const submitForm = () => {
  form.transform((data) => ({
    ...data,
    starts_at: data.starts_at || null,
    reservation_ends_at: data.reservation_ends_at || null,
    booking_starts_at: data.booking_starts_at || null,
    max_tickets: data.max_tickets || null,
  }))

  const options = { onSuccess: () => emit('submitted') }

  if (props.event?.id) {
    form.put(route('admin.events.update', props.event.id), options)
  } else {
    form.post(route('admin.events.store'), options)
  }
}

const cancel = () => {
  emit('cancel')
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium mb-2">Event Name *</label>
      <Input
        v-model="form.name"
        placeholder="Enter event name"
        :class="{ 'border-red-500': form.errors.name }"
      />
      <span v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</span>
    </div>
    
    <div>
      <label class="block text-sm font-medium mb-2">Room *</label>
      <Select v-model="form.room_id">
        <SelectTrigger class="w-full" :class="{ 'border-red-500': form.errors.room_id }">
          <SelectValue placeholder="Select a room" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="room in rooms" :key="room.id" :value="room.id.toString()">
            {{ room.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <span v-if="form.errors.room_id" class="text-sm text-red-500">{{ form.errors.room_id }}</span>
    </div>
    
    <DateTimePicker
      v-model="form.starts_at"
      label="Event Start Date & Time"
      :error="form.errors.starts_at"
    />

    <DateTimePicker
      v-model="form.booking_starts_at"
      label="Booking Start Date & Time"
      :error="form.errors.booking_starts_at"
      hint="Leave empty to allow booking immediately"
    />

    <DateTimePicker
      v-model="form.reservation_ends_at"
      label="Reservation Deadline"
      :error="form.errors.reservation_ends_at"
      hint="Leave empty to allow booking right up until the event start"
    />

    <div>
      <label class="block text-sm font-medium mb-2">Max Tickets</label>
      <Input
        v-model="form.max_tickets"
        type="number"
        placeholder="Leave empty for unlimited"
        :class="{ 'border-red-500': form.errors.max_tickets }"
      />
      <span v-if="form.errors.max_tickets" class="text-sm text-red-500">{{ form.errors.max_tickets }}</span>
    </div>
    
    <div class="flex justify-end gap-2 pt-4">
      <Button variant="outline" @click="cancel" :disabled="form.processing">
        Cancel
      </Button>
      <Button @click="submitForm" :disabled="form.processing">
        {{ isEditMode ? 'Update Event' : 'Create Event' }}
      </Button>
    </div>
  </div>
</template>