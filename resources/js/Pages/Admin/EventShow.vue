<script setup>
import {Head, router, useForm} from '@inertiajs/vue3'
import {ref, computed, nextTick, onMounted, watch, watchEffect} from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import SeatLayout from '@/Components/SeatLayout.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Checkbox } from '@/Components/ui/checkbox'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import {Download, Calendar, Clock, MapPin, Users, Plus, UserPlus, X, Pencil, Trash2, Info} from 'lucide-vue-next'
import dayjs from 'dayjs'
import axios from 'axios'

defineOptions({layout: AdminLayout})

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    bookings: Array,
    bookedSeats: Array,
    seatBookingMap: Object,
    search: String,
    booking_id: [String, Number],
    selected_seats: String, // Comma-separated seat IDs
    title: String,
    breadcrumbs: Array,
})

// Admin seat selection state - initialize from URL parameter
const selectedSeats = ref(
    props.selected_seats
        ? props.selected_seats.split(',').map(id => parseInt(id)).filter(Boolean)
        : []
)

// Manual booking form state
const manualBookingForm = ref({
    guestName: '',
    comment: '',
    isProcessing: false
})

// Search state - initialize from props
const searchQuery = ref(props.search || '')

// Edit/Delete modal state
const editModal = ref({
    show: false,
    booking: null,
    name: '',
    comment: '',
    isProcessing: false
})

const deleteModal = ref({
    show: false,
    booking: null,
    isProcessing: false
})

// Calculate seat statistics
const seatStats = computed(() => {
    let totalSeats = 0

    // Count all seats in all blocks
    props.blocks?.forEach(block => {
        block.rows?.forEach(row => {
            totalSeats += row.seats?.length || 0
        })
    })

    const bookedCount = props.bookedSeats?.length || 0
    const selectedCount = selectedSeats.value.length
    const availableCount = totalSeats - bookedCount

    const maxTickets = props.event.max_tickets || totalSeats
    const ticketsRemaining = Math.max(0, maxTickets - bookedCount)

    return {
        total: totalSeats,
        available: availableCount,
        selected: selectedCount,
        booked: bookedCount,
        maxTickets,
        ticketsRemaining,
        isOverLimit: bookedCount > maxTickets
    }
})

// Handle seat selection changes from layout
const handleSeatsChanged = (newSelectedSeats) => {
    selectedSeats.value = newSelectedSeats

    // Update URL with selected seats
    const params = {}
    if (props.search) params.search = props.search
    if (props.booking_id) params.booking_id = props.booking_id
    if (newSelectedSeats.length > 0) {
        params.selected_seats = newSelectedSeats.join(',')
    }

    router.get(route('admin.events.show', props.event.id), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['selected_seats']
    })
}

// Handle clicking on booked seats to highlight booking in table
const handleBookedSeatClick = (seat) => {
    // Find the booking ID for this seat using the mapping
    const bookingId = props.seatBookingMap[seat.id]
    if (bookingId) {
        // Navigate with booking_id parameter to highlight the booking
        const params = {}
        if (props.search) {
            params.search = props.search
        }
        // Preserve selected seats when highlighting booking
        if (selectedSeats.value.length > 0) {
            params.selected_seats = selectedSeats.value.join(',')
        }
        params.booking_id = bookingId

        router.get(route('admin.events.show', props.event.id), params, {
            preserveState: true,
            preserveScroll: false, // Allow scroll to new page
            only: ['bookings', 'search', 'booking_id']
        })
    }
}

// Registration status computed property
const registrationStatus = computed(() => {
    if (!props.event.reservation_ends_at) {
        return { isOpen: true, status: 'Open', color: 'text-emerald-600' }
    }

    const now = new Date()
    const reservationEnd = new Date(props.event.reservation_ends_at)
    const isOpen = now <= reservationEnd

    return {
        isOpen,
        status: isOpen ? 'Open' : 'Closed',
        color: isOpen ? 'text-emerald-600' : 'text-red-600',
        endDate: reservationEnd
    }
})

// Get selected seat information for display
const selectedSeatInfo = computed(() => {
    if (selectedSeats.value.length === 0) return []

    const seatDetails = []
    props.blocks?.forEach(block => {
        block.rows?.forEach(row => {
            row.seats?.forEach(seat => {
                if (selectedSeats.value.includes(seat.id)) {
                    seatDetails.push({
                        id: seat.id,
                        label: `${block.name} - ${row.name} - Seat ${seat.label || seat.name}`
                    })
                }
            })
        })
    })
    return seatDetails
})


