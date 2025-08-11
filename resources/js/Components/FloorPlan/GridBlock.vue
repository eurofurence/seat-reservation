<script setup>
import { computed, ref } from 'vue'
import { Armchair, Users, Settings } from 'lucide-vue-next'

const props = defineProps({
    block: Object,
    isPreview: Boolean,
    gridConfig: Object
})

const emit = defineEmits(['grid-position-change'])

const isDragging = ref(false)
const dragStartPosition = ref({ x: 0, y: 0 })

// Grid positioning computed properties
const gridItemStyle = computed(() => {
    const rotation = props.block.rotation || 0
    const isRotated = rotation === 90 || rotation === 270
    
    let minWidthFromLayout = 'auto'
    
    if (seatLayout.value.rows.length > 0) {
        if (isRotated) {
            // When rotated, width is based on number of rows (each row becomes a column)
            // Each row column is ~40px wide + gap + padding
            const rowCount = seatLayout.value.rows.length
            minWidthFromLayout = rowCount > 0 ? (rowCount * 48 + 32) + 'px' : 'auto'
        } else {
            // When not rotated, width is based on maximum seats in any row
            const maxSeatsInRow = Math.max(...seatLayout.value.rows.map(row => row.seat_count || 0))
            minWidthFromLayout = maxSeatsInRow > 0 ? (maxSeatsInRow * 26 + 32) + 'px' : 'auto'
        }
    }
    
    return {
        gridColumn: `${props.block.grid_column} / span ${props.block.grid_column_span}`,
        gridRow: `${props.block.grid_row} / span ${props.block.grid_row_span}`,
        backgroundColor: props.block.isSelected ? '#e3f2fd' : 'white',
        border: props.block.isSelected ? '2px solid #2196f3' : '1px solid #dee2e6',
        borderRadius: '8px',
        padding: '1rem',
        minHeight: '120px',
        minWidth: minWidthFromLayout,
        display: 'flex',
        flexDirection: 'column',
        cursor: props.isPreview ? 'default' : (isDragging.value ? 'grabbing' : 'grab'),
        transition: isDragging.value ? 'none' : 'all 0.2s ease',
        overflow: 'hidden',
        opacity: isDragging.value ? 0.8 : 1,
        position: 'relative'
    }
})

// Compute arrow rotation for stage direction
const arrowRotation = computed(() => {
    return `rotate(${props.block.rotation || 0}deg)`
})

// Check if block is rotated (90 or 270 degrees = vertical layout)
const isRotated = computed(() => {
    const rotation = props.block.rotation || 0
    return rotation === 90 || rotation === 270
})

// Calculate block dimensions based on percentage
const blockDimensions = computed(() => {
    if (!props.gridConfig || !props.block) {
        return { width: '100%', height: '100%', aspectRatio: 1 }
    }
    
    const widthPercentage = (props.block.grid_column_span / props.gridConfig.grid_columns) * 100
    const heightPercentage = (props.block.grid_row_span / props.gridConfig.grid_rows) * 100
    
    return {
        width: `${widthPercentage.toFixed(1)}%`,
        height: `${heightPercentage.toFixed(1)}%`,
        aspectRatio: props.block.grid_column_span / props.block.grid_row_span
    }
})

// Seat layout computation
const seatLayout = computed(() => {
    if (!props.block || !props.block.rows) return { rows: [], totalSeats: 0 }
    
    // Ensure rows have proper seat_count values
    const processedRows = props.block.rows.map(row => ({
        ...row,
        seat_count: row.seat_count || row.seats?.length || 0
    }))
    
    const totalSeats = processedRows.reduce((sum, row) => sum + row.seat_count, 0)
    
    return {
        rows: processedRows,
        totalSeats
    }
})

// Handle grid position updates
function updateGridPosition(updates) {
    if (props.block && props.block.id) {
        emit('grid-position-change', props.block.id, updates)
    }
}

// Handle drag start for repositioning
function handleMouseDown(event) {
    if (props.isPreview) return
    
    event.stopPropagation()
    
    // Click to select (not dragging yet)
    emit('click')
    
    // Start dragging on mousemove
    isDragging.value = false
    dragStartPosition.value = { x: event.clientX, y: event.clientY }
    
    function handleMouseMove(moveEvent) {
        const deltaX = Math.abs(moveEvent.clientX - dragStartPosition.value.x)
        const deltaY = Math.abs(moveEvent.clientY - dragStartPosition.value.y)
        
        // Start dragging if mouse moved enough
        if (!isDragging.value && (deltaX > 5 || deltaY > 5)) {
            isDragging.value = true
        }
        
        if (isDragging.value) {
            // Calculate which grid cell we're over
            const gridContainer = event.target.closest('.grid-editor')
            if (!gridContainer) return
            
            const gridRect = gridContainer.getBoundingClientRect()
            const cellWidth = gridRect.width / props.gridConfig.grid_columns
            const cellHeight = gridRect.height / props.gridConfig.grid_rows
            
            const relativeX = moveEvent.clientX - gridRect.left
            const relativeY = moveEvent.clientY - gridRect.top
            
            const newColumn = Math.max(1, Math.min(
                Math.floor(relativeX / cellWidth) + 1,
                props.gridConfig.grid_columns - props.block.grid_column_span + 1
            ))
            const newRow = Math.max(1, Math.min(
                Math.floor(relativeY / cellHeight) + 1,
                props.gridConfig.grid_rows - props.block.grid_row_span + 1
            ))
            
            if (newColumn !== props.block.grid_column || newRow !== props.block.grid_row) {
                updateGridPosition({
                    grid_column: newColumn,
                    grid_row: newRow
                })
            }
        }
    }
    
    function handleMouseUp() {
        isDragging.value = false
        document.removeEventListener('mousemove', handleMouseMove)
        document.removeEventListener('mouseup', handleMouseUp)
    }
    
    document.addEventListener('mousemove', handleMouseMove)
    document.addEventListener('mouseup', handleMouseUp)
}

