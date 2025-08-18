<script setup>
import { Head, Link } from "@inertiajs/vue3"
import Layout from "@/Layouts/Layout.vue"
import Button from "@/Components/ui/Button.vue"
import Card from "@/Components/ui/Card.vue"
import Alert from "@/Components/ui/Alert.vue"
import { Clock, MapPin, User, Plus, Search, LogOut, CheckCircle, AlertCircle, Info } from 'lucide-vue-next'
import dayjs from "dayjs"

defineOptions({layout: Layout})

defineProps({
    bookings: Array
})

const formatDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, HH:mm')
}

const formatFullDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, YYYY - HH:mm')
}

const getSeatInfo = (booking) => {
  return `${booking.seat.row.block.name} - Row ${booking.seat.row.name} - Seat ${booking.seat.label}`
}

const getBookingStatus = (booking) => {
  const now = dayjs()
  const eventStart = dayjs(booking.event.starts_at)
  const reservationEnd = dayjs(booking.event.reservation_ends_at)
  
  if (now.isAfter(eventStart)) {
    return { 
      status: 'completed', 
      color: 'bg-gray-100 text-gray-800 border-gray-200', 
      text: 'Event Completed',
      icon: CheckCircle
    }
  }
  if (now.isAfter(reservationEnd)) {
    return { 
      status: 'locked', 
      color: 'bg-orange-100 text-orange-800 border-orange-200', 
      text: 'Reservation Locked',
      icon: AlertCircle
    }
  }
  return { 
    status: 'active', 
    color: 'bg-green-100 text-green-800 border-green-200', 
    text: 'Active',
    icon: CheckCircle
  }
}
</script>

<template>
  <Head title="My Bookings" />
  
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-20">
          <h1 class="text-xl lg:text-2xl font-semibold">My Reservations</h1>
          <div class="flex items-center space-x-4">
            <!-- Desktop: Add New Booking button -->
            <Link :href="route('events.index')" class="hidden lg:block">
              <Button>
                <Plus class="mr-2 h-4 w-4" />
                Make New Booking
              </Button>
            </Link>
            <Link method="post" :href="route('auth.logout')">
              <Button variant="outline" size="sm">
                <LogOut class="mr-2 h-4 w-4" />
                Logout
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8 pb-24 lg:pb-8">
      <!-- Flash Message -->
      <Alert v-if="$page.props.flash.message" class="mb-6 lg:mb-8 border-green-200 bg-green-50">
        <CheckCircle class="h-4 w-4" />
        <div class="text-green-800">
          {{ $page.props.flash.message }}
        </div>
      </Alert>

      <!-- Instructions -->
      <Alert class="mb-6 lg:mb-8 border-blue-200 bg-blue-50">
        <Info class="h-4 w-4" />
        <div class="text-blue-800">
          Click any booking to view details or cancel
        </div>
      </Alert>

      <!-- Bookings List -->
      <div v-if="bookings.length" class="space-y-4 lg:grid lg:grid-cols-2 xl:grid-cols-3 lg:gap-6 lg:space-y-0">
        <Link 
          v-for="booking in bookings" 
          :key="booking.id"
          :href="route('bookings.show', {booking: booking.id, event: booking.event.id})"
          class="block"
        >
          <Card class="p-4 lg:p-6 hover:shadow-md lg:hover:shadow-lg transition-shadow cursor-pointer h-full">
            <div class="flex items-start justify-between lg:flex-col lg:space-y-4">
              <div class="flex-1 min-w-0 lg:w-full">
                <div class="flex items-start justify-between lg:justify-start mb-3">
                  <h3 class="text-lg lg:text-xl font-semibold text-gray-900 lg:mb-2">{{ booking.event.name }}</h3>
                  <span 
                    :class="[
                      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ml-4 lg:ml-0 lg:w-fit',
                      getBookingStatus(booking).color
                    ]"
                  >
                    <component :is="getBookingStatus(booking).icon" class="h-3 w-3 mr-1" />
                    {{ getBookingStatus(booking).text }}
                  </span>
                </div>
                
                <div class="space-y-2 lg:space-y-3 text-sm lg:text-base text-gray-600 mb-3 lg:mb-4">
                  <div class="flex items-center">
                    <Clock class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span>{{ formatDateTime(booking.event.starts_at) }}</span>
                  </div>
                  <div class="flex items-center">
                    <MapPin class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span>{{ booking.event.room.name }}</span>
                  </div>
                  <div class="flex items-center">
                    <User class="h-4 w-4 mr-2 flex-shrink-0" />
                    <span>{{ booking.name }}</span>
                  </div>
                </div>
                
                <div class="text-sm lg:text-base text-gray-500 lg:pt-4 lg:border-t">
                  <strong>Seat:</strong> {{ getSeatInfo(booking) }}
                </div>
              </div>
            </div>
          </Card>
        </Link>
      </div>
      
      <!-- Empty State -->
      <div v-else class="text-center py-16 lg:py-24">
        <div class="flex flex-col items-center">
          <Search class="h-16 w-16 lg:h-24 lg:w-24 text-gray-300 mb-4 lg:mb-6" />
          <h3 class="text-lg lg:text-xl font-medium text-gray-900 mb-2">No reservations yet</h3>
          <p class="text-gray-600 max-w-sm lg:max-w-md mx-auto mb-6 lg:text-lg">
            You haven't made any reservations yet. Click the button below to book your first seat!
          </p>
          <Link :href="route('events.index')" class="hidden lg:block">
            <Button size="lg">
              <Plus class="mr-2 h-4 w-4" />
              Make New Booking
            </Button>
          </Link>
        </div>
      </div>
    </div>
    
    <!-- Fixed Bottom Action (Mobile Only) -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t shadow-lg lg:hidden">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <Link :href="route('events.index')">
          <Button size="lg" class="w-full">
            <Plus class="mr-2 h-4 w-4" />
            Make New Booking
          </Button>
        </Link>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Additional styles can be added here if needed */
</style>