// Process manual booking
const processManualBooking = async () => {
    if (selectedSeats.value.length === 0) {
        alert('Please select at least one seat')
        return
    }

    if (!manualBookingForm.value.guestName.trim()) {
        alert('Please enter a name')
        return
    }

    manualBookingForm.value.isProcessing = true

    try {
        const response = await axios.post(route('admin.events.manual-booking', props.event.id), {
            guest_name: manualBookingForm.value.guestName,
            comment: manualBookingForm.value.comment,
            seat_ids: selectedSeats.value
        })

        if (response.data.success) {
            // Show success message
            alert(`Successfully booked ${response.data.bookings_count} seat(s) for ${response.data.guest_name}`)

            // Reset form and clear selected seats from URL
            manualBookingForm.value = { guestName: '', comment: '', isProcessing: false }
            selectedSeats.value = []

            // Refresh the page to show new bookings (this will clear selected_seats)
            router.reload()
        }

    } catch (error) {
        console.error('Error creating manual booking:', error)

        if (error.response?.data?.error) {
            alert(error.response.data.error)
        } else {
            alert('Error creating booking. Please try again.')
        }
    } finally {
        manualBookingForm.value.isProcessing = false
    }
}

// Clear manual booking form
const clearManualBooking = () => {
    manualBookingForm.value = { guestName: '', comment: '', isProcessing: false }
    selectedSeats.value = []

    // Clear selected seats from URL
    const params = {}
    if (props.search) params.search = props.search
    if (props.booking_id) params.booking_id = props.booking_id

    router.get(route('admin.events.show', props.event.id), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['selected_seats']
    })
}

// Handle pickup toggle
const togglePickup = async (booking) => {
    try {
        const response = await axios.post(route('admin.events.toggle-pickup', props.event.id), {
            booking_id: booking.id,
            picked_up: !booking.picked_up_at
        })

        if (response.data.success) {
            // Refresh the page to show updated pickup status
            router.reload()
        }

    } catch (error) {
        console.error('Error toggling pickup status:', error)
        alert('Error updating pickup status. Please try again.')
    }
}

// Open edit modal
const openEditModal = (booking) => {
    editModal.value = {
        show: true,
        booking: booking,
        name: booking.guest_name || booking.name || (booking.user ? booking.user.name : ''),
        comment: booking.comment || '',
        isProcessing: false
    }
}

// Save edited booking
const saveEditedBooking = () => {
    if (!editModal.value.name.trim()) {
        alert('Please enter a name')
        return
    }

    const editForm = useForm({
        name: editModal.value.name,
        comment: editModal.value.comment
    })

    editModal.value.isProcessing = true

    editForm.put(route('admin.events.bookings.update', [props.event.id, editModal.value.booking.id]), {
        onSuccess: () => {
            editModal.value.show = false
        },
        onFinish: () => {
            editModal.value.isProcessing = false
        }
    })
}

// Open delete modal
const openDeleteModal = (booking) => {
    deleteModal.value = {
        show: true,
        booking: booking,
        isProcessing: false
    }
}

// Delete booking
const deleteBooking = () => {
    const deleteForm = useForm({})

    deleteModal.value.isProcessing = true

    deleteForm.delete(route('admin.events.bookings.delete', [props.event.id, deleteModal.value.booking.id]), {
        onSuccess: () => {
            deleteModal.value.show = false
        },
        onFinish: () => {
            deleteModal.value.isProcessing = false
        }
    })
}

// Get display name for booking
const getBookingDisplayName = (booking) => {
    if (booking.guest_name) {
        return booking.guest_name
    }
    if (booking.user) {
        return booking.user.name
    }
    return booking.name || 'Unknown'
}

// Get booker information (user name or "Admin Booking")
const getBookerInfo = (booking) => {
    if (booking.user && booking.user.name) {
        return booking.user.name
    }
    return 'Admin Booking'
}

// Handle search with Inertia GET request (adds search as URL parameter)
const handleSearch = () => {
    const params = {}
    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim()
    }
    // Preserve selected seats when searching
    if (selectedSeats.value.length > 0) {
        params.selected_seats = selectedSeats.value.join(',')
    }
    // Clear booking highlight when searching

    router.get(route('admin.events.show', props.event.id),
        params,
        {
            preserveState: true,
            preserveScroll: true,
            only: ['bookings', 'search', 'booking_id']
        }
    )
}

