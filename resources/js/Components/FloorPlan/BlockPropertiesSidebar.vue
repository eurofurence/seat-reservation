<script setup>
import { ref, computed, watch } from 'vue'
import { X, Armchair, Move, Maximize, Trash2, Copy, Settings, Plus, Minus, Users } from 'lucide-vue-next'

const props = defineProps({
    element: Object,
    gridConfig: Object
})

const emit = defineEmits(['update-element', 'remove-element', 'close'])

// Local element data for editing
const localElement = ref({
    name: props.element?.name || 'Unnamed Block',
    grid_column: props.element?.grid_column || 1,
    grid_row: props.element?.grid_row || 1,
    grid_column_span: props.element?.grid_column_span || 1,
    grid_row_span: props.element?.grid_row_span || 1,
    rows: props.element?.rows ? [...props.element.rows] : []
})

// Row management
const uniformSeatCount = ref(true)
const defaultSeatCount = ref(10)

// Watch for prop changes
watch(() => props.element, (newElement) => {
    if (newElement) {
        localElement.value = {
            name: newElement.name || 'Unnamed Block',
            grid_column: newElement.grid_column || 1,
            grid_row: newElement.grid_row || 1,
            grid_column_span: newElement.grid_column_span || 1,
            grid_row_span: newElement.grid_row_span || 1,
            rows: newElement.rows ? [...newElement.rows] : []
        }
        
        // If no rows exist, create a default row
        if (localElement.value.rows.length === 0) {
            localElement.value.rows = [{
                id: `temp-row-${Date.now()}`,
                name: 'Row 1',
                seat_count: parseInt(defaultSeatCount.value),
                seats: []
            }]
            // Emit the change immediately so the parent component updates
            emit('update-element', { rows: localElement.value.rows })
        }
        
        // Check if all rows have the same seat count
        if (localElement.value.rows.length > 0) {
            const firstSeatCount = localElement.value.rows[0].seat_count || localElement.value.rows[0].seats?.length || 0
            uniformSeatCount.value = localElement.value.rows.every(row => 
                (row.seat_count || row.seats?.length || 0) === firstSeatCount
            )
            defaultSeatCount.value = firstSeatCount
        }
    }
}, { immediate: true })

// Computed properties
const elementType = computed(() => {
    return props.element?.id === 'stage' ? 'Stage' : 'Block'
})

const dimensionPercentages = computed(() => {
    if (!props.gridConfig) return { width: '0.0', height: '0.0' }
    const widthPercent = (localElement.value.grid_column_span / props.gridConfig.grid_columns) * 100
    const heightPercent = (localElement.value.grid_row_span / props.gridConfig.grid_rows) * 100
    return {
        width: widthPercent.toFixed(1),
        height: heightPercent.toFixed(1)
    }
})

const maxColumn = computed(() => {
    if (!props.gridConfig) return 1
    return props.gridConfig.grid_columns - localElement.value.grid_column_span + 1
})

const maxRow = computed(() => {
    if (!props.gridConfig) return 1
    return props.gridConfig.grid_rows - localElement.value.grid_row_span + 1
})

const maxColumnSpan = computed(() => {
    if (!props.gridConfig) return 1
    return props.gridConfig.grid_columns - localElement.value.grid_column + 1
})

const maxRowSpan = computed(() => {
    if (!props.gridConfig) return 1
    return props.gridConfig.grid_rows - localElement.value.grid_row + 1
})

// Update functions
function updateElement() {
    emit('update-element', localElement.value)
}

function updateName() {
    updateElement()
}

function updateGridPosition(field, value) {
    localElement.value[field] = Math.max(1, parseInt(value))
    updateElement()
}

function updateGridSpan(field, value) {
    const newValue = Math.max(1, parseInt(value))
    localElement.value[field] = newValue
    
    // Ensure position + span doesn't exceed grid bounds
    if (props.gridConfig) {
        if (field === 'grid_column_span') {
            const maxCol = props.gridConfig.grid_columns - newValue + 1
            if (localElement.value.grid_column > maxCol) {
                localElement.value.grid_column = maxCol
            }
        } else if (field === 'grid_row_span') {
            const maxRow = props.gridConfig.grid_rows - newValue + 1
            if (localElement.value.grid_row > maxRow) {
                localElement.value.grid_row = maxRow
            }
        }
    }
    
    updateElement()
}

