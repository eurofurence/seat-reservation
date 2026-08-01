<script setup>
import {Head, router, useForm} from '@inertiajs/vue3'
import {ref, computed, nextTick, watch} from 'vue'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import SeatLayout from '@/Components/SeatLayout.vue'
import BookingIdentityCell from '@/Components/Admin/BookingIdentityCell.vue'
import { Card } from '@/Components/ui/card'
import { CardHeader } from '@/Components/ui/card'
import { CardTitle } from '@/Components/ui/card'
import { CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Checkbox } from '@/Components/ui/checkbox'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import {Download, Upload, Calendar, Clock, MapPin, Users, Plus, UserPlus, X, Pencil, Trash2, Info} from 'lucide-vue-next'
import dayjs, { fmt } from '@/lib/datetime'
import SeatStatistics from '@/Components/Admin/SeatStatistics.vue'
import ImportDialog from '@/Components/Admin/ImportDialog.vue'
import EditBookingDialog from '@/Components/Admin/EditBookingDialog.vue'
import DeleteBookingDialog from '@/Components/Admin/DeleteBookingDialog.vue'
import { useCsvDownload } from '@/composables/useCsvDownload'
import { getGuestName, getSeatInfo } from '@/lib/bookingDisplay'

defineOptions({layout: AdminLayout})

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    stageBlocks: Array,
    markerBlocks: { type: Array, default: () => [] },
    bookings: Array,
    bookedSeats: Array,
    seatBookingMap: Object,
    search: String,
    bookingcode: String,
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

// Edit/Delete modal state (dialog UI lives in EditBookingDialog/DeleteBookingDialog)
const editingBooking = ref(null)
const editOpen = ref(false)

const deletingBooking = ref(null)
const deleteOpen = ref(false)

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
    const availableCount = totalSeats - bookedCount

    const maxTickets = props.event.max_tickets || totalSeats
    const ticketsRemaining = Math.max(0, maxTickets - bookedCount)

    return {
        total: totalSeats,
        available: availableCount,
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
            preserveScroll: true,
            only: ['bookings', 'booking_id']
        })
    }
}

