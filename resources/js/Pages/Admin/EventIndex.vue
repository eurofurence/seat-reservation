<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table } from '@/Components/ui/table'
import { Plus, Edit, Trash2, Calendar, Clock, MapPin, Users } from 'lucide-vue-next'
import dayjs from 'dayjs'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/Components/ui/dialog'
import EventForm from '@/Components/EventForm.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  events: Array,
  rooms: Array,
  title: String,
  breadcrumbs: Array,
})

const createDialogOpen = ref(false)
const editDialogOpen = ref(false)
const editingEvent = ref(null)
const isLoading = ref(false)

const openCreateDialog = () => {
  editingEvent.value = null
  createDialogOpen.value = true
}

const openEditDialog = (event) => {
  editingEvent.value = event
  editDialogOpen.value = true
}

const handleFormSubmit = (data) => {
  isLoading.value = true
  
  if (editingEvent.value) {
    // Update existing event
    router.put(route('admin.events.update', editingEvent.value.id), data, {
      onSuccess: () => {
        editDialogOpen.value = false
        editingEvent.value = null
      },
      onFinish: () => {
        isLoading.value = false
      }
    })
  } else {
    // Create new event
    router.post(route('admin.events.store'), data, {
      onSuccess: () => {
        createDialogOpen.value = false
      },
      onFinish: () => {
        isLoading.value = false
      }
    })
  }
}

const handleFormCancel = () => {
  createDialogOpen.value = false
  editDialogOpen.value = false
  editingEvent.value = null
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
      <Dialog :open="createDialogOpen" @update:open="createDialogOpen = $event">
        <DialogTrigger as-child>
          <Button @click="openCreateDialog">
            <Plus class="mr-2 h-4 w-4" />
            Create Event
          </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Create New Event</DialogTitle>
          </DialogHeader>
          <EventForm
            :rooms="rooms"
            :is-loading="isLoading"
            @submit="handleFormSubmit"
            @cancel="handleFormCancel"
          />
        </DialogContent>
      </Dialog>
    </div>

    <!-- Edit Event Dialog -->
    <Dialog :open="editDialogOpen" @update:open="editDialogOpen = $event">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Edit Event</DialogTitle>
        </DialogHeader>
        <EventForm
          v-if="editingEvent"
          :event="editingEvent"
          :rooms="rooms"
          :is-loading="isLoading"
          @submit="handleFormSubmit"
          @cancel="handleFormCancel"
        />
      </DialogContent>
    </Dialog>
    
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
                    @click="openEditDialog(event)"
                  >
                    <Edit class="h-4 w-4 mr-1" />
                    Edit
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    @click="viewEvent(event)"
                  >
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