<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted } from 'vue'

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

// Manual print function
const printPage = () => {
    if (typeof window !== 'undefined' && window.print) {
        window.print()
    }
}
</script>

<style scoped>
@media print {
    @page { 
        size: A4 landscape; 
        margin: 15mm; 
    }
    
    .ticket {
        width: 100%;
        height: 180mm;
        page-break-after: always;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        padding: 20mm;
        border: 3px solid #000;
        background: white;
    }
    
    .ticket:last-child { 
        page-break-after: avoid; 
    }
    
    .no-print { 
        display: none; 
    }
}
</style>

<template>
    <Head :title="title" />
    
    <div class="min-h-screen bg-gray-100 p-5">
        
        <!-- Screen-only controls -->
        <div class="no-print fixed top-5 right-5 bg-white p-4 rounded-lg shadow-lg z-50">
            <div class="mb-2 font-semibold">{{ bookings.length }} tickets ready</div>
            <button @click="printPage" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                Print All Tickets
            </button>
        </div>
        
        <!-- Generate a ticket for each booking -->
        <div v-for="booking in bookings" :key="booking.id" 
             class="ticket w-full max-w-[297mm] h-[210mm] mx-auto mb-5 bg-white shadow-lg border-2 border-black flex flex-col justify-center items-center text-center p-12">
            <div class="reserved-text text-6xl font-black uppercase tracking-wider mb-16 border-2 border-black px-12 py-8 bg-gray-50">
                RESERVED
            </div>
            
            <div class="seat-info text-2xl font-bold mb-12 leading-relaxed">
                <div>{{ booking.seat.row.block.name }}</div>
                <div>{{ booking.seat.row.name }}</div>
                <div>Seat {{ booking.seat.label }}</div>
            </div>
            
            <div class="for-text text-3xl font-black uppercase mb-8 tracking-wide">
                FOR
            </div>
            
            <div class="name-text text-2xl font-semibold px-8 py-4 border-2 border-black bg-gray-50">
                {{ getBookingDisplayName(booking) }}
            </div>
        </div>
    </div>
</template>