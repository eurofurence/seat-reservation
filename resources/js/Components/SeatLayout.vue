<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    event: Object,
    room: Object,
    blocks: Array,
    selectedSeats: {
        type: Array,
        default: () => []
    },
    bookedSeats: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['seat-selected', 'seats-changed'])

const localSelectedSeats = ref([...props.selectedSeats])

// Check if a seat is selected
function isSeatSelected(seatId) {
    return localSelectedSeats.value.includes(seatId)
}

// Check if a seat is booked
function isSeatBooked(seatId) {
    return props.bookedSeats.includes(seatId)
}

// Toggle seat selection
function toggleSeat(seat) {
    if (isSeatBooked(seat.id)) {
        return // Can't select booked seats
    }

    const index = localSelectedSeats.value.indexOf(seat.id)
    if (index > -1) {
        localSelectedSeats.value.splice(index, 1)
    } else {
        localSelectedSeats.value.push(seat.id)
    }
    
    emit('seats-changed', localSelectedSeats.value)
    emit('seat-selected', seat, !isSeatSelected(seat.id))
}

// Get seat class based on state
function getSeatClass(seat) {
    if (isSeatBooked(seat.id)) {
        return 'seat-booked'
    }
    if (isSeatSelected(seat.id)) {
        return 'seat-selected'
    }
    return 'seat-available'
}

// Get block style based on rotation
function getBlockStyle(block) {
    const rotationStyles = {
        0: {},
        90: {
            transform: 'rotate(90deg)',
            transformOrigin: 'center'
        },
        180: {
            transform: 'rotate(180deg)',
            transformOrigin: 'center'
        },
        270: {
            transform: 'rotate(270deg)',
            transformOrigin: 'center'
        }
    }
    
    return rotationStyles[block.rotation || 0]
}

// Check if block should display rows vertically (rotated)
function isBlockRotated(block) {
    return block.rotation === 90 || block.rotation === 270
}

// Calculate table layout grid positions
const layoutGrid = computed(() => {
    const grid = []
    let maxX = 0
    let maxY = 0
    
    // Find max positions
    props.blocks.forEach(block => {
        maxX = Math.max(maxX, block.position_x || 0)
        maxY = Math.max(maxY, block.position_y || 0)
    })
    
    // Create grid array
    for (let y = 0; y <= maxY; y++) {
        grid[y] = []
        for (let x = 0; x <= maxX; x++) {
            grid[y][x] = null
        }
    }
    
    // Place blocks in grid
    props.blocks.forEach(block => {
        const x = block.position_x || 0
        const y = block.position_y || 0
        if (grid[y] && grid[y][x] !== undefined) {
            grid[y][x] = block
        }
    })
    
    return grid
})

// Check if we should use grid layout or simple list
const useGridLayout = computed(() => {
    return props.blocks.some(block => 
        (block.position_x !== null && block.position_x !== undefined && block.position_x > 0) ||
        (block.position_y !== null && block.position_y !== undefined && block.position_y > 0)
    )
})
</script>

