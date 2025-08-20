<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Calendar, Users, MapPin, TrendingUp } from 'lucide-vue-next'

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
  title: String,
  breadcrumbs: Array,
})
</script>

<template>
  <Head :title="title" />
  
  <div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
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
  </div>
</template>