function moveElement(direction) {
    switch (direction) {
        case 'left':
            if (localElement.value.grid_column > 1) {
                localElement.value.grid_column--
                updateElement()
            }
            break
        case 'right':
            if (localElement.value.grid_column < maxColumn.value) {
                localElement.value.grid_column++
                updateElement()
            }
            break
        case 'up':
            if (localElement.value.grid_row > 1) {
                localElement.value.grid_row--
                updateElement()
            }
            break
        case 'down':
            if (localElement.value.grid_row < maxRow.value) {
                localElement.value.grid_row++
                updateElement()
            }
            break
    }
}

function resizeElement(direction) {
    switch (direction) {
        case 'wider':
            if (localElement.value.grid_column_span < maxColumnSpan.value) {
                localElement.value.grid_column_span++
                updateElement()
            }
            break
        case 'narrower':
            if (localElement.value.grid_column_span > 1) {
                localElement.value.grid_column_span--
                updateElement()
            }
            break
        case 'taller':
            if (localElement.value.grid_row_span < maxRowSpan.value) {
                localElement.value.grid_row_span++
                updateElement()
            }
            break
        case 'shorter':
            if (localElement.value.grid_row_span > 1) {
                localElement.value.grid_row_span--
                updateElement()
            }
            break
    }
}

function duplicateElement() {
    // This would need to be implemented in the parent component
    console.log('Duplicate element:', props.element)
}

function removeElement() {
    emit('remove-element', props.element)
}

// Row management functions
function addRow() {
    const newRow = {
        id: `temp-row-${Date.now()}`,
        name: `Row ${localElement.value.rows.length + 1}`,
        seat_count: parseInt(uniformSeatCount.value ? defaultSeatCount.value : 10),
        seats: []
    }
    localElement.value.rows.push(newRow)
    emit('update-element', { rows: localElement.value.rows })
}

function removeRow(index) {
    if (localElement.value.rows.length > 1) {
        localElement.value.rows.splice(index, 1)
        // Renumber the remaining rows
        localElement.value.rows.forEach((row, idx) => {
            row.name = `Row ${idx + 1}`
        })
        emit('update-element', { rows: localElement.value.rows })
    }
}

function updateRowSeatCount(rowIndex, seatCount) {
    if (localElement.value.rows[rowIndex]) {
        const newSeatCount = Math.max(1, parseInt(seatCount) || 1)
        localElement.value.rows[rowIndex].seat_count = newSeatCount
        emit('update-element', { rows: localElement.value.rows })
    }
}

function updateAllRowSeats() {
    if (uniformSeatCount.value) {
        const seatCount = parseInt(defaultSeatCount.value) || 10
        localElement.value.rows.forEach(row => {
            row.seat_count = seatCount
        })
        emit('update-element', { rows: localElement.value.rows })
    }
}

function toggleUniformSeats() {
    // The checkbox state is already toggled by v-model
    if (uniformSeatCount.value) {
        // Apply default seat count to all rows when enabling uniform seats
        const seatCount = parseInt(defaultSeatCount.value) || 10
        localElement.value.rows.forEach(row => {
            row.seat_count = seatCount
        })
        emit('update-element', { rows: localElement.value.rows })
    }
    // When disabling uniform seats, keep current values as they are
}
</script>