function handleMouseUp() {
    isDragging.value = false
}

</script>

<template>
    <div
        class="grid-block"
        :style="gridItemStyle"
        @mousedown="handleMouseDown"
        @mouseup="handleMouseUp"
        @click.stop="$emit('click')"
    >
        <!-- Block Header -->
        <div class="block-header">
            <div class="block-info">
                <Armchair :size="16" class="block-icon" />
                <span class="block-name">{{ block.name }}</span>
                <!-- Stage direction arrow -->
                <div v-if="block.rotation" class="stage-arrow" :style="{ transform: arrowRotation }" title="Stage direction">
                    ↑
                </div>
            </div>
            <div class="block-stats">
                <Users :size="14" />
                <span class="seat-count">{{ seatLayout.totalSeats }}</span>
            </div>
        </div>

        <!-- Seat Visualization -->
        <div class="seats-container" :class="{ 'rotated': isRotated }">
            <div 
                v-for="row in seatLayout.rows" 
                :key="row.id"
                class="seat-row"
                :class="{ 'vertical': isRotated }"
            >
                <div class="row-label">{{ row.name || `Row ${row.id}` }}</div>
                <div class="seats" :class="{ 'vertical': isRotated }">
                    <div
                        v-for="seat in ((row.seats && row.seats.length > 0) ? row.seats : Array.from({ length: Math.max(0, parseInt(row.seat_count) || 0) }, (_, i) => ({ id: i + 1, number: i + 1, is_available: true })))"
                        :key="seat.id"
                        class="seat"
                        :class="{
                            'seat-available': seat.is_available && !seat.is_booked,
                            'seat-booked': seat.is_booked,
                            'seat-unavailable': !seat.is_available
                        }"
                        :title="`Seat ${seat.number || seat.name} - ${seat.is_booked ? 'Booked' : seat.is_available ? 'Available' : 'Unavailable'}`"
                    >
                        {{ seat.number || seat.name }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Block Controls (Edit Mode Only) -->
        <div v-if="!isPreview && block.isSelected" class="block-controls">
            <button 
                class="control-btn"
                @click.stop="updateGridPosition({ grid_column_span: Math.max(1, block.grid_column_span - 1) })"
                :disabled="block.grid_column_span <= 1"
                title="Decrease width"
            >
                W-
            </button>
            <button 
                class="control-btn"
                @click.stop="updateGridPosition({ grid_column_span: Math.min(gridConfig.grid_columns, block.grid_column_span + 1) })"
                :disabled="block.grid_column + block.grid_column_span > gridConfig.grid_columns"
                title="Increase width"
            >
                W+
            </button>
            <button 
                class="control-btn"
                @click.stop="updateGridPosition({ grid_row_span: Math.max(1, block.grid_row_span - 1) })"
                :disabled="block.grid_row_span <= 1"
                title="Decrease height"
            >
                H-
            </button>
            <button 
                class="control-btn"
                @click.stop="updateGridPosition({ grid_row_span: Math.min(gridConfig.grid_rows, block.grid_row_span + 1) })"
                :disabled="block.grid_row + block.grid_row_span > gridConfig.grid_rows"
                title="Increase height"
            >
                H+
            </button>
            <button 
                class="control-btn rotation-btn"
                @click.stop="updateGridPosition({ rotation: ((block.rotation || 0) + 90) % 360 })"
                title="Rotate 90 degrees"
            >
                ↻
            </button>
        </div>

        <!-- Grid Position Display -->
        <div v-if="!isPreview && block.isSelected" class="position-info">
            <small>
                Col: {{ block.grid_column }}-{{ block.grid_column + block.grid_column_span - 1 }} | 
                Row: {{ block.grid_row }}-{{ block.grid_row + block.grid_row_span - 1 }} |
                Size: {{ blockDimensions.width }} × {{ blockDimensions.height }}
                <span v-if="block.rotation"> | Rot: {{ block.rotation }}°</span>
            </small>
        </div>
    </div>
</template>

<style scoped>
.grid-block {
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.grid-block:hover {
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

.seat-row.vertical {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-right: 0.5rem;
    margin-bottom: 0;
    flex: 0 0 auto;
}

.row-label {
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 0.25rem;
    font-weight: 500;
    text-align: center;
    white-space: nowrap;
}

.seats {
    display: flex;
    flex-wrap: nowrap;
    gap: 2px;
}

.seats.vertical {
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.seats-container.rotated {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    justify-content: center;
    flex-wrap: nowrap;
    gap: 8px;
}

.seat {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    border: 1px solid #ddd;
    background: #f5f5f5;
    color: #666;
}

.seat-available {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #2e7d32;
}

.seat-booked {
    background: #fff3e0;
    border-color: #ff9800;
    color: #e65100;
}

.seat-unavailable {
    background: #ffebee;
    border-color: #f44336;
    color: #c62828;
}

.block-controls {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e0e0e0;
}

.control-btn {
    padding: 0.25rem 0.5rem;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s;
}

.control-btn:hover:not(:disabled) {
    background: #f0f0f0;
    border-color: #007bff;
}

.control-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.rotation-btn {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #1976d2;
    font-size: 0.9rem;
    font-weight: bold;
}

.rotation-btn:hover {
    background: #bbdefb;
    border-color: #1976d2;
}

.position-info {
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.65rem;
    text-align: center;
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
</style>