// Registration status computed property
const registrationStatus = computed(() => {
    if (!props.event.reservation_ends_at) {
        return { isOpen: true, status: 'Open', color: 'text-emerald-600' }
    }

    const reservationEnd = dayjs(props.event.reservation_ends_at)
    const isOpen = !dayjs().isAfter(reservationEnd)

    return {
        isOpen,
        status: isOpen ? 'Open' : 'Closed',
        color: isOpen ? 'text-emerald-600' : 'text-red-600'
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
const processManualBooking = () => {
    if (selectedSeats.value.length === 0) {
        alert('Please select at least one seat')
        return
    }

    if (!manualBookingForm.value.guestName.trim()) {
        alert('Please enter a name')
        return
    }

    const form = useForm({
        guest_name: manualBookingForm.value.guestName,
        comment: manualBookingForm.value.comment,
        seat_ids: selectedSeats.value
    })

    manualBookingForm.value.isProcessing = true

    form.post(route('admin.events.manual-booking', props.event.id), {
        onSuccess: () => {
            // Reset form and clear selected seats
            manualBookingForm.value = { guestName: '', comment: '', isProcessing: false }
            selectedSeats.value = []

            // Clear selected seats from URL by navigating without the selected_seats parameter
            const params = {}
            if (props.search) params.search = props.search
            if (props.booking_id) params.booking_id = props.booking_id

            router.get(route('admin.events.show', props.event.id), params, {
                preserveState: false, // Don't preserve state to force a full reload
                preserveScroll: true
            })
        },
        onFinish: () => {
            manualBookingForm.value.isProcessing = false
        }
    })
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
const togglePickup = (booking, event) => {
    const isRevert = !!booking.picked_up_at

    if (isRevert && !confirm('This ticket is already marked as picked up. Are you sure you want to revert it to unpicked?')) {
        event.target.checked = true // undo the native checkbox toggle, :checked isn't a v-model
        return
    }

    router.post(route('admin.events.toggle-pickup', props.event.id), {
        booking_id: booking.id,
        picked_up: !isRevert,
    }, {
        preserveScroll: true,
        onError: () => {
            // Revert the checkbox to its previous state if the request failed - the flash
            // toast from AdminLayout will surface the error message.
            event.target.checked = isRevert
        },
    })
}

// Open edit modal
const openEditModal = (booking) => {
    editingBooking.value = booking
    editOpen.value = true
}

// Open delete modal
const openDeleteModal = (booking) => {
    deletingBooking.value = booking
    deleteOpen.value = true
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

// Clear search, booking code filter, and booking highlight
const clearSearch = () => {
    searchQuery.value = ''
    const params = {}
    // Preserve selected seats when clearing search
    if (selectedSeats.value.length > 0) {
        params.selected_seats = selectedSeats.value.join(',')
    }

    router.get(route('admin.events.show', props.event.id),
        params, // Preserve selected seats but clear search, booking_code, and booking_id
        {
            preserveState: true,
            preserveScroll: true,
            only: ['bookings', 'search', 'bookingcode', 'booking_id']
        }
    )
}

const includeUnpicked = ref(false)

const { download } = useCsvDownload()

const exportBookings = () => {
    download(route('admin.events.export', props.event.id), `bookings-${props.event.id}-${Date.now()}.csv`)
}

const printSeatCards = () => {
    const url = new URL(route('admin.events.seating-cards', props.event.id), window.location.origin)
    if (includeUnpicked.value) {
        url.searchParams.set('include_unpicked', '1')
    }
    window.open(url.toString(), '_blank')
}

// Bulk booking import (dialog UI lives in ImportDialog)
const importOpen = ref(false)

// Watch for selected_seats prop changes (when navigating back from validation)
watch(() => props.selected_seats, (newSelectedSeats) => {
    if (newSelectedSeats) {
        const seatIds = newSelectedSeats.split(',').map(id => parseInt(id)).filter(Boolean)
        selectedSeats.value = seatIds
    } else {
        selectedSeats.value = []
    }
}, { immediate: true })

const navigateToPage = (linkUrl) => {
    const pageParam = new URL(linkUrl).searchParams.get('page')
    const params = {}
    if (props.search) params.search = props.search
    if (selectedSeats.value.length > 0) params.selected_seats = selectedSeats.value.join(',')
    if (pageParam) params.page = pageParam
    router.get(route('admin.events.show', props.event.id), params, { preserveState: true, preserveScroll: true, only: ['bookings'] })
}
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
                    <div v-if="event.booking_starts_at" class="text-sm text-muted-foreground">
                        Booking opens {{ fmt(event.booking_starts_at, 'MMM D, YYYY HH:mm') }}
                    </div>
                    <div v-if="event.reservation_ends_at" class="text-sm text-muted-foreground">
                        {{ registrationStatus.isOpen ? 'Closes' : 'Closed' }} {{ fmt(event.reservation_ends_at, 'MMM D, YYYY HH:mm') }}
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
                        {{ fmt(event.starts_at, 'MMM D, YYYY') }}
                    </div>
                    <div class="flex items-center">
                        <Clock class="h-4 w-4 mr-1"/>
                        {{ fmt(event.starts_at, 'HH:mm') }}
                    </div>
                    <div class="flex items-center">
                        <MapPin class="h-4 w-4 mr-1"/>
                        {{ room.name }}
                    </div>
                    <div class="flex items-center">
                        <Users class="h-4 w-4 mr-1"/>
                        {{ bookings.total ?? bookings.length }} bookings
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <div class="flex gap-2">
                        <Button @click="exportBookings">
                            <Download class="mr-2 h-4 w-4"/>
                            Export Bookings
                        </Button>
                        <Button @click="importOpen = true" variant="outline">
                            <Upload class="mr-2 h-4 w-4"/>
                            Import Bookings
                        </Button>
                        <Button @click="printSeatCards" variant="outline">
                            <Download class="mr-2 h-4 w-4"/>
                            Print Seat Cards
                        </Button>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer select-none">
                        <input type="checkbox" v-model="includeUnpicked" class="h-3 w-3" />
                        Include unpicked (seat cards)
                    </label>
                </div>
            </div>
        </div>

        <!-- Seat Statistics -->
        <div class="mb-6">
            <SeatStatistics :stats="seatStats" />
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
                            :stage-blocks="stageBlocks"
                            :marker-blocks="markerBlocks"
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
                                <div class="flex items-center gap-2">
                                    <!-- Show booking code filter indicator -->
                                    <div v-if="props.bookingcode" class="flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm font-medium">
                                        Code: {{ props.bookingcode }}
                                    </div>
                                    <div class="w-64 relative">
                                        <Input
                                            v-model="searchQuery"
                                            @input="debouncedSearch"
                                            :placeholder="props.bookingcode ? 'Filtered by booking code' : 'Search by name, seat, or comment...'"
                                            :disabled="!!props.bookingcode"
                                            class="h-8 text-sm pr-8"
                                            :class="{ 'bg-gray-50': props.bookingcode }"
                                        />
                                        <button
                                            v-if="searchQuery || props.bookingcode || props.booking_id"
                                            @click="clearSearch"
                                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                            :title="props.bookingcode ? 'Clear booking code filter' : (props.booking_id && !searchQuery ? 'Clear highlighted booking' : 'Clear search')"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <!-- Simple Booking Table -->
                        <div class="overflow-x-auto">
                            <Table>
                                <thead class="sticky top-0 bg-white">
                                <tr class="border-b">
                                    <th class="text-left p-2 text-xs">Name</th>
                                    <th class="text-left p-2 text-xs">Code</th>
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
                                        <BookingIdentityCell :booking="booking">
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
                                        </BookingIdentityCell>
                                    </td>
                                    <td class="p-2">
                                        <span v-if="booking.booking_code" class="text-sm font-mono bg-gray-100 px-1.5 py-0.5 rounded">{{ booking.booking_code }}</span>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                    <td class="p-2">
                                        <div class="text-sm">{{ getSeatInfo(booking) }}</div>
                                    </td>
                                    <td class="p-2">
                                        <div class="text-xs">{{ fmt(booking.created_at, 'MMM D') }}</div>
                                        <div class="text-xs text-muted-foreground">{{ fmt(booking.created_at, 'HH:mm') }}</div>
                                    </td>
                                    <td class="p-2">
                                        <input
                                            type="checkbox"
                                            :checked="!!booking.picked_up_at"
                                            @change="togglePickup(booking, $event)"
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
                                    <td colspan="6" class="text-center p-4 text-muted-foreground text-sm">
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
                                            :variant="link.active ? 'secondary' : 'outline'"
                                            size="sm"
                                            @click="navigateToPage(link.url)"
                                            v-html="link.label"
                                            :class="[
                                                'text-xs px-2',
                                                link.active && 'shadow-inner bg-gray-200 font-semibold'
                                            ]"
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

        <EditBookingDialog
            v-model:open="editOpen"
            :event-id="event.id"
            :booking="editingBooking"
        />

        <DeleteBookingDialog
            v-model:open="deleteOpen"
            :event-id="event.id"
            :booking="deletingBooking"
            :display-name="deletingBooking ? getGuestName(deletingBooking) : ''"
            :seat-info="deletingBooking ? getSeatInfo(deletingBooking) : ''"
        />

        <ImportDialog
            v-model:open="importOpen"
            :event-id="event.id"
        />

    </div>
</template>