<template>
    <div class="properties-sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="header-info">
                <Armchair v-if="props.element && elementType === 'Block'" :size="20" />
                <Settings v-else-if="props.element" :size="20" />
                <Settings v-else :size="20" />
                <h3>{{ props.element ? `${elementType} Properties` : 'Properties' }}</h3>
            </div>
            <button v-if="props.element" @click="$emit('close')" class="close-btn">
                <X :size="16" />
            </button>
        </div>

        <!-- Content when element is selected -->
        <div v-if="props.element">
            <!-- Element Information -->
            <div class="sidebar-section">
                <h4 class="section-title">Basic Information</h4>
                
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input 
                        v-model="localElement.name"
                        @blur="updateName"
                        @keyup.enter="updateName"
                        type="text" 
                        class="form-input"
                        placeholder="Enter name..."
                    />
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Type</span>
                        <span class="info-value">{{ elementType }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Size</span>
                        <span class="info-value">{{ dimensionPercentages.width }}% × {{ dimensionPercentages.height }}%</span>
                    </div>
                </div>
            </div>

            <!-- Position Controls -->
            <div class="sidebar-section">
                <h4 class="section-title">
                    <Move :size="16" />
                    Position
                </h4>
                
                <div class="control-grid">
                    <div class="control-group">
                        <label class="control-label">Column</label>
                        <div class="control-with-buttons">
                            <button 
                                @click="moveElement('left')"
                                :disabled="localElement.grid_column <= 1"
                                class="control-btn"
                            >
                                ←
                            </button>
                            <input 
                                :value="localElement.grid_column"
                                @input="updateGridPosition('grid_column', $event.target.value)"
                                type="number" 
                                :min="1" 
                                :max="maxColumn"
                                class="control-input"
                            />
                            <button 
                                @click="moveElement('right')"
                                :disabled="localElement.grid_column >= maxColumn"
                                class="control-btn"
                            >
                                →
                            </button>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <label class="control-label">Row</label>
                        <div class="control-with-buttons">
                            <button 
                                @click="moveElement('up')"
                                :disabled="localElement.grid_row <= 1"
                                class="control-btn"
                            >
                                ↑
                            </button>
                            <input 
                                :value="localElement.grid_row"
                                @input="updateGridPosition('grid_row', $event.target.value)"
                                type="number" 
                                :min="1" 
                                :max="maxRow"
                                class="control-input"
                            />
                            <button 
                                @click="moveElement('down')"
                                :disabled="localElement.grid_row >= maxRow"
                                class="control-btn"
                            >
                                ↓
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Size Controls -->
            <div class="sidebar-section">
                <h4 class="section-title">
                    <Maximize :size="16" />
                    Size
                </h4>
                
                <div class="control-grid">
                    <div class="control-group">
                        <label class="control-label">Width (Columns)</label>
                        <div class="control-with-buttons">
                            <button 
                                @click="resizeElement('narrower')"
                                :disabled="localElement.grid_column_span <= 1"
                                class="control-btn"
                            >
                                -
                            </button>
                            <input 
                                :value="localElement.grid_column_span"
                                @input="updateGridSpan('grid_column_span', $event.target.value)"
                                type="number" 
                                :min="1" 
                                :max="maxColumnSpan"
                                class="control-input"
                            />
                            <button 
                                @click="resizeElement('wider')"
                                :disabled="localElement.grid_column_span >= maxColumnSpan"
                                class="control-btn"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <label class="control-label">Height (Rows)</label>
                        <div class="control-with-buttons">
                            <button 
                                @click="resizeElement('shorter')"
                                :disabled="localElement.grid_row_span <= 1"
                                class="control-btn"
                            >
                                -
                            </button>
                            <input 
                                :value="localElement.grid_row_span"
                                @input="updateGridSpan('grid_row_span', $event.target.value)"
                                type="number" 
                                :min="1" 
                                :max="maxRowSpan"
                                class="control-input"
                            />
                            <button 
                                @click="resizeElement('taller')"
                                :disabled="localElement.grid_row_span >= maxRowSpan"
                                class="control-btn"
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div class="size-info">
                    <small>
                        Occupies: {{ localElement.grid_column }} to {{ localElement.grid_column + localElement.grid_column_span - 1 }} (columns),
                        {{ localElement.grid_row }} to {{ localElement.grid_row + localElement.grid_row_span - 1 }} (rows)
                    </small>
                </div>
            </div>

            <!-- Block-specific properties -->
            <div v-if="elementType === 'Block'" class="sidebar-section">
                <h4 class="section-title">
                    <Users :size="16" />
                    Row Management
                </h4>
                
                <!-- Summary Info -->
                <div class="info-grid mb-4">
                    <div v-if="props.element.rows" class="info-item">
                        <span class="info-label">Rows</span>
                        <span class="info-value">{{ props.element.rows.length }}</span>
                    </div>
                    <div v-if="props.element.rows" class="info-item">
                        <span class="info-label">Total Seats</span>
                        <span class="info-value">{{ props.element.rows.reduce((sum, row) => sum + (row.seats?.length || row.seat_count || 0), 0) }}</span>
                    </div>
                </div>

                <!-- Uniform Seat Count Toggle -->
                <div class="control-group mb-4">
                    <label class="checkbox-label">
                        <input 
                            type="checkbox" 
                            v-model="uniformSeatCount"
                            @change="toggleUniformSeats"
                            class="checkbox-input"
                        />
                        <span class="checkbox-text">Same seat count for all rows</span>
                    </label>
                    
                    <div v-if="uniformSeatCount" class="mt-2">
                        <label class="control-label">Seats per row</label>
                        <input 
                            type="number" 
                            v-model.number="defaultSeatCount"
                            @input="updateAllRowSeats"
                            :min="1" 
                            :max="50"
                            class="control-input"
                        />
                    </div>
                </div>

                <!-- Add Row Button -->
                <div class="control-group mb-4">
                    <button @click="addRow" class="action-btn action-btn-primary w-full">
                        <Plus :size="16" />
                        <span>Add Row</span>
                    </button>
                </div>

                <!-- Individual Row Configuration -->
                <div v-if="props.element.rows && props.element.rows.length > 0" class="rows-list">
                    <h5 class="subsection-title">Row Configuration</h5>
                    
                    <div v-for="(row, index) in props.element.rows" :key="index" class="row-item">
                        <div class="row-header">
                            <span class="row-label">Row {{ index + 1 }}</span>
                            <button 
                                @click="removeRow(index)"
                                :disabled="props.element.rows.length <= 1"
                                class="remove-row-btn"
                                title="Remove row"
                            >
                                <X :size="14" />
                            </button>
                        </div>
                        
                        <div class="row-controls">
                            <label class="control-label">Seats</label>
                            <input 
                                type="number" 
                                :value="row.seat_count || (row.seats ? row.seats.length : 0)"
                                @input="updateRowSeatCount(index, $event.target.value)"
                                :disabled="uniformSeatCount"
                                :min="1" 
                                :max="50"
                                class="control-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Empty State for rows -->
                <div v-else class="empty-state">
                    <p class="empty-text">No rows configured</p>
                    <p class="empty-subtext">Add a row to start configuring seats</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="sidebar-section">
                <h4 class="section-title">Actions</h4>
                
                <div class="action-buttons">
                    <button @click="duplicateElement" class="action-btn action-btn-secondary">
                        <Copy :size="16" />
                        <span>Duplicate</span>
                    </button>
                    
                    <button @click="removeElement" class="action-btn action-btn-danger">
                        <Trash2 :size="16" />
                        <span>Remove</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state when no element is selected -->
        <div v-else class="sidebar-section">
            <div class="empty-state">
                <Settings :size="48" class="empty-icon" />
                <h3 class="empty-title">No Element Selected</h3>
                <p class="empty-text">Select a block or stage element to view and edit its properties</p>
                <div class="empty-tips">
                    <h4 class="tips-title">Tips:</h4>
                    <ul class="tips-list">
                        <li>Click on any block to select it</li>
                        <li>Use the left sidebar to add new blocks</li>
                        <li>Click and drag elements to reposition them</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.properties-sidebar {
    width: 320px;
    background: white;
    border-left: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1rem 1rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.header-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.header-info h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #212529;
}

.close-btn {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: all 0.2s;
}

.close-btn:hover {
    background: #e9ecef;
    color: #495057;
}

.sidebar-section {
    padding: 1rem;
    border-bottom: 1px solid #f0f0f0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.75rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
}

.form-group {
    margin-bottom: 0.75rem;
}

.form-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.875rem;
    transition: border-color 0.2s;
}

