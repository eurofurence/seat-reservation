<script setup>
import {Head, router} from '@inertiajs/vue3'
import {ref} from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import SeatLayout from '@/Components/SeatLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table } from '@/Components/ui/table'
import {Download, Calendar, Clock, MapPin, Users} from 'lucide-vue-next'
import dayjs from 'dayjs'
import axios from 'axios'

defineOptions({layout: AdminLayout})

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    bookings: Array,
    bookedSeats: Array,
    title: String,
    breadcrumbs: Array,
})

const exportBookings = async () => {
    try {
        const response = await axios.get(route('admin.events.export', props.event.id), {
            responseType: 'blob',
        })

        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `bookings-${props.event.id}-${Date.now()}.csv`)
        document.body.appendChild(link)
        link.click()
        link.remove()
    } catch (error) {
        console.error('Error exporting bookings:', error)
    }
}

const getSeatInfo = (booking) => {
    if (!booking.seat) return 'N/A'
    return `${booking.seat.row.block.name} - Row ${booking.seat.row.name} - Seat ${booking.seat.label}`
}
</script>

<template>
    <Head :title="title"/>

    <div>
        <!-- Event Info -->
        <div class="mb-6">
            <div class="flex justify-between items-start">
                <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <div class="flex items-center">
                        <Calendar class="h-4 w-4 mr-1"/>
                        {{ dayjs(event.starts_at).format('MMM DD, YYYY') }}
                    </div>
                    <div class="flex items-center">
                        <Clock class="h-4 w-4 mr-1"/>
                        {{ dayjs(event.starts_at).format('HH:mm') }}
                    </div>
                    <div class="flex items-center">
                        <MapPin class="h-4 w-4 mr-1"/>
                        {{ room.name }}
                    </div>
                    <div class="flex items-center">
                        <Users class="h-4 w-4 mr-1"/>
                        {{ bookings.total || bookings.length }} bookings
                    </div>
                </div>
                <Button @click="exportBookings">
                    <Download class="mr-2 h-4 w-4"/>
                    Export Bookings
                </Button>
            </div>
        </div>

        <!-- Seat Layout -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Seat Layout</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="blocks && blocks.length > 0">
                    <SeatLayout
                        :event="event"
                        :room="room"
                        :blocks="blocks"
                        :booked-seats="bookedSeats"
                        :selected-seats="[]"
                        @seats-changed="() => {}"
                    />
                </div>
                <div v-else class="text-center py-8 text-muted-foreground">
                    No seating layout configured for this room
                </div>
            </CardContent>
        </Card>

        <!-- Bookings Table -->
        <Card>
            <CardHeader>
                <CardTitle>Bookings ({{ bookings.total || bookings.length }})</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <Table>
                        <thead>
                        <tr class="border-b">
                            <th class="text-left p-2">ID</th>
                            <th class="text-left p-2">User</th>
                            <th class="text-left p-2">Email</th>
                            <th class="text-left p-2">Seat</th>
                            <th class="text-left p-2">Booked At</th>
                            <th class="text-left p-2">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="booking in (bookings.data || bookings)" :key="booking.id" class="border-b">
                            <td class="p-2">{{ booking.id }}</td>
                            <td class="p-2">{{ booking.user.name }}</td>
                            <td class="p-2">{{ booking.user.email }}</td>
                            <td class="p-2">{{ getSeatInfo(booking) }}</td>
                            <td class="p-2">{{ dayjs(booking.created_at).format('MMM DD, HH:mm') }}</td>
                            <td class="p-2">
                  <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                    Active
                  </span>
                            </td>
                        </tr>
                        <tr v-if="(bookings.data || bookings).length === 0">
                            <td colspan="6" class="text-center p-4 text-muted-foreground">
                                No bookings yet
                            </td>
                        </tr>
                        </tbody>
                    </Table>
                </div>

                <!-- Pagination -->
                <div v-if="bookings.links" class="flex justify-center mt-4">
                    <nav class="flex space-x-2">
                        <template v-for="(link, index) in bookings.links" :key="index">
                            <Button
                                v-if="link.url"
                                variant="outline"
                                size="sm"
                                @click="router.visit(link.url)"
                                :disabled="!link.url"
                                v-html="link.label"
                            />
                            <span v-else class="px-3 py-1 text-sm text-muted-foreground" v-html="link.label"/>
                        </template>
                    </nav>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
