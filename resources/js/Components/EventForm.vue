<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Calendar } from '@/Components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { CalendarIcon } from 'lucide-vue-next'
import { cn } from '@/lib/utils'
import { DateFormatter, getLocalTimeZone, parseDate, CalendarDate } from '@internationalized/date'
import dayjs from 'dayjs'

interface Event {
  id?: number
  name: string
  room_id: string | number
  starts_at: string
  reservation_ends_at: string
  max_tickets: string | number
}

interface Props {
  event?: Event
  rooms: Array<{ id: number; name: string }>
  isLoading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false
})

const emit = defineEmits<{
  submit: [data: any]
  cancel: []
}>()

const df = new DateFormatter('en-US', {
  dateStyle: 'medium'
})

// Convert datetime string to CalendarDate for the date picker
const parseDateTime = (dateTimeString: string) => {
  if (!dateTimeString) return undefined
  try {
    const date = new Date(dateTimeString)
    return parseDate(date.toISOString().split('T')[0])
  } catch {
    return undefined
  }
}

// Convert datetime string to time string for time input
const parseTime = (dateTimeString: string) => {
  if (!dateTimeString) return ''
  try {
    const date = new Date(dateTimeString)
    return date.toTimeString().slice(0, 5) // HH:MM format
  } catch {
    return ''
  }
}

const form = useForm({
  name: props.event?.name || '',
  room_id: props.event?.room_id ? props.event.room_id.toString() : '',
  starts_at: props.event?.starts_at || '',
  reservation_ends_at: props.event?.reservation_ends_at || '',
  max_tickets: props.event?.max_tickets || '',
})

// Separate date and time fields for better UX
const startsAtDate = ref<CalendarDate | undefined>(parseDateTime(props.event?.starts_at || ''))
const startsAtTime = ref(parseTime(props.event?.starts_at || ''))
const reservationEndsAtDate = ref<CalendarDate | undefined>(parseDateTime(props.event?.reservation_ends_at || ''))
const reservationEndsAtTime = ref(parseTime(props.event?.reservation_ends_at || ''))

// Combine date and time into datetime string
const combineDateTime = (date: CalendarDate | undefined, time: string) => {
  if (!date || !time) return ''
  try {
    const dateStr = date.toString() // YYYY-MM-DD format
    return `${dateStr}T${time}:00`
  } catch {
    return ''
  }
}

// Watch for date/time changes and update form
watch([startsAtDate, startsAtTime], ([date, time]) => {
  form.starts_at = combineDateTime(date, time)
})

watch([reservationEndsAtDate, reservationEndsAtTime], ([date, time]) => {
  form.reservation_ends_at = combineDateTime(date, time)
})

const submitForm = () => {
  const data = { ...form.data() }
  
  // Convert empty strings to null for datetime fields
  if (!data.starts_at) data.starts_at = null
  if (!data.reservation_ends_at) data.reservation_ends_at = null
  if (!data.max_tickets) data.max_tickets = null
  
  emit('submit', data)
}

const cancel = () => {
  emit('cancel')
}

const isEditMode = computed(() => !!props.event?.id)
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
    
    <div>
      <label class="block text-sm font-medium mb-2">Event Start Date & Time</label>
      <div class="flex gap-2">
        <div class="flex-1">
          <Popover>
            <PopoverTrigger as-child>
              <Button
                variant="outline"
                :class="cn(
                  'w-full justify-start text-left font-normal',
                  !startsAtDate && 'text-muted-foreground',
                  form.errors.starts_at && 'border-red-500'
                )"
              >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ startsAtDate ? df.format(startsAtDate.toDate(getLocalTimeZone())) : "Pick a date" }}
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0">
              <Calendar v-model="startsAtDate" initial-focus />
            </PopoverContent>
          </Popover>
        </div>
        <div class="w-32">
          <Input
            v-model="startsAtTime"
            type="time"
            placeholder="HH:MM"
            :class="{ 'border-red-500': form.errors.starts_at }"
          />
        </div>
      </div>
      <span v-if="form.errors.starts_at" class="text-sm text-red-500">{{ form.errors.starts_at }}</span>
    </div>
    
    <div>
      <label class="block text-sm font-medium mb-2">Reservation Deadline</label>
      <div class="flex gap-2">
        <div class="flex-1">
          <Popover>
            <PopoverTrigger as-child>
              <Button
                variant="outline"
                :class="cn(
                  'w-full justify-start text-left font-normal',
                  !reservationEndsAtDate && 'text-muted-foreground',
                  form.errors.reservation_ends_at && 'border-red-500'
                )"
              >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ reservationEndsAtDate ? df.format(reservationEndsAtDate.toDate(getLocalTimeZone())) : "Pick a date" }}
              </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0">
              <Calendar v-model="reservationEndsAtDate" initial-focus />
            </PopoverContent>
          </Popover>
        </div>
        <div class="w-32">
          <Input
            v-model="reservationEndsAtTime"
            type="time"
            placeholder="HH:MM"
            :class="{ 'border-red-500': form.errors.reservation_ends_at }"
          />
        </div>
      </div>
      <span v-if="form.errors.reservation_ends_at" class="text-sm text-red-500">{{ form.errors.reservation_ends_at }}</span>
    </div>
    
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
      <Button variant="outline" @click="cancel" :disabled="isLoading">
        Cancel
      </Button>
      <Button @click="submitForm" :disabled="isLoading">
        {{ isEditMode ? 'Update Event' : 'Create Event' }}
      </Button>
    </div>
  </div>
</template>