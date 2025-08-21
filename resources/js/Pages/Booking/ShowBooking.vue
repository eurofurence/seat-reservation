<script setup>
import { Head, Link, useForm, router } from "@inertiajs/vue3"
import { computed, ref } from 'vue'
import Layout from "@/Layouts/Layout.vue"
import SeatLayout from '@/Components/SeatLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Alert } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { 
  ArrowLeft, 
  Calendar, 
  Clock, 
  MapPin, 
  User, 
  AlertCircle, 
  CheckCircle, 
  Edit3, 
  Trash2,
  Info,
  ChevronDown,
  ChevronUp,
  Settings
} from 'lucide-vue-next'
import dayjs from "dayjs"

defineOptions({layout: Layout})

const props = defineProps({
    booking: Object,
    event: Object,
    blocks: Array,
    bookedSeats: Array,
    userBookedSeats: Array
})

const form = useForm({
    comment: props.booking.comment,
    name: props.booking.name || props.booking.guest_name
})

// Collapsible seat layout state
const showRoomPlan = ref(false)

// Check if user can modify booking
const canModify = computed(() => {
    const now = dayjs()
    const reservationEnd = dayjs(props.event.reservation_ends_at)
    return now.isBefore(reservationEnd) && !props.booking.picked_up_at
})

// Get booking status
const bookingStatus = computed(() => {
    const now = dayjs()
    const eventStart = dayjs(props.event.starts_at)
    const reservationEnd = dayjs(props.event.reservation_ends_at)
    
    if (props.booking.picked_up_at) {
        return {
            status: 'picked_up',
            color: 'bg-purple-100 text-purple-800 border-purple-200',
            text: 'Ticket Picked Up',
            icon: CheckCircle
        }
    }
    
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
            status: 'cancelled',
            color: 'bg-red-100 text-red-800 border-red-200',
            text: 'Cancelled',
            icon: AlertCircle
        }
    }
    
    return {
        status: 'active',
        color: 'bg-green-100 text-green-800 border-green-200',
        text: 'Active',
        icon: CheckCircle
    }
})

function cancelReservation() {
    if (!confirm('Are you sure you want to cancel your reservation? This action cannot be undone.')) {
        return
    }
    
    form.delete(route('bookings.destroy', {booking: props.booking.id, event: props.event.id}), {
        onSuccess: () => {
            router.visit(route('bookings.index'))
        }
    })
}

function updateBooking() {
    form.patch(route('bookings.update', {booking: props.booking.id, event: props.event.id}), {
        preserveScroll: true
    })
}
</script>