// Debounced search function
let searchTimeout
const debouncedSearch = () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(handleSearch, 300)
}

// Clear search and booking highlight
const clearSearch = () => {
    searchQuery.value = ''
    const params = {}
    // Preserve selected seats when clearing search
    if (selectedSeats.value.length > 0) {
        params.selected_seats = selectedSeats.value.join(',')
    }
    
    router.get(route('admin.events.show', props.event.id),
        params, // Preserve selected seats but clear search and booking_id
        {
            preserveState: true,
            preserveScroll: true,
            only: ['bookings', 'search', 'booking_id']
        }
    )
}

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

const printTickets = () => {
    // Open print tickets page in new window
    window.open(route('admin.events.print-tickets', props.event.id), '_blank')
}

const getSeatInfo = (booking) => {
    if (!booking.seat) return 'N/A'
    return `${booking.seat.row.block.name} - ${booking.seat.row.name} - Seat ${booking.seat.label}`
}

// Scroll to highlighted booking when page loads
const scrollToHighlightedBooking = () => {
    if (props.booking_id) {
        nextTick(() => {
            const bookingElement = document.getElementById(`booking-${props.booking_id}`)
            if (bookingElement) {
                setTimeout(() => {
                    bookingElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
                }, 100) // Small delay to ensure DOM is fully rendered
            }
        })
    }
}

// Watch for booking_id changes to scroll to highlighted booking
watch(() => props.booking_id, scrollToHighlightedBooking)

// Watch for selected_seats prop changes (when navigating back from validation)
watch(() => props.selected_seats, (newSelectedSeats) => {
    if (newSelectedSeats) {
        const seatIds = newSelectedSeats.split(',').map(id => parseInt(id)).filter(Boolean)
        selectedSeats.value = seatIds
    } else {
        selectedSeats.value = []
    }
}, { immediate: true })

// Scroll to highlighted booking on component mount
onMounted(scrollToHighlightedBooking)
</script>

