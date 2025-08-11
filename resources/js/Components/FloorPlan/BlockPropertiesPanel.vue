<script setup>
import { ref, watch, computed } from 'vue'

const props = defineProps({
    element: Object
})

const emit = defineEmits(['update-element', 'close'])

// Local copies for editing
const elementName = ref(props.element.name || 'Stage')
const elementX = ref(Math.round(props.element.position_x))
const elementY = ref(Math.round(props.element.position_y))
const elementRotation = ref(props.element.rotation)

// Stage-specific properties
const stageWidth = ref(props.element.width || 200)
const stageHeight = ref(props.element.height || 80)

// Block-specific properties - create reactive copy of rows
const blockRows = ref(props.element.rows ? [...props.element.rows] : [])

// Watch for changes and emit updates
watch([elementName, elementX, elementY, elementRotation], () => {
    const updates = {
        name: elementName.value,
        position_x: elementX.value,
        position_y: elementY.value,
        rotation: elementRotation.value
    }

    if (props.element.width !== undefined) {
        updates.width = stageWidth.value
        updates.height = stageHeight.value
    }

    if (blockRows.value.length > 0) {
        updates.rows = blockRows.value
    }

    emit('update-element', updates)
})

watch([stageWidth, stageHeight], () => {
    if (props.element.width !== undefined) {
        emit('update-element', {
            width: stageWidth.value,
            height: stageHeight.value
        })
    }
})

watch(blockRows, () => {
    if (blockRows.value.length > 0) {
        emit('update-element', { rows: blockRows.value })
    }
}, { deep: true })

// Row management functions
function addRow() {
    const newRow = {
        id: 'temp-row-' + Date.now(),
        name: `Row ${blockRows.value.length + 1}`,
        seat_count: 10,
        seats: Array.from({length: 10}, (_, i) => ({
            id: `temp-seat-${i + 1}`,
            name: String.fromCharCode(65 + i), // A, B, C, etc.
            is_available: true
        }))
    }
    blockRows.value.push(newRow)
}

function removeRow(index) {
    if (blockRows.value.length > 1) {
        blockRows.value.splice(index, 1)
        // Renumber rows
        blockRows.value.forEach((row, i) => {
            row.name = `Row ${i + 1}`
        })
    }
}

function updateRowSeatCount(row, newCount) {
    row.seat_count = newCount
    // Update seats array to match
    row.seats = Array.from({length: newCount}, (_, i) => ({
        id: `seat-${i + 1}`,
        name: String.fromCharCode(65 + i), // A, B, C, etc.
        is_available: true
    }))
}

// Predefined rotation angles
const rotationOptions = [0, 90, 180, 270]

// Calculate total seats
const totalSeats = computed(() => {
    return blockRows.value.reduce((sum, row) => sum + (row.seat_count || 0), 0)
})

// Check if element is a stage
const isStage = computed(() => {
    return props.element.id === 'stage' || props.element.width !== undefined
})
</script>

