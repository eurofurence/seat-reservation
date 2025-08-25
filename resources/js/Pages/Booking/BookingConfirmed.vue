<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { InfoIcon, CheckCircle2 } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Link } from '@inertiajs/vue3'

interface Props {
    event: {
        id: number
        name: string
        reservation_ends_at: string
    }
    bookingCode: string
    bookings: Array<{
        id: number
        name: string
        seat: {
            label: string
            row: string
            block: string
        }
    }>
}

const props = defineProps<Props>()

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short'
    })
}
</script>

<template>
    <Head title="Booking Confirmed" />

    <div class="min-h-screen bg-gray-50 py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <Card>
                <CardHeader class="text-center">
                    <CheckCircle2 class="mx-auto h-16 w-16 text-green-500 mb-4" />
                    <CardTitle class="text-2xl">Booking Confirmed!</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Booking Code Display -->
                    <div class="bg-gray-900 text-white p-8 rounded-lg text-center">
                        <p class="text-sm uppercase tracking-wider mb-2">Your Booking Code</p>
                        <p class="text-6xl font-bold font-mono">{{ bookingCode }}</p>
                    </div>

                    <!-- Important Information -->
                    <Alert class="border-orange-200 bg-orange-50">
                        <InfoIcon class="h-5 w-5 text-orange-600" />
                        <AlertDescription class="text-orange-800">
                            <strong class="block mb-2">Important: Ticket Collection Required</strong>
                            Please come to the Infodesk by <strong>{{ formatDate(event.reservation_ends_at) }}</strong>
                            to exchange this booking code for your Priority Access Ticket.
                            <br><br>
                            <span class="font-semibold">
                                If you do not collect your ticket by the reservation deadline,
                                your booking will be automatically cancelled!
                            </span>
                        </AlertDescription>
                    </Alert>

                    <!-- Event Details -->
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold mb-3">Event: {{ event.name }}</h3>
                        <div class="space-y-2">
                            <p class="text-sm text-gray-600">Reserved Seats:</p>
                            <ul class="space-y-1">
                                <li v-for="booking in bookings" :key="booking.id" class="text-sm">
                                    • {{ booking.name }} - Block {{ booking.seat.block }}, Row {{ booking.seat.row }}, Seat {{ booking.seat.label }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-center gap-4 pt-4">
                        <Link :href="route('bookings.index')">
                            <Button as="span">View My Bookings</Button>
                        </Link>
                        <Button
                            variant="outline"
                            @click="window.print()"
                        >
                            Print This Page
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .min-h-screen {
        min-height: auto;
    }
    button {
        display: none;
    }
}
</style>