<template>
    <Head :title="title"/>

    <div>
        <!-- Registration Status -->
        <div class="mb-4">
            <div class="flex items-center justify-between p-4 rounded-lg border" :class="registrationStatus.isOpen ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" :class="registrationStatus.isOpen ? 'bg-emerald-500' : 'bg-red-500'"></div>
                        <span class="font-medium">Registration Status:</span>
                        <span class="font-bold" :class="registrationStatus.color">{{ registrationStatus.status }}</span>
                    </div>
                    <div v-if="event.reservation_ends_at" class="text-sm text-muted-foreground">
                        {{ registrationStatus.isOpen ? 'Closes' : 'Closed' }} {{ dayjs(event.reservation_ends_at).format('MMM DD, YYYY HH:mm') }}
                    </div>
                </div>
            </div>
        </div>

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
                <div class="flex gap-2">
                    <Button @click="exportBookings">
                        <Download class="mr-2 h-4 w-4"/>
                        Export Bookings
                    </Button>
                    <Button @click="printTickets" variant="outline">
                        <Download class="mr-2 h-4 w-4"/>
                        Print Tickets
                    </Button>
                </div>
            </div>
        </div>

        <!-- Seat Statistics -->
        <div class="mb-6">
            <Card>
                <CardHeader>
                    <CardTitle>Ticket Statistics</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold">{{ seatStats.maxTickets }}</div>
                            <div class="text-sm text-muted-foreground">Available Tickets</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-600">{{ seatStats.booked }}</div>
                            <div class="text-sm text-muted-foreground">Tickets Requested</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold" :class="seatStats.ticketsRemaining > 0 ? 'text-emerald-600' : 'text-red-600'">{{ seatStats.ticketsRemaining }}</div>
                            <div class="text-sm text-muted-foreground">Tickets Remaining</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ seatStats.selected }}</div>
                            <div class="text-sm text-muted-foreground">Currently Selected</div>
                        </div>
                    </div>

                    <!-- Progress bar for remaining tickets -->
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">Remaining Tickets</span>
                            <span class="text-sm text-muted-foreground">{{ Math.round((seatStats.booked / seatStats.maxTickets) * 100) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div
                                class="h-2 rounded-full transition-all duration-300"
                                :class="seatStats.isOverLimit ? 'bg-red-500' : seatStats.ticketsRemaining === 0 ? 'bg-yellow-500' : 'bg-emerald-500'"
                                :style="{ width: Math.min(100, (seatStats.booked / seatStats.maxTickets) * 100) + '%' }"
                            ></div>
                        </div>
                        <div v-if="seatStats.isOverLimit" class="mt-2 text-sm text-red-600 font-medium">
                            ⚠️ {{ seatStats.booked - seatStats.maxTickets }} tickets over limit
                        </div>
                        <div v-else-if="seatStats.ticketsRemaining === 0" class="mt-2 text-sm text-yellow-600 font-medium">
                            🎟️ Event is sold out
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Main Content: Side by Side Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Side: Seat Layout -->
            <Card>
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
                            :selected-seats="selectedSeats"
                            @seats-changed="handleSeatsChanged"
                            @booked-seat-click="handleBookedSeatClick"
                            :admin-mode="true"
                        />
                    </div>
                    <div v-else class="text-center py-8 text-muted-foreground">
                        No seating layout configured for this room
                    </div>
                </CardContent>
            </Card>

            <!-- Right Side: Booking Management -->
            <Card>
                <CardHeader>
                    <CardTitle>Booking Management</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Manual Booking Form -->
                        <div v-if="selectedSeats.length > 0" class="border rounded-lg p-4 bg-blue-50">
                            <div class="flex items-center gap-2 mb-3">
                                <UserPlus class="h-4 w-4 text-blue-600" />
                                <h3 class="font-medium text-blue-900">Manual Booking</h3>
                            </div>

                            <!-- Selected Seats Display -->
                            <div v-if="selectedSeats.length > 0" class="mb-3">
                                <Label class="text-sm text-muted-foreground">Selected Seats:</Label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <Badge v-for="seat in selectedSeatInfo" :key="seat.id" variant="secondary" class="text-xs">
                                        {{ seat.label }}
                                    </Badge>
                                </div>
                            </div>
                            <div v-else class="mb-3 text-sm text-muted-foreground">
                                Select seats from the layout to book
                            </div>

                            <!-- Simple Name and Comment Form -->
                            <div class="space-y-3">
                                <div>
                                    <Label for="guestName" class="text-sm">Name</Label>
                                    <Input
                                        id="guestName"
                                        v-model="manualBookingForm.guestName"
                                        placeholder="Name or team"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label for="comment" class="text-sm">Comment (optional)</Label>
                                    <Input
                                        id="comment"
                                        v-model="manualBookingForm.comment"
                                        placeholder="Any additional notes..."
                                        class="mt-1"
                                    />
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2 pt-2">
                                    <Button
                                        @click="processManualBooking"
                                        :disabled="selectedSeats.length === 0 || !manualBookingForm.guestName.trim() || manualBookingForm.isProcessing"
                                        class="flex-1"
                                        size="sm"
                                    >
                                        <Plus class="h-3 w-3 mr-1" />
                                        {{ manualBookingForm.isProcessing ? 'Booking...' : `Book ${selectedSeats.length} Seat${selectedSeats.length !== 1 ? 's' : ''}` }}
                                    </Button>
                                    <Button
                                        @click="clearManualBooking"
                                        variant="outline"
                                        size="sm"
                                    >
                                        Clear
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Bookings List -->
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-medium">Current Bookings ({{ (bookings.data || bookings).length }}{{ bookings.total && bookings.total !== (bookings.data || bookings).length ? ` of ${bookings.total}` : '' }})</h3>
                                <div class="w-64 relative">
                                    <Input
                                        v-model="searchQuery"
                                        @input="debouncedSearch"
                                        placeholder="Search by name, seat, or comment..."
                                        class="h-8 text-sm pr-8"
                                    />
                                    <button
                                        v-if="searchQuery || props.booking_id"
                                        @click="clearSearch"
                                        class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        :title="props.booking_id && !searchQuery ? 'Clear highlighted booking' : 'Clear search'"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <!-- Simple Booking Table -->
                        <div class="overflow-x-auto">
                            <Table>
                                <thead class="sticky top-0 bg-white">
                                <tr class="border-b">
                                    <th class="text-left p-2 text-xs">Name</th>
                                    <th class="text-left p-2 text-xs">Seat</th>
                                    <th class="text-left p-2 text-xs">Time</th>
                                    <th class="text-left p-2 text-xs">Picked Up</th>
                                    <th class="text-left p-2 text-xs">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr
                                    v-for="booking in (bookings.data || bookings)"
                                    :key="booking.id"
                                    :id="`booking-${booking.id}`"
                                    :class="[
                                        'border-b hover:bg-gray-50 transition-colors',
                                        { 'bg-blue-100 border-blue-300': props.booking_id && parseInt(props.booking_id) === booking.id }
                                    ]"
                                >
                                    <td class="p-2">
                                        <div class="text-sm font-medium">{{ getBookingDisplayName(booking) }}</div>
                                        <div class="text-xs text-muted-foreground flex items-center gap-1">
                                            {{ getBookerInfo(booking) }}
                                            <Popover v-if="booking.comment">
                                                <PopoverTrigger>
                                                    <Info class="h-3 w-3 text-blue-500 hover:text-blue-700 cursor-pointer" />
                                                </PopoverTrigger>
                                                <PopoverContent class="w-80">
                                                    <div class="space-y-2">
                                                        <h4 class="font-medium">Comment</h4>
                                                        <p class="text-sm text-muted-foreground">{{ booking.comment }}</p>
                                                    </div>
                                                </PopoverContent>
                                            </Popover>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <div class="text-sm">{{ getSeatInfo(booking) }}</div>
                                    </td>
                                    <td class="p-2">
                                        <div class="text-xs">{{ dayjs(booking.created_at).format('MMM DD') }}</div>
                                        <div class="text-xs text-muted-foreground">{{ dayjs(booking.created_at).format('HH:mm') }}</div>
                                    </td>
                                    <td class="p-2">
                                        <input
                                            type="checkbox"
                                            :checked="!!booking.picked_up_at"
                                            @change="togglePickup(booking)"
                                            class="cursor-pointer h-4 w-4"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <div class="flex gap-1">
                                            <Button
                                                @click="openEditModal(booking)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-7 w-7 p-0"
                                            >
                                                <Pencil class="h-3 w-3" />
                                            </Button>
                                            <Button
                                                @click="openDeleteModal(booking)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-7 w-7 p-0 text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="(bookings.data || bookings).length === 0">
                                    <td colspan="5" class="text-center p-4 text-muted-foreground text-sm">
                                        {{ searchQuery.trim() ? 'No bookings match your search' : 'No bookings yet' }}
                                    </td>
                                </tr>
                                </tbody>
                            </Table>
                        </div>

                            <!-- Pagination -->
                            <div v-if="bookings.links" class="flex justify-center">
                                <nav class="flex space-x-1">
                                    <template v-for="(link, index) in bookings.links" :key="index">
                                        <Button
                                            v-if="link.url"
                                            variant="outline"
                                            size="sm"
                                            @click="router.visit(link.url, { preserveState: true, preserveScroll: true, only: ['bookings'] })"
                                            :disabled="!link.url"
                                            v-html="link.label"
                                            class="text-xs px-2"
                                        />
                                        <span v-else class="px-2 py-1 text-xs text-muted-foreground" v-html="link.label"/>
                                    </template>
                                </nav>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Booking Modal -->
        <Dialog v-model:open="editModal.show">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Booking</DialogTitle>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <Label for="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            v-model="editModal.name"
                            placeholder="Name or team"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <Label for="edit-comment">Comment</Label>
                        <Textarea
                            id="edit-comment"
                            v-model="editModal.comment"
                            placeholder="Additional notes..."
                            class="mt-1"
                            rows="3"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="editModal.show = false"
                        :disabled="editModal.isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="saveEditedBooking"
                        :disabled="editModal.isProcessing || !editModal.name.trim()"
                    >
                        {{ editModal.isProcessing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:open="deleteModal.show">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Booking</DialogTitle>
                </DialogHeader>
                <div class="py-4">
                    <p class="text-sm text-muted-foreground">
                        Are you sure you want to delete this booking?
                    </p>
                    <div v-if="deleteModal.booking" class="mt-4 p-3 bg-gray-50 rounded-md">
                        <div class="text-sm">
                            <div><strong>Name:</strong> {{ getBookingDisplayName(deleteModal.booking) }}</div>
                            <div><strong>Seat:</strong> {{ getSeatInfo(deleteModal.booking) }}</div>
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="deleteModal.show = false"
                        :disabled="deleteModal.isProcessing"
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deleteBooking"
                        :disabled="deleteModal.isProcessing"
                    >
                        {{ deleteModal.isProcessing ? 'Deleting...' : 'Delete Booking' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </div>
</template>
