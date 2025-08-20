<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Table } from '@/Components/ui/table'
import { Plus, Edit, Trash2, Calendar, Clock, MapPin, Users } from 'lucide-vue-next'
import dayjs from 'dayjs'
import { Dialog } from '@/Components/ui/dialog'
import { DialogContent } from '@/Components/ui/dialog'
import { DialogHeader } from '@/Components/ui/dialog'
import { DialogTitle } from '@/Components/ui/dialog'
import { DialogTrigger } from '@/Components/ui/dialog'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  events: Array,
  rooms: Array,
  title: String,
  breadcrumbs: Array,
})

const dialogOpen = ref(false)

const eventForm = useForm({
  name: '',
  room_id: '',
  starts_at: '',
  reservation_ends_at: '',
  max_tickets: '',
})

const createEvent = () => {
  if (eventForm.name.trim() && eventForm.room_id) {
    const data = { ...eventForm.data() }
    
    // Convert empty strings to null for datetime fields
    if (!data.starts_at) data.starts_at = null
    if (!data.reservation_ends_at) data.reservation_ends_at = null
    if (!data.max_tickets) data.max_tickets = null
    
    eventForm.transform(() => data).post(route('admin.events.store'), {
      onSuccess: () => {
        dialogOpen.value = false
        eventForm.reset()
      }
    })
  }
}

const deleteEvent = (event) => {
  if (confirm(`Are you sure you want to delete "${event.name}"? This will also delete all bookings for this event.`)) {
    router.delete(route('admin.events.destroy', event.id))
  }
}

const viewEvent = (event) => {
  router.visit(`/admin/events/${event.id}`)
}

const getEventStatus = (event) => {
  const now = dayjs()
  const eventStart = dayjs(event.starts_at)
  const reservationEnd = dayjs(event.reservation_ends_at)
  
  if (now.isAfter(eventStart)) {
    return { text: 'Completed', class: 'bg-gray-100 text-gray-800' }
  }
  if (event.reservation_ends_at && now.isAfter(reservationEnd)) {
    return { text: 'Locked', class: 'bg-orange-100 text-orange-800' }
  }
  return { text: 'Active', class: 'bg-green-100 text-green-800' }
}
</script>

<template>
  <Head :title="title" />
  
  <div>
    <div class="flex justify-end items-center mb-6">
      <Dialog :open="dialogOpen" @update:open="dialogOpen = $event">
        <DialogTrigger as-child>
          <Button>
            <Plus class="mr-2 h-4 w-4" />
            Create Event
          </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Create New Event</DialogTitle>
          </DialogHeader>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Event Name *</label>
              <Input
                v-model="eventForm.name"
                placeholder="Enter event name"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Room *</label>
              <select 
                v-model="eventForm.room_id"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Select a room</option>
                <option v-for="room in rooms" :key="room.id" :value="room.id">
                  {{ room.name }}
                </option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Event Start Date & Time</label>
              <Input
                v-model="eventForm.starts_at"
                type="datetime-local"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Reservation Deadline</label>
              <Input
                v-model="eventForm.reservation_ends_at"
                type="datetime-local"
              />
            </div>
            
            <div>
              <label class="block text-sm font-medium mb-2">Max Tickets</label>
              <Input
                v-model="eventForm.max_tickets"
                type="number"
                placeholder="Leave empty for unlimited"
              />
            </div>
            
            <div class="flex justify-end gap-2">
              <Button variant="outline" @click="dialogOpen = false">
                Cancel
              </Button>
              <Button @click="createEvent">
                Create Event
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </div>
    
    <!-- Events Table -->
    <Card>
      <CardContent class="p-0">
        <Table>
          <thead>
            <tr class="border-b">
              <th class="text-left p-4">Event</th>
              <th class="text-left p-4">Room</th>
              <th class="text-left p-4">Date & Time</th>
              <th class="text-left p-4">Reservations</th>
              <th class="text-left p-4">Status</th>
              <th class="text-left p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id" class="border-b hover:bg-gray-50">
              <td class="p-4">
                <div>
                  <div class="font-medium">{{ event.name }}</div>
                  <div class="text-sm text-gray-500" v-if="event.max_tickets">
                    Max {{ event.max_tickets }} tickets
                  </div>
                </div>
              </td>
              <td class="p-4">
                <div class="flex items-center">
                  <MapPin class="h-4 w-4 mr-1 text-gray-400" />
                  {{ event.room?.name }}
                </div>
              </td>
              <td class="p-4">
                <div class="space-y-1">
                  <div class="flex items-center text-sm" v-if="event.starts_at">
                    <Calendar class="h-4 w-4 mr-1 text-gray-400" />
                    {{ dayjs(event.starts_at).format('MMM DD, YYYY HH:mm') }}
                  </div>
                  <div class="flex items-center text-sm text-gray-500" v-if="event.reservation_ends_at">
                    <Clock class="h-4 w-4 mr-1 text-gray-400" />
                    Reservations until {{ dayjs(event.reservation_ends_at).format('MMM DD, HH:mm') }}
                  </div>
                </div>
              </td>
              <td class="p-4">
                <div class="flex items-center">
                  <Users class="h-4 w-4 mr-1 text-gray-400" />
                  {{ event.bookings_count || 0 }} bookings
                </div>
              </td>
              <td class="p-4">
                <span 
                  class="px-2 py-1 text-xs rounded-full"
                  :class="getEventStatus(event).class"
                >
                  {{ getEventStatus(event).text }}
                </span>
              </td>
              <td class="p-4">
                <div class="flex gap-2">
                  <Button
                    size="sm"
                    variant="outline"
                    @click="viewEvent(event)"
                  >
                    <Edit class="h-4 w-4 mr-1" />
                    View
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    @click="deleteEvent(event)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </td>
            </tr>
            <tr v-if="events.length === 0">
              <td colspan="6" class="text-center p-8 text-gray-500">
                No events created yet
              </td>
            </tr>
          </tbody>
        </Table>
      </CardContent>
    </Card>
  </div>
</template>