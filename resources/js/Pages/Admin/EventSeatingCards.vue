<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted } from 'vue'
import seatingCardBg from '@/../assets/images/seating_card.svg'

defineOptions({ layout: null }) // No layout for print page

const props = defineProps({
    event: Object,
    bookings: Array,
    title: String
})

// Get display name for booking
const getBookingDisplayName = (booking) => {
    if (booking.guest_name) {
        return booking.guest_name
    }
    if (booking.name) {
        return booking.name
    }
    if (booking.user) {
        return booking.user.name
    }
    return 'Unknown'
}

// Get seat location info
const getSeatLocation = (booking) => {
    const block = booking.seat.row.block.name
    const row = booking.seat.row.name
    const seat = booking.seat.label
    return `${block} ${row} ${seat}`
}

// Manual print function
const printPage = () => {
    if (typeof window !== 'undefined' && window.print) {
        window.print()
    }
}

// Group bookings into chunks of 4 for printing
const chunkedBookings = () => {
    const chunks = []
    for (let i = 0; i < props.bookings.length; i += 4) {
        chunks.push(props.bookings.slice(i, i + 4))
    }
    return chunks
}
</script>

<style scoped>
@media print {
    @page { 
        size: A4 portrait; 
        margin: 10mm; 
    }
    
    .print-page {
        width: 210mm;
        height: 297mm;
        page-break-after: always;
    }
    
    .print-page:last-child { 
        page-break-after: avoid; 
    }
    
    .no-print { 
        display: none; 
    }
    
    .seating-card-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        width: 100%;
        height: 100%;
        gap: 5mm;
    }
    
    .seating-card {
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 15mm;
        position: relative;
    }
    
    .card-name {
        font-family: 'Orbitron', monospace;
        font-size: 24px;
        font-weight: 700;
        color: #000;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .card-location {
        font-family: 'Orbitron', monospace;
        font-size: 16px;
        font-weight: 500;
        color: #000;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
}

@media screen {
    .seating-card-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        width: 100%;
        height: 100%;
        gap: 10px;
    }
    
    .seating-card {
        width: 100%;
        height: 200px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 20px;
        border: 2px solid #ccc;
        border-radius: 8px;
        position: relative;
    }
    
    .card-name {
        font-family: 'Orbitron', monospace;
        font-size: 18px;
        font-weight: 700;
        color: #000;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .card-location {
        font-family: 'Orbitron', monospace;
        font-size: 14px;
        font-weight: 500;
        color: #000;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .print-page {
        width: 100%;
        max-width: 800px;
        height: 600px;
        margin-bottom: 20px;
    }
}
</style>

<template>
    <Head :title="title" />
    
    <div class="min-h-screen bg-gray-100 p-5">
        
        <!-- Screen-only controls -->
        <div class="no-print fixed top-5 right-5 bg-white p-4 rounded-lg shadow-lg z-50">
            <div class="mb-2 font-semibold">{{ bookings.length }} seating cards ready</div>
            <div class="mb-2 text-sm text-gray-600">{{ chunkedBookings().length }} pages (4 cards per page)</div>
            <button @click="printPage" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                Print All Cards
            </button>
        </div>
        
        <!-- Generate pages with 4 cards each -->
        <div v-for="(chunk, pageIndex) in chunkedBookings()" :key="pageIndex" 
             class="print-page mx-auto bg-white shadow-lg">
            <div class="seating-card-grid">
                <!-- Each card in the 2x2 grid -->
                <div v-for="booking in chunk" :key="booking.id" 
                     class="seating-card"
                     :style="{ backgroundImage: `url(${seatingCardBg})` }">
                    <div class="card-name">
                        {{ getBookingDisplayName(booking) }}
                    </div>
                    <div class="card-location">
                        Block {{ booking.seat.row.block.name }}<br>
                        Row {{ booking.seat.row.name }}<br>
                        Seat {{ booking.seat.label }}
                    </div>
                </div>
                
                <!-- Fill empty slots if less than 4 bookings in chunk -->
                <div v-for="n in (4 - chunk.length)" :key="`empty-${n}`" 
                     class="seating-card"
                     :style="{ backgroundImage: `url(${seatingCardBg})` }">
                    <!-- Empty card -->
                </div>
            </div>
        </div>
        
        <!-- Show message if no bookings -->
        <div v-if="bookings.length === 0" class="text-center py-20">
            <div class="text-gray-500 text-xl">No bookings found for this event</div>
        </div>
    </div>
</template>