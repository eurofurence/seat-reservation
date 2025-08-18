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

const getTicketAvailabilityStatus = (ticketsLeft) => {
  if (ticketsLeft === 0) return { color: 'bg-red-100 text-red-800 border-red-200', text: 'Sold Out' }
  if (ticketsLeft < 10) return { color: 'bg-orange-100 text-orange-800 border-orange-200', text: 'Few Left' }
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
        <div class="flex items-center justify-between h-16 lg:h-20">
          <div class="flex items-center">
            <button
              @click="goBack"
              class="mr-4 p-2 hover:bg-gray-100 rounded-md transition-colors lg:hidden"
            >
              <ArrowLeft class="h-5 w-5" />
            </button>
            <h1 class="text-xl lg:text-2xl font-semibold">Select Event</h1>
          </div>
          <!-- Desktop Navigation -->
          <div class="hidden lg:flex items-center space-x-4">
            <Button variant="outline" @click="goBack">
              <ArrowLeft class="mr-2 h-4 w-4" />
              Back to Dashboard
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
      <!-- Page Description -->
      <Alert class="mb-6 lg:mb-8 border-blue-200 bg-blue-50">
        <Volume2 class="h-4 w-4" />
        <div class="text-blue-800">
          Select an event to book your seats
        </div>
      </Alert>

      <!-- Events List -->
      <div v-if="events.length" class="space-y-4 lg:grid lg:grid-cols-2 xl:grid-cols-3 lg:gap-6 lg:space-y-0">
        <Link 
          v-for="event in events" 
          :key="event.id"
          :href="route('bookings.create', {event: event.id})"
          class="block"
        >
          <Card class="p-4 lg:p-6 hover:shadow-md lg:hover:shadow-lg transition-shadow cursor-pointer h-full">
            <div class="flex items-start justify-between lg:flex-col lg:space-y-4">
              <div class="flex-1 min-w-0 lg:w-full">
                <h3 class="text-lg lg:text-xl font-semibold text-gray-900 mb-2 lg:mb-3">{{ event.name }}</h3>
                
                <div class="space-y-2 lg:space-y-3 text-sm lg:text-base text-gray-600">
                  <div class="flex items-center">
                    <MapPin class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span>{{ event.room.name }}</span>
                  </div>
                  <div class="flex items-center">
                    <Clock class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span>{{ formatDateTime(event.starts_at) }}</span>
                  </div>
                  <div class="flex items-center">
                    <Calendar class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span class="text-xs lg:text-sm">Deadline: {{ formatDeadline(event.reservation_ends_at) }}</span>
                  </div>
                </div>
              </div>
              
              <div class="flex flex-col items-end space-y-2 ml-4 lg:ml-0 lg:w-full lg:items-start lg:pt-4 lg:border-t">
                <span 
                  :class="[
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border lg:w-full lg:justify-center',
                    getTicketAvailabilityStatus(event.tickets_left).color
                  ]"
                >
                  <Users class="h-3 w-3 mr-1" />
                  {{ event.tickets_left }} tickets left
                </span>
                
                <span class="text-xs text-gray-500 lg:w-full lg:text-center">
                  {{ getTicketAvailabilityStatus(event.tickets_left).text }}
                </span>
              </div>
            </div>
          </Card>
        </Link>
      </div>
      
      <!-- Empty State -->
      <div v-else class="text-center py-16 lg:py-24">
        <div class="flex flex-col items-center">
          <Search class="h-16 w-16 lg:h-24 lg:w-24 text-gray-300 mb-4 lg:mb-6" />
          <h3 class="text-lg lg:text-xl font-medium text-gray-900 mb-2">No events available</h3>
          <p class="text-gray-600 max-w-sm lg:max-w-md mx-auto lg:text-lg">
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