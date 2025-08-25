<script setup>
import { Head, useForm, usePoll } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card'
import { Table } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { Calendar, Users, MapPin, TrendingUp, Search, Clock, CheckCircle, ExternalLink } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import dayjs from 'dayjs'

defineOptions({ layout: AdminLayout })

defineProps({
  stats: {
    type: Object,
    default: () => ({
      totalEvents: 0,
      upcomingEvents: 0,
      totalBookings: 0,
      totalRooms: 0,
    }),
  },
  recentBookings: {
    type: Array,
    default: () => [],
  },
  title: String,
  breadcrumbs: Array,
})

const bookingCodeForm = useForm({
  booking_code: ''
})

const lookupBookingCode = () => {
  bookingCodeForm.post(route('admin.lookup-booking-code'), {
    preserveScroll: true,
    onSuccess: () => {
      bookingCodeForm.reset()
    }
  })
}

// Auto-refresh dashboard every 5 seconds
usePoll(5000, {
  keepAlive: true
})

// Helper functions for recent bookings display
const getBookingDisplayName = (booking) => {
  if (booking.user) {
    return booking.user.name
  }
  return booking.name || 'Unknown'
}

const getBookerType = (booking) => {
  if (booking.user) {
    return 'User'
  }
  return booking.type === 'admin' ? 'Admin' : 'Manual'
}

const getSeatInfo = (booking) => {
  if (!booking.seat) return 'N/A'
  return `${booking.seat.row.block.name} - ${booking.seat.row.name} - ${booking.seat.label}`
}

const formatTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, HH:mm')
}
</script>

<template>
  <Head :title="title" />

  <div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Events</CardTitle>
          <Calendar class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.totalEvents }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Upcoming Events</CardTitle>
          <TrendingUp class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.upcomingEvents }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Bookings</CardTitle>
          <Users class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.totalBookings }}</div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader class="flex flex-row items-center justify-between pb-2">
          <CardTitle class="text-sm font-medium text-muted-foreground">Total Rooms</CardTitle>
          <MapPin class="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          <div class="text-2xl font-bold">{{ stats.totalRooms }}</div>
        </CardContent>
      </Card>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
      <!-- Left Column: Booking Code Lookup -->
      <Card>
        <CardHeader>
          <CardTitle>Quick Booking Lookup</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
            <p class="text-sm text-blue-800">
              Enter a booking code to open the event page and highlight the specific booking in the table.
              This lookup does <strong>not</strong> mark the PAT as handed out - you must still check the pickup box manually.
            </p>
          </div>

          <form @submit.prevent="lookupBookingCode" class="flex gap-4 items-end">
            <div class="flex-1">
              <Label for="booking_code">Booking Code</Label>
              <div class="flex gap-2 mt-1">
                <Input
                  id="booking_code"
                  v-model="bookingCodeForm.booking_code"
                  type="text"
                  placeholder="e.g. A7"
                  maxlength="2"
                  class="uppercase font-mono text-lg"
                  :class="{ 'border-red-500': bookingCodeForm.errors.booking_code }"
                />
                <Button type="submit" :disabled="bookingCodeForm.processing || bookingCodeForm.booking_code.length !== 2">
                  <Search class="h-4 w-4 mr-1" />
                  Lookup
                </Button>
              </div>
              <p v-if="bookingCodeForm.errors.booking_code" class="text-sm text-red-500 mt-1">
                {{ bookingCodeForm.errors.booking_code }}
              </p>
            </div>
          </form>
        </CardContent>
      </Card>

      <!-- Right Column: Recent Bookings -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            Recent Bookings
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse" title="Auto-refreshing every 5 seconds"></div>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div v-if="recentBookings.length === 0" class="text-center py-4 text-muted-foreground">
            No recent bookings
          </div>
          <div v-else class="overflow-x-auto">
            <Table>
              <thead>
                <tr class="border-b">
                  <th class="text-left p-2 text-xs font-medium text-muted-foreground">Name</th>
                  <th class="text-left p-2 text-xs font-medium text-muted-foreground">Event</th>
                  <th class="text-left p-2 text-xs font-medium text-muted-foreground">Time</th>
                  <th class="text-left p-2 text-xs font-medium text-muted-foreground">Status</th>
                  <th class="text-left p-2 text-xs font-medium text-muted-foreground">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="booking in recentBookings"
                  :key="booking.id"
                  class="border-b hover:bg-gray-50 transition-colors"
                >
                  <td class="p-2">
                    <div class="text-sm font-medium">{{ getBookingDisplayName(booking) }}</div>
                    <div class="text-xs text-muted-foreground flex items-center gap-1">
                      {{ getBookerType(booking) }}
                      <span v-if="booking.booking_code" class="font-mono bg-gray-100 px-1 rounded">{{ booking.booking_code }}</span>
                    </div>
                  </td>
                  <td class="p-2">
                    <div class="text-sm">{{ booking.event.name }}</div>
                    <div class="text-xs text-muted-foreground">{{ getSeatInfo(booking) }}</div>
                  </td>
                  <td class="p-2">
                    <div class="text-xs">{{ formatTime(booking.created_at) }}</div>
                  </td>
                  <td class="p-2">
                    <Badge v-if="booking.picked_up_at" class="bg-green-100 text-green-800">
                      <CheckCircle class="h-3 w-3 mr-1" />
                      Picked Up
                    </Badge>
                    <Badge v-else variant="outline">
                      <Clock class="h-3 w-3 mr-1" />
                      Pending
                    </Badge>
                  </td>
                  <td class="p-2">
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-7 px-2"
                      @click="$inertia.visit(route('admin.events.show', { event: booking.event_id, booking_id: booking.id }))"
                    >
                      <ExternalLink class="h-3 w-3" />
                    </Button>
                  </td>
                </tr>
              </tbody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