.form-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.info-label {
    font-size: 0.7rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 0.875rem;
    color: #212529;
    font-weight: 600;
}

.control-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.control-label {
    font-size: 0.75rem;
    color: #6c757d;
    font-weight: 500;
}

.control-with-buttons {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.control-btn {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.control-btn:hover:not(:disabled) {
    background: #e9ecef;
    border-color: #007bff;
}

.control-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.control-input {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    text-align: center;
    font-size: 0.875rem;
}

.size-info {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.size-info small {
    color: #6c757d;
    font-size: 0.7rem;
    line-height: 1.4;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn-secondary {
    background: #6c757d;
    color: white;
}

.action-btn-secondary:hover {
    background: #545b62;
}

.action-btn-danger {
    background: #dc3545;
    color: white;
}

.action-btn-danger:hover {
    background: #c82333;
}

.action-btn-primary {
    background: #007bff;
    color: white;
}

.action-btn-primary:hover {
    background: #0056b3;
}

.w-full {
    width: 100%;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mt-2 {
    margin-top: 0.5rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-input {
    margin: 0;
}

.checkbox-text {
    font-size: 0.875rem;
    color: #333;
    user-select: none;
}

.subsection-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.75rem;
}

.rows-list {
    max-height: 300px;
    overflow-y: auto;
}

.row-item {
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
}

.row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.row-label {
    font-weight: 500;
    color: #333;
    font-size: 0.875rem;
}

.remove-row-btn {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.remove-row-btn:hover:not(:disabled) {
    background: #c82333;
}

.remove-row-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.row-controls {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d;
}

.empty-text {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.empty-subtext {
    font-size: 0.875rem;
    opacity: 0.8;
}

.empty-icon {
    color: #6c757d;
    margin-bottom: 1rem;
}

.empty-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.empty-tips {
    margin-top: 2rem;
    text-align: left;
}

.tips-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.75rem;
}

.tips-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.tips-list li {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 0.5rem;
    padding-left: 1.25rem;
    position: relative;
}

.tips-list li:before {
    content: '•';
    color: #007bff;
    font-weight: bold;
    position: absolute;
    left: 0;
}
</style>