<template>
    <Head title="Manage Reservation" />
    
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 lg:h-20">
                    <div class="flex items-center space-x-4">
                        <Link :href="route('bookings.index')" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
                            <ArrowLeft class="h-5 w-5 mr-2" />
                            Back to Reservations
                        </Link>
                    </div>
                    <h1 class="text-xl lg:text-2xl font-semibold">Manage Reservation</h1>
                    <div class="flex items-center space-x-4">
                        <!-- Admin button for admin users -->
                        <Link v-if="$page.props.auth.user?.is_admin" :href="route('admin.dashboard')">
                            <Button variant="outline" size="sm">
                                <Settings class="mr-2 h-4 w-4" />
                                Switch to Admin
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Event Information Card -->
            <Card class="mb-8">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ event.name }}</h2>
                            <div class="space-y-2 text-gray-600">
                                <div class="flex items-center">
                                    <Calendar class="h-4 w-4 mr-2" />
                                    <span>{{ dayjs(event.starts_at).format('MMMM DD, YYYY') }}</span>
                                </div>
                                <div class="flex items-center">
                                    <Clock class="h-4 w-4 mr-2" />
                                    <span>{{ dayjs(event.starts_at).format('HH:mm') }}</span>
                                </div>
                                <div class="flex items-center">
                                    <MapPin class="h-4 w-4 mr-2" />
                                    <span>{{ event.room.name }}</span>
                                </div>
                            </div>
                        </div>
                        <Badge :class="bookingStatus.color" class="flex items-center">
                            <component :is="bookingStatus.icon" class="h-3 w-3 mr-1" />
                            {{ bookingStatus.text }}
                        </Badge>
                    </div>

                    <!-- Seat Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Your Seat</h3>
                        <div class="text-lg font-medium text-gray-900">
                            {{ booking.seat.row.block.name }} - {{ booking.seat.row.name }} - Seat {{ booking.seat.label }}
                        </div>
                        <div class="text-sm text-gray-600 mt-1">
                            Booked on {{ dayjs(booking.created_at).format('MMMM DD, YYYY [at] HH:mm') }}
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Seat Layout -->
            <Card v-if="blocks && blocks.length > 0" class="mb-8">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Seat Location</h3>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showRoomPlan = !showRoomPlan"
                            class="flex items-center gap-2"
                        >
                            <component :is="showRoomPlan ? ChevronUp : ChevronDown" class="h-4 w-4" />
                            {{ showRoomPlan ? 'Hide Room Plan' : 'Show Room Plan' }}
                        </Button>
                    </div>
                    
                    <div v-if="showRoomPlan" class="bg-gray-50 rounded-lg p-4">
                        <SeatLayout
                            :event="event"
                            :room="event.room"
                            :blocks="blocks"
                            :selected-seats="userBookedSeats"
                            :booked-seats="bookedSeats"
                            :admin-mode="false"
                        />
                        <div class="mt-4 text-sm text-gray-600">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 bg-blue-500 rounded border border-blue-600"></div>
                                    <span>Your Seat</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 bg-red-500 rounded border border-red-600"></div>
                                    <span>Booked</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 bg-emerald-500 rounded border border-emerald-600"></div>
                                    <span>Available</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Status Messages -->
            <div class="mb-8">
                <Alert v-if="booking.picked_up_at" class="mb-4 border-purple-200 bg-purple-50">
                    <Info class="h-4 w-4" />
                    <div>
                        <div class="font-medium">Ticket Picked Up</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Your ticket was picked up on {{ dayjs(booking.picked_up_at).format('MMMM DD, YYYY [at] HH:mm') }}. 
                            You can no longer modify or cancel this reservation.
                        </div>
                    </div>
                </Alert>

                <Alert v-else-if="!canModify && dayjs().isBefore(dayjs(event.starts_at))" class="mb-4 border-red-200 bg-red-50">
                    <AlertCircle class="h-4 w-4" />
                    <div>
                        <div class="font-medium">Reservation Cancelled</div>
                        <div class="text-sm text-gray-600 mt-1">
                            Your reservation was cancelled because you didn't pick up your PAT before the deadline.
                        </div>
                    </div>
                </Alert>

                <Alert v-else-if="dayjs().isAfter(dayjs(event.starts_at))" class="mb-4 border-gray-200 bg-gray-50">
                    <CheckCircle class="h-4 w-4" />
                    <div>
                        <div class="font-medium">Event Completed</div>
                        <div class="text-sm text-gray-600 mt-1">
                            This event has already taken place. This reservation is now historical.
                        </div>
                    </div>
                </Alert>

                <Alert v-else class="mb-4 border-green-200 bg-green-50">
                    <CheckCircle class="h-4 w-4" />
                    <div>
                        <div class="font-medium">Active Reservation</div>
                        <div class="text-sm text-gray-600 mt-1">
                            You can modify or cancel this reservation until {{ dayjs(event.reservation_ends_at).format('MMMM DD, YYYY [at] HH:mm') }}.
                        </div>
                    </div>
                </Alert>

                <!-- PAT Pickup Warning -->
                <Alert v-if="!booking.picked_up_at && dayjs().isBefore(dayjs(event.reservation_ends_at))" class="mb-4 border-amber-200 bg-amber-50">
                    <AlertCircle class="h-4 w-4" />
                    <div>
                        <div class="font-medium text-amber-900">Important: Pickup Required</div>
                        <div class="text-sm text-amber-800 mt-1">
                            <strong>You must pick up your Priority Access Ticket (PAT) at the Infodesk before {{ dayjs(event.reservation_ends_at).format('MMMM DD, YYYY [at] HH:mm') }}.</strong>
                        </div>
                        <div class="text-sm text-amber-800 mt-2">
                            ⚠️ If you do not pick up your PAT before the reservation deadline, your ticket will not be valid and your reservation will be cancelled.
                        </div>
                    </div>
                </Alert>
            </div>

            <!-- Booking Details Form -->
            <Card>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Reservation Details</h3>
                    
                    <form @submit.prevent="updateBooking" class="space-y-6">
                        <div>
                            <Label for="name">Name on Reservation</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                :disabled="!canModify || form.processing"
                                placeholder="Enter name for reservation"
                                class="mt-2"
                                required
                            />
                            <div v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <Label for="comment">Additional Comment</Label>
                            <Textarea
                                id="comment"
                                v-model="form.comment"
                                :disabled="!canModify || form.processing"
                                placeholder="Any special notes or requirements (optional)"
                                class="mt-2"
                                rows="3"
                            />
                            <div v-if="form.errors.comment" class="text-red-600 text-sm mt-1">{{ form.errors.comment }}</div>
                        </div>

                        <!-- Action Buttons -->
                        <div v-if="canModify" class="flex flex-col sm:flex-row gap-3 pt-4">
                            <Button 
                                type="submit" 
                                :disabled="form.processing"
                                class="flex items-center justify-center"
                            >
                                <Edit3 class="h-4 w-4 mr-2" />
                                {{ form.processing ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            
                            <Button 
                                type="button"
                                variant="destructive"
                                @click="cancelReservation"
                                :disabled="form.processing"
                                class="flex items-center justify-center"
                            >
                                <Trash2 class="h-4 w-4 mr-2" />
                                Cancel Reservation
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>
        </div>
    </div>
</template>