<template>
    <div class="seat-layout-container">
        <!-- Stage/Center indicator -->
        <div class="stage-indicator">
            <div class="stage">STAGE</div>
        </div>
        
        <!-- Grid Layout (for positioned blocks) -->
        <div v-if="useGridLayout" class="seat-grid">
            <table class="layout-table">
                <tbody>
                    <tr v-for="(row, y) in layoutGrid" :key="y">
                        <td v-for="(cell, x) in row" :key="x" class="layout-cell">
                            <div v-if="cell" class="block-container" :style="getBlockStyle(cell)">
                                <div class="block">
                                    <div class="block-name">{{ cell.name }}</div>
                                    <div :class="['block-seats', { 'rotated': isBlockRotated(cell) }]">
                                        <div v-for="row in cell.rows" :key="row.id" class="seat-row">
                                            <div class="row-label">{{ row.name }}</div>
                                            <div class="seats">
                                                <button
                                                    v-for="seat in row.seats"
                                                    :key="seat.id"
                                                    :class="['seat', getSeatClass(seat)]"
                                                    :title="`${cell.name} - ${row.name} - ${seat.label}`"
                                                    @click="toggleSeat(seat)"
                                                    :disabled="isSeatBooked(seat.id)"
                                                >
                                                    {{ seat.label }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Simple Layout (for non-positioned blocks) -->
        <div v-else class="seat-blocks">
            <div v-for="block in blocks" :key="block.id" class="block-container" :style="getBlockStyle(block)">
                <div class="block">
                    <div class="block-name">{{ block.name }}</div>
                    <div :class="['block-seats', { 'rotated': isBlockRotated(block) }]">
                        <div v-for="row in block.rows" :key="row.id" class="seat-row">
                            <div class="row-label">{{ row.name }}</div>
                            <div class="seats">
                                <button
                                    v-for="seat in row.seats"
                                    :key="seat.id"
                                    :class="['seat', getSeatClass(seat)]"
                                    :title="`${block.name} - ${row.name} - ${seat.label}`"
                                    @click="toggleSeat(seat)"
                                    :disabled="isSeatBooked(seat.id)"
                                >
                                    {{ seat.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="seat-legend">
            <div class="legend-item">
                <span class="seat seat-available"></span>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <span class="seat seat-selected"></span>
                <span>Selected</span>
            </div>
            <div class="legend-item">
                <span class="seat seat-booked"></span>
                <span>Booked</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.seat-layout-container {
    width: 100%;
    padding: 20px;
    overflow-x: auto;
    overflow-y: auto;
    max-height: 600px;
    background: #f5f5f5;
    border-radius: 8px;
}

.stage-indicator {
    text-align: center;
    margin-bottom: 30px;
}

.stage {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 60px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 18px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Grid Layout */
.seat-grid {
    display: flex;
    justify-content: center;
    margin-bottom: 30px;
}

.layout-table {
    border-spacing: 20px;
}

.layout-cell {
    padding: 0;
    vertical-align: top;
}

/* Simple Layout */
.seat-blocks {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    margin-bottom: 30px;
}

/* Block Styling */
.block-container {
    transition: transform 0.3s ease;
}

.block {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.block-name {
    font-weight: bold;
    text-align: center;
    margin-bottom: 10px;
    padding: 5px;
    background: #f0f0f0;
    border-radius: 4px;
}

.block-seats {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.block-seats.rotated {
    flex-direction: row;
}

.block-seats.rotated .seat-row {
    flex-direction: column;
}

.block-seats.rotated .seats {
    flex-direction: column;
}

.seat-row {
    display: flex;
    align-items: center;
    gap: 5px;
}

.row-label {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    min-width: 30px;
    text-align: right;
    padding-right: 5px;
}

.seats {
    display: flex;
    gap: 2px;
    flex-wrap: wrap;
}

/* Seat Styling */
.seat {
    width: 25px;
    height: 25px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

.seat-available {
    background: #e8f5e9;
    color: #2e7d32;
}

.seat-available:hover {
    background: #c8e6c9;
    transform: scale(1.1);
}

.seat-selected {
    background: #2196f3;
    color: white;
    border-color: #1976d2;
}

.seat-booked {
    background: #ffcdd2;
    color: #c62828;
    cursor: not-allowed;
    opacity: 0.6;
}

.seat:disabled {
    cursor: not-allowed;
}

/* Legend */
.seat-legend {
    display: flex;
    justify-content: center;
    gap: 30px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-item .seat {
    cursor: default;
}

/* Responsive */
@media (max-width: 768px) {
    .seat-layout-container {
        padding: 10px;
    }
    
    .stage {
        padding: 10px 30px;
        font-size: 14px;
    }
    
    .seat {
        width: 20px;
        height: 20px;
        font-size: 9px;
    }
}
</style>