<template>
    <div class="properties-panel">
        <!-- Panel Header -->
        <div class="panel-header">
            <h3 class="panel-title">
                {{ isStage ? 'Stage Properties' : 'Block Properties' }}
            </h3>
            <button @click="emit('close')" class="close-btn" title="Close">×</button>
        </div>

        <!-- General Properties -->
        <div class="property-section">
            <h4 class="section-title">General</h4>

            <div class="form-group">
                <label>Name</label>
                <input
                    v-model="elementName"
                    type="text"
                    placeholder="Enter name"
                    :disabled="isStage"
                />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>X Position</label>
                    <input v-model.number="elementX" type="number" min="0" step="1" />
                </div>
                <div class="form-group">
                    <label>Y Position</label>
                    <input v-model.number="elementY" type="number" min="0" step="1" />
                </div>
            </div>

            <div class="form-group">
                <label>Rotation</label>
                <select v-model.number="elementRotation">
                    <option v-for="angle in rotationOptions" :key="angle" :value="angle">
                        {{ angle }}°
                    </option>
                </select>
            </div>
        </div>

        <!-- Stage-specific Properties -->
        <div v-if="isStage" class="property-section">
            <h4 class="section-title">Stage Dimensions</h4>

            <div class="form-row">
                <div class="form-group">
                    <label>Width (px)</label>
                    <input v-model.number="stageWidth" type="number" min="50" max="800" step="10" />
                </div>
                <div class="form-group">
                    <label>Height (px)</label>
                    <input v-model.number="stageHeight" type="number" min="30" max="300" step="10" />
                </div>
            </div>
        </div>

        <!-- Block-specific Properties -->
        <div v-else class="property-section">
            <h4 class="section-title">
                Seating Configuration
                <span class="seat-count">({{ totalSeats }} seats)</span>
            </h4>

            <!-- Rows List -->
            <div class="rows-container">
                <div
                    v-for="(row, index) in blockRows"
                    :key="row.id"
                    class="row-item"
                >
                    <div class="row-header">
                        <span class="row-name">{{ row.name }}</span>
                        <button
                            @click="removeRow(index)"
                            class="remove-row-btn"
                            :disabled="blockRows.length <= 1"
                            title="Remove Row"
                        >
                            ×
                        </button>
                    </div>

                    <div class="row-controls">
                        <div class="form-group">
                            <label>Row Name</label>
                            <input v-model="row.name" type="text" />
                        </div>

                        <div class="form-group">
                            <label>Seats</label>
                            <input
                                :value="row.seat_count"
                                @input="updateRowSeatCount(row, parseInt($event.target.value))"
                                type="number"
                                min="1"
                                max="50"
                            />
                        </div>
                    </div>

                    <!-- Visual Seat Preview -->
                    <div class="seat-preview">
                        <div
                            v-for="seat in row.seats"
                            :key="seat.id"
                            class="preview-seat"
                            :title="`Seat ${seat.name}`"
                        >
                            {{ seat.name }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Row Button -->
            <button @click="addRow" class="add-row-btn">
                + Add Row
            </button>
        </div>

        <!-- Quick Actions -->
        <div class="property-section">
            <h4 class="section-title">Quick Actions</h4>
            <div class="action-buttons">
                <button @click="emit('update-element', { rotation: (elementRotation + 90) % 360 })" class="action-btn">
                    🔄 Rotate 90°
                </button>
                <button @click="emit('update-element', { position_x: 0, position_y: 0 })" class="action-btn">
                    📍 Move to Origin
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.properties-panel {
    position: fixed;
    top: 120px;
    right: 20px;
    width: 350px;
    max-height: calc(100vh - 140px);
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    overflow: hidden;
    z-index: 1000;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.panel-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #212529;
    margin: 0;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.close-btn:hover {
    background: #e9ecef;
    color: #495057;
}

.property-section {
    padding: 1rem;
    border-bottom: 1px solid #f1f3f4;
}

.property-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.seat-count {
    font-size: 0.8rem;
    font-weight: 400;
    color: #6c757d;
}

.form-group {
    margin-bottom: 1rem;
}

.form-row {
    display: flex;
    gap: 1rem;
}

.form-row .form-group {
    flex: 1;
}

.form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: #495057;
    margin-bottom: 0.25rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 0.9rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-group input:disabled {
    background: #f8f9fa;
    color: #6c757d;
}

.rows-container {
    max-height: 300px;
    overflow-y: auto;
    margin-bottom: 1rem;
}

.row-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 0.75rem;
}

.row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.row-name {
    font-weight: 600;
    color: #495057;
}

.remove-row-btn {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
}

.remove-row-btn:disabled {
    background: #dee2e6;
    cursor: not-allowed;
}

.remove-row-btn:hover:not(:disabled) {
    background: #c82333;
}

.row-controls {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.seat-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
}

.preview-seat {
    width: 18px;
    height: 18px;
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 500;
    color: #155724;
}

.add-row-btn {
    width: 100%;
    padding: 0.75rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}

.add-row-btn:hover {
    background: #0056b3;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.action-btn {
    padding: 0.5rem;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}
</style>
