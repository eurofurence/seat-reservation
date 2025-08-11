<script setup>
import { computed } from 'vue'

const props = defineProps({
    block: Object,
    isPreview: Boolean,
    gridSize: Number
})

const emit = defineEmits(['mousedown'])

// Helper function to convert number to letter (1=A, 2=B, etc.)
const numberToLetter = (num) => String.fromCharCode(64 + num)

// Calculate block dimensions based on rows and seats
const blockDimensions = computed(() => {
    const baseWidth = 120
    const baseHeight = 80
    const seatWidth = 8
    const rowHeight = 20

    if (!props.block.rows || props.block.rows.length === 0) {
        return { width: baseWidth, height: baseHeight }
    }

    const maxSeatsInRow = Math.max(...props.block.rows.map(row => row.seat_count || row.seats?.length || 0))
    const rowCount = props.block.rows.length

    return {
        width: Math.max(baseWidth, maxSeatsInRow * seatWidth + 40),
        height: Math.max(baseHeight, rowCount * rowHeight + 40)
    }
})

// Block style with position, rotation, and dimensions
const blockStyle = computed(() => {
    const { width, height } = blockDimensions.value

    return {
        position: 'absolute',
        left: `${props.block.position_x}px`,
        top: `${props.block.position_y}px`,
        width: `${width}px`,
        height: `${height}px`,
        transform: `rotate(${props.block.rotation}deg)`,
        transformOrigin: 'center center',
        zIndex: props.block.z_index || 1,
        cursor: props.isPreview ? 'default' : 'grab',
        transition: props.block.isDragging ? 'none' : 'all 0.2s ease'
    }
})
</script>

<template>
    <div
        class="draggable-block"
        :class="{
            'is-selected': block.isSelected,
            'is-dragging': block.isDragging,
            'is-preview': isPreview,
            'is-new': block.isNew
        }"
        :style="blockStyle"
        @mousedown="emit('mousedown', $event)"
    >
        <!-- Block Header -->
        <div class="block-header drag-handle">
            <span class="block-title">{{ block.name }}</span>
            <span class="block-info">
                {{ block.rows?.reduce((sum, row) => sum + (row.seat_count || row.seats?.length || 0), 0) }} seats
            </span>
        </div>

        <!-- Block Content - Rows and Seats -->
        <div class="block-content">
            <div
                v-for="(row, rowIndex) in block.rows"
                :key="row.id"
                class="seat-row"
                :style="{ marginBottom: rowIndex < block.rows.length - 1 ? '8px' : '0' }"
            >
                <!-- Row Label -->
                <div class="row-label">{{ row.name }}</div>

                <!-- Seats in Flexbox Layout -->
                <div class="seats-container">
                    <div
                        v-for="seat in (row.seats || Array.from({length: row.seat_count}, (_, i) => ({
                            id: `seat-${i + 1}`,
                            name: numberToLetter(i + 1),
                            is_available: true
                        })))"
                        :key="seat.id"
                        class="seat"
                        :class="{
                            'seat-available': seat.is_available,
                            'seat-unavailable': !seat.is_available
                        }"
                        :title="`Seat ${seat.name}`"
                    >
                        {{ seat.name }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Selection Handles -->
        <div v-if="block.isSelected && !isPreview" class="selection-handles">
            <div class="handle handle-nw"></div>
            <div class="handle handle-ne"></div>
            <div class="handle handle-sw"></div>
            <div class="handle handle-se"></div>
            <div class="rotation-handle" title="Drag to rotate">⟲</div>
        </div>

        <!-- New Block Indicator -->
        <div v-if="block.isNew" class="new-block-badge">NEW</div>
    </div>
</template>

<style scoped>
.draggable-block {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    border: 2px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    user-select: none;
}

.draggable-block:hover:not(.is-preview) {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.2);
}

.draggable-block.is-selected {
    border-color: #28a745;
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
}

.draggable-block.is-dragging {
    opacity: 0.8;
    transform-style: preserve-3d;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.draggable-block.is-new {
    border-color: #fd7e14;
    border-style: dashed;
}

.block-header {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: grab;
}

.block-header:active {
    cursor: grabbing;
}

.block-title {
    font-weight: 600;
    font-size: 0.9rem;
}

.block-info {
    font-size: 0.75rem;
    opacity: 0.9;
}

.block-content {
    padding: 12px;
    background: white;
}

.seat-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.row-label {
    font-size: 0.7rem;
    font-weight: 500;
    color: #6c757d;
    margin-bottom: 2px;
}

.seats-container {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    justify-content: flex-start;
}

.seat {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 500;
    border: 1px solid;
    transition: all 0.2s;
}

.seat-available {
    background: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.seat-unavailable {
    background: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

.seat:hover {
    transform: scale(1.1);
}

.selection-handles {
    position: absolute;
    top: -5px;
    left: -5px;
    right: -5px;
    bottom: -5px;
    pointer-events: none;
}

.handle {
    position: absolute;
    width: 8px;
    height: 8px;
    background: #28a745;
    border: 2px solid white;
    border-radius: 50%;
    pointer-events: auto;
    cursor: pointer;
}

.handle-nw { top: 0; left: 0; cursor: nw-resize; }
.handle-ne { top: 0; right: 0; cursor: ne-resize; }
.handle-sw { bottom: 0; left: 0; cursor: sw-resize; }
.handle-se { bottom: 0; right: 0; cursor: se-resize; }

.rotation-handle {
    position: absolute;
    top: -20px;
    left: 50%;
    transform: translateX(-50%);
    background: #007bff;
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    cursor: grab;
    pointer-events: auto;
}

.rotation-handle:hover {
    background: #0056b3;
}

.new-block-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #fd7e14;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.6rem;
    font-weight: 600;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Preview mode styles */
.is-preview {
    cursor: default !important;
}

.is-preview .block-header {
    cursor: default !important;
}

.is-preview:hover {
    border-color: #dee2e6 !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
}
</style>
