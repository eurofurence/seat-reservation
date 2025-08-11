<script setup>
import { computed, ref, onMounted } from 'vue'
import { Armchair, Users, Theater } from 'lucide-vue-next'

const props = defineProps({
    room: Object,
    blocks: Array,
    layoutConfig: Object,
    selectedSeats: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['seat-selected', 'seat-deselected'])

// Grid computed styles
const gridStyle = computed(() => {
    if (!props.layoutConfig) {
        return {
            display: 'grid',
            gridTemplateColumns: 'repeat(12, minmax(120px, 1fr))',
            gridTemplateRows: 'repeat(8, minmax(80px, auto))',
            gap: '48px',
            minHeight: 'max-content',
            width: 'max-content',
            minWidth: '100%',
            padding: '2rem',
            background: '#f8f9fa',
            border: '2px solid #dee2e6',
            borderRadius: '12px'
        }
    }
    
    return {
        display: 'grid',
        gridTemplateColumns: `repeat(${props.layoutConfig.grid_columns || 12}, minmax(120px, 1fr))`,
        gridTemplateRows: `repeat(${props.layoutConfig.grid_rows || 8}, minmax(80px, auto))`,
        gap: `${props.layoutConfig.gap_size || 48}px`,
        minHeight: 'max-content',
        width: 'max-content',
        minWidth: '100%',
        padding: '2rem',
        background: '#f8f9fa',
        border: '2px solid #dee2e6',
        borderRadius: '12px'
    }
})

// Check if seat is selected
function isSeatSelected(blockId, rowId, seatId) {
    return props.selectedSeats.some(seat => 
        seat.blockId === blockId && seat.rowId === rowId && seat.seatId === seatId
    )
}

// Handle seat selection
function toggleSeat(block, row, seat) {
    const seatData = {
        blockId: block.id,
        rowId: row.id,
        seatId: seat.id,
        blockName: block.name,
        rowName: row.name,
        seatNumber: seat.number || seat.name
    }
    
    if (isSeatSelected(block.id, row.id, seat.id)) {
        emit('seat-deselected', seatData)
    } else {
        emit('seat-selected', seatData)
    }
}

// Compute arrow rotation for stage direction
function getArrowRotation(rotation) {
    return `rotate(${rotation || 0}deg)`
}

// Generate seats for display
function getSeatsForRow(row) {
    if (row.seats && row.seats.length > 0) {
        return row.seats
    }
    
    const seatCount = row.seat_count || 0
    return Array.from({ length: seatCount }, (_, i) => ({
        id: i + 1,
        number: i + 1,
        name: String(i + 1),
        is_available: true
    }))
}
</script>

<template>
    <div class="floor-plan-viewer">
        <!-- Room Header -->
        <div class="room-header">
            <h2>{{ room?.name || 'Floor Plan' }}</h2>
            <p class="room-subtitle">Select your seats</p>
        </div>

        <!-- Grid Container -->
        <div class="grid-container">
            <div
                class="grid-viewer"
                :style="gridStyle"
            >
                <!-- Grid Blocks -->
                <div
                    v-for="block in blocks"
                    :key="block.id"
                    class="viewer-block"
                    :style="{
                        gridColumn: `${block.grid_column} / span ${block.grid_column_span}`,
                        gridRow: `${block.grid_row} / span ${block.grid_row_span}`
                    }"
                >
                    <!-- Block Header -->
                    <div class="block-header">
                        <div class="block-info">
                            <Armchair :size="16" class="block-icon" />
                            <span class="block-name">{{ block.name }}</span>
                            <!-- Stage direction arrow -->
                            <div v-if="block.rotation" class="stage-arrow" :style="{ transform: getArrowRotation(block.rotation) }" title="Stage direction">
                                ↑
                            </div>
                        </div>
                        <div class="block-stats">
                            <Users :size="14" />
                            <span class="seat-count">{{ block.rows?.reduce((sum, row) => sum + (row.seat_count || 0), 0) || 0 }}</span>
                        </div>
                    </div>

                    <!-- Seat Visualization -->
                    <div class="seats-container">
                        <div 
                            v-for="row in block.rows" 
                            :key="row.id"
                            class="seat-row"
                        >
                            <div class="row-label">{{ row.name }}</div>
                            <div class="seats">
                                <button
                                    v-for="seat in getSeatsForRow(row)"
                                    :key="seat.id"
                                    class="seat"
                                    :class="{
                                        'seat-available': seat.is_available && !seat.is_booked && !isSeatSelected(block.id, row.id, seat.id),
                                        'seat-selected': isSeatSelected(block.id, row.id, seat.id),
                                        'seat-booked': seat.is_booked,
                                        'seat-unavailable': !seat.is_available
                                    }"
                                    :disabled="!seat.is_available || seat.is_booked"
                                    @click="toggleSeat(block, row, seat)"
                                    :title="`Seat ${seat.number || seat.name} - ${seat.is_booked ? 'Booked' : seat.is_available ? 'Available' : 'Unavailable'}`"
                                >
                                    {{ seat.number || seat.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stage Element -->
                <div
                    v-if="layoutConfig?.stage"
                    class="viewer-stage"
                    :style="{
                        gridColumn: `${layoutConfig.stage.grid_column} / span ${layoutConfig.stage.grid_column_span}`,
                        gridRow: `${layoutConfig.stage.grid_row} / span ${layoutConfig.stage.grid_row_span}`
                    }"
                >
                    <div class="stage-content">
                        <Theater :size="24" />
                        <span>STAGE</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.floor-plan-viewer {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.room-header {
    padding: 1rem 2rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
    text-align: center;
}

.room-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #212529;
}

.room-subtitle {
    margin: 0;
    color: #6c757d;
    font-size: 0.875rem;
}

.grid-container {
    flex: 1;
    padding: 2rem;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    background: #f8f9fa;
}

.grid-viewer {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    user-select: none;
}

.viewer-block {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.viewer-block:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.block-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.block-icon {
    color: #666;
}

.block-name {
    font-weight: 600;
    color: #333;
    font-size: 0.875rem;
}

.stage-arrow {
    margin-left: 0.5rem;
    font-size: 1.2rem;
    color: #2196f3;
    font-weight: bold;
    display: inline-block;
    transition: transform 0.3s ease;
    transform-origin: center;
}

.block-stats {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    color: #666;
    font-size: 0.75rem;
}

.seats-container {
    flex: 1;
    overflow: hidden;
}

.seat-row {
    margin-bottom: 0.5rem;
}

.row-label {
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.seats {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
}

.seat {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    border: 1px solid #ddd;
    background: #f5f5f5;
    color: #666;
    cursor: pointer;
    transition: all 0.2s;
}

.seat:hover:not(:disabled) {
    transform: scale(1.1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.seat-available {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #2e7d32;
}

.seat-available:hover {
    background: #c8e6c8;
    border-color: #388e3c;
}

.seat-selected {
    background: #2196f3;
    border-color: #1976d2;
    color: white;
    transform: scale(1.1);
}

.seat-selected:hover {
    background: #1976d2;
    border-color: #1565c0;
}

.seat-booked {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e65100;
    cursor: not-allowed;
}

.seat-unavailable {
    background: #ffebee;
    border-color: #f44336;
    color: #c62828;
    cursor: not-allowed;
}

.seat:disabled {
    opacity: 0.6;
}

.viewer-stage {
    background: linear-gradient(135deg, #6a5acd, #483d8b);
    border: 2px solid #4b0082;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.stage-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    text-align: center;
}
</style>