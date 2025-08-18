<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Layout from "@/Layouts/Layout.vue"
import Button from "@/Components/ui/Button.vue"
import Card from "@/Components/ui/Card.vue"
import Alert from "@/Components/ui/Alert.vue"
import { ArrowLeft, Volume2, Calendar, MapPin, Clock, Users, Search } from 'lucide-vue-next'
import dayjs from "dayjs"

defineOptions({layout: Layout})

defineProps({
    events: Array
})

const getSeatAvailabilityStatus = (seatsLeft) => {
  if (seatsLeft === 0) return { color: 'bg-red-100 text-red-800 border-red-200', text: 'Sold Out' }
  if (seatsLeft < 10) return { color: 'bg-orange-100 text-orange-800 border-orange-200', text: 'Few Left' }
  return { color: 'bg-green-100 text-green-800 border-green-200', text: 'Available' }
}

const formatDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, HH:mm')
}

const formatDeadline = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, YYYY - HH:mm')
}

function goBack() {
  window.location.href = route('dashboard')
}
</script>

<template>
  <Head title="Events" />
  
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center">
            <button
              @click="goBack"
              class="mr-4 p-2 hover:bg-gray-100 rounded-md transition-colors"
            >
              <ArrowLeft class="h-5 w-5" />
            </button>
            <h1 class="text-xl font-semibold">Select Event</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Page Description -->
      <Alert class="mb-6 border-blue-200 bg-blue-50">
        <Volume2 class="h-4 w-4" />
        <div class="text-blue-800">
          Select an event to book your seats
        </div>
      </Alert>

      <!-- Events List -->
      <div v-if="events.length" class="space-y-4">
        <Link 
          v-for="event in events" 
          :key="event.id"
          :href="route('bookings.create', {event: event.id})"
          class="block"
        >
          <Card class="p-4 hover:shadow-md transition-shadow cursor-pointer">
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ event.name }}</h3>
                
                <div class="space-y-2 text-sm text-gray-600">
                  <div class="flex items-center">
                    <MapPin class="h-4 w-4 mr-2" />
                    <span>{{ event.room.name }}</span>
                  </div>
                  <div class="flex items-center">
                    <Clock class="h-4 w-4 mr-2" />
                    <span>{{ formatDateTime(event.starts_at) }}</span>
                  </div>
                  <div class="flex items-center">
                    <Calendar class="h-4 w-4 mr-2" />
                    <span>Deadline: {{ formatDeadline(event.reservation_ends_at) }}</span>
                  </div>
                </div>
              </div>
              
              <div class="flex flex-col items-end space-y-2 ml-4">
                <span 
                  :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border',
                    getSeatAvailabilityStatus(event.seats_left).color
                  ]"
                >
                  <Users class="h-3 w-3 mr-1" />
                  {{ event.seats_left }} seats left
                </span>
                
                <span class="text-xs text-gray-500">
                  {{ getSeatAvailabilityStatus(event.seats_left).text }}
                </span>
              </div>
            </div>
          </Card>
        </Link>
      </div>
      
      <!-- Empty State -->
      <div v-else class="text-center py-16">
        <div class="flex flex-col items-center">
          <Search class="h-16 w-16 text-gray-300 mb-4" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">No events available</h3>
          <p class="text-gray-600 max-w-sm mx-auto">
            There are currently no events available for booking. Please check back later.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Additional styles can be added here if needed */
</style>