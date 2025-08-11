<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    selectedElement: Object,
    showGrid: Boolean,
    layoutConfig: Object
})

const emit = defineEmits([
    'add-block', 'add-stage', 'rotate-element', 'bring-to-front',
    'send-to-back', 'remove-element', 'toggle-grid', 'update-canvas-size'
])

// Canvas size inputs
const canvasWidth = ref(props.layoutConfig.canvas_width)
const canvasHeight = ref(props.layoutConfig.canvas_height)

// Watch for canvas size changes
watch([canvasWidth, canvasHeight], ([width, height]) => {
    emit('update-canvas-size', width, height)
})

// Predefined canvas sizes
const presetSizes = [
    { name: 'Small', width: 800, height: 600 },
    { name: 'Medium', width: 1200, height: 800 },
    { name: 'Large', width: 1600, height: 1000 },
    { name: 'Extra Large', width: 2000, height: 1200 }
]

function applyPresetSize(preset) {
    canvasWidth.value = preset.width
    canvasHeight.value = preset.height
}
</script>

<template>
    <div class="editor-toolbar">
        <!-- Creation Tools -->
        <div class="toolbar-section">
            <h4 class="section-title">Add Elements</h4>
            <div class="tool-group">
                <button @click="emit('add-block')" class="tool-btn primary">
                    <span class="icon">🏛️</span>
                    Add Block
                </button>
                <button @click="emit('add-stage')" class="tool-btn secondary">
                    <span class="icon">🎭</span>
                    Add Stage
                </button>
            </div>
        </div>

        <!-- Element Controls -->
        <div class="toolbar-section" v-if="selectedElement">
            <h4 class="section-title">Element Controls</h4>
            <div class="tool-group">
                <button
                    @click="emit('rotate-element', selectedElement)"
                    class="tool-btn"
                    title="Rotate 90°"
                >
                    <span class="icon">↻</span>
                    Rotate
                </button>
                <button
                    @click="emit('bring-to-front', selectedElement)"
                    class="tool-btn"
                    title="Bring to Front"
                >
                    <span class="icon">⬆️</span>
                    Front
                </button>
                <button
                    @click="emit('send-to-back', selectedElement)"
                    class="tool-btn"
                    title="Send to Back"
                >
                    <span class="icon">⬇️</span>
                    Back
                </button>
                <button
                    @click="emit('remove-element', selectedElement)"
                    class="tool-btn danger"
                    title="Delete Element"
                >
                    <span class="icon">🗑️</span>
                    Delete
                </button>
            </div>
        </div>

        <!-- View Controls -->
        <div class="toolbar-section">
            <h4 class="section-title">View Options</h4>
            <div class="tool-group">
                <button
                    @click="emit('toggle-grid')"
                    :class="['tool-btn', { active: showGrid }]"
                    title="Toggle Grid"
                >
                    <span class="icon">⊞</span>
                    Grid
                </button>
            </div>
        </div>

        <!-- Canvas Settings -->
        <div class="toolbar-section">
            <h4 class="section-title">Canvas Size</h4>
            <div class="canvas-size-controls">
                <!-- Preset Sizes -->
                <div class="preset-sizes">
                    <button
                        v-for="preset in presetSizes"
                        :key="preset.name"
                        @click="applyPresetSize(preset)"
                        class="preset-btn"
                        :class="{
                            active: canvasWidth === preset.width && canvasHeight === preset.height
                        }"
                    >
                        {{ preset.name }}
                    </button>
                </div>

                <!-- Custom Size Inputs -->
                <div class="size-inputs">
                    <div class="input-group">
                        <label>Width</label>
                        <input
                            v-model.number="canvasWidth"
                            type="number"
                            min="400"
                            max="3000"
                            step="50"
                        />
                    </div>
                    <div class="input-group">
                        <label>Height</label>
                        <input
                            v-model.number="canvasHeight"
                            type="number"
                            min="300"
                            max="2000"
                            step="50"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Info -->
        <div class="toolbar-section" v-if="selectedElement">
            <h4 class="section-title">Selection Info</h4>
            <div class="info-display">
                <div class="info-item">
                    <strong>{{ selectedElement.name || 'Stage' }}</strong>
                </div>
                <div class="info-item">
                    Position: {{ Math.round(selectedElement.position_x) }}, {{ Math.round(selectedElement.position_y) }}
                </div>
                <div class="info-item">
                    Rotation: {{ selectedElement.rotation }}°
                </div>
                <div class="info-item" v-if="selectedElement.rows">
                    Seats: {{ selectedElement.rows.reduce((sum, row) => sum + (row.seat_count || row.seats?.length || 0), 0) }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.editor-toolbar {
    width: 280px;
    background: white;
    border-right: 1px solid #dee2e6;
    padding: 1rem;
    overflow-y: auto;
    box-shadow: 2px 0 4px rgba(0,0,0,0.1);
}

.toolbar-section {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f3f4;
}

.toolbar-section:last-child {
    border-bottom: none;
}

.section-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: #495057;
    margin: 0 0 1rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tool-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tool-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
    font-weight: 500;
}

.tool-btn:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.tool-btn.primary {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.tool-btn.primary:hover {
    background: #0056b3;
}

.tool-btn.secondary {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}

.tool-btn.secondary:hover {
    background: #545b62;
}

.tool-btn.danger {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.tool-btn.danger:hover {
    background: #c82333;
}

.tool-btn.active {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.icon {
    font-size: 1rem;
}

.canvas-size-controls {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.preset-sizes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.preset-btn {
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.8rem;
}

.preset-btn:hover {
    border-color: #007bff;
}

.preset-btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.size-inputs {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.input-group label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #6c757d;
}

.input-group input {
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 0.9rem;
}

.input-group input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.info-display {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-item {
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
    font-size: 0.8rem;
    color: #495057;
}
</style>
