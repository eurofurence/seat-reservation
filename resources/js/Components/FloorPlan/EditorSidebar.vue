<script setup>
import { ref, computed } from 'vue'
import { Plus, Grid, Settings, Theater, Armchair, LayoutGrid } from 'lucide-vue-next'

const props = defineProps({
    layoutConfig: Object
})

const emit = defineEmits(['add-block', 'add-stage', 'update-layout-config'])

const showGridSettings = ref(false)

// Local layout config for editing
const localConfig = ref({
    grid_columns: props.layoutConfig.grid_columns,
    grid_rows: props.layoutConfig.grid_rows,
    gap_size: props.layoutConfig.gap_size,
    show_grid_lines: props.layoutConfig.show_grid_lines
})

// Update parent when local config changes
function updateLayoutConfig() {
    emit('update-layout-config', localConfig.value)
}

// Grid size presets
const gridPresets = [
    { name: 'Small', columns: 8, rows: 6 },
    { name: 'Medium', columns: 12, rows: 8 },
    { name: 'Large', columns: 16, rows: 10 },
    { name: 'Extra Large', columns: 20, rows: 12 }
]

function applyGridPreset(preset) {
    localConfig.value.grid_columns = preset.columns
    localConfig.value.grid_rows = preset.rows
    updateLayoutConfig()
}

// Gap presets (in pixels, corresponding to Tailwind spacing)
const gapPresets = [
    { name: 'Tight', value: 12, label: 'gap-3' },
    { name: 'Normal', value: 24, label: 'gap-6' },
    { name: 'Relaxed', value: 48, label: 'gap-12' },
    { name: 'Loose', value: 96, label: 'gap-24' }
]

function applyGapPreset(preset) {
    localConfig.value.gap_size = preset.value
    updateLayoutConfig()
}
</script>

<template>
    <div class="editor-sidebar">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <LayoutGrid :size="20" />
            <h3>Floor Plan Tools</h3>
        </div>

        <!-- Add Elements Section -->
        <div class="sidebar-section">
            <h4 class="section-title">
                <Plus :size="16" />
                Add Elements
            </h4>
            
            <div class="tool-buttons">
                <button @click="$emit('add-block')" class="tool-btn tool-btn-primary">
                    <Armchair :size="18" />
                    <span>Add Block</span>
                </button>
                
                <button @click="$emit('add-stage')" class="tool-btn tool-btn-secondary">
                    <Theater :size="18" />
                    <span>Add Stage</span>
                </button>
            </div>
        </div>

        <!-- Grid Configuration Section -->
        <div class="sidebar-section">
            <h4 class="section-title" @click="showGridSettings = !showGridSettings">
                <Grid :size="16" />
                Grid Settings
                <button class="expand-btn" :class="{ expanded: showGridSettings }">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <path d="M6 9L1 4h10L6 9z"/>
                    </svg>
                </button>
            </h4>
            
            <div v-show="showGridSettings" class="grid-settings">
                <!-- Grid Size Controls -->
                <div class="setting-group">
                    <label class="setting-label">Grid Dimensions</label>
                    <div class="grid-dimension-controls">
                        <div class="dimension-control">
                            <label>Columns</label>
                            <input 
                                v-model.number="localConfig.grid_columns"
                                @change="updateLayoutConfig"
                                type="number" 
                                min="1" 
                                max="24" 
                                class="dimension-input"
                            />
                        </div>
                        <div class="dimension-control">
                            <label>Rows</label>
                            <input 
                                v-model.number="localConfig.grid_rows"
                                @change="updateLayoutConfig"
                                type="number" 
                                min="1" 
                                max="20" 
                                class="dimension-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Grid Presets -->
                <div class="setting-group">
                    <label class="setting-label">Quick Presets</label>
                    <div class="preset-buttons">
                        <button 
                            v-for="preset in gridPresets"
                            :key="preset.name"
                            @click="applyGridPreset(preset)"
                            class="preset-btn"
                            :class="{ 
                                active: localConfig.grid_columns === preset.columns && localConfig.grid_rows === preset.rows 
                            }"
                        >
                            {{ preset.name }}
                            <small>{{ preset.columns }}×{{ preset.rows }}</small>
                        </button>
                    </div>
                </div>

                <!-- Gap Settings -->
                <div class="setting-group">
                    <label class="setting-label">Gap Size</label>
                    <div class="gap-controls">
                        <input 
                            v-model.number="localConfig.gap_size"
                            @change="updateLayoutConfig"
                            type="range" 
                            min="0" 
                            max="120" 
                            step="4"
                            class="gap-slider"
                        />
                        <span class="gap-value">{{ localConfig.gap_size }}px</span>
                    </div>
                    
                    <div class="gap-presets">
                        <button 
                            v-for="preset in gapPresets"
                            :key="preset.name"
                            @click="applyGapPreset(preset)"
                            class="gap-preset-btn"
                            :class="{ active: localConfig.gap_size === preset.value }"
                        >
                            {{ preset.name }}
                        </button>
                    </div>
                </div>

                <!-- Grid Lines Toggle -->
                <div class="setting-group">
                    <label class="setting-checkbox">
                        <input 
                            v-model="localConfig.show_grid_lines"
                            @change="updateLayoutConfig"
                            type="checkbox"
                        />
                        <span class="checkbox-label">Show Grid Lines</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div class="sidebar-section">
            <h4 class="section-title">
                <Settings :size="16" />
                Instructions
            </h4>
            
            <div class="instructions">
                <div class="instruction-item">
                    <strong>Click</strong> to select blocks
                </div>
                <div class="instruction-item">
                    <strong>Delete/Backspace</strong> to remove selected items
                </div>
                <div class="instruction-item">
                    <strong>W+/W-</strong> to resize width
                </div>
                <div class="instruction-item">
                    <strong>H+/H-</strong> to resize height
                </div>
                <div class="instruction-item">
                    Use the <strong>properties panel</strong> on the right for detailed configuration
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.editor-sidebar {
    width: 280px;
    background: white;
    border-right: 1px solid #dee2e6;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.sidebar-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1.5rem 1rem 1rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #212529;
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
    cursor: pointer;
    user-select: none;
}

.expand-btn {
    margin-left: auto;
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    transition: transform 0.2s;
}

.expand-btn.expanded {
    transform: rotate(180deg);
}

.tool-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tool-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.tool-btn-primary {
    background: #007bff;
    color: white;
}

.tool-btn-primary:hover {
    background: #0056b3;
}

.tool-btn-secondary {
    background: #6c757d;
    color: white;
}

.tool-btn-secondary:hover {
    background: #545b62;
}

.grid-settings {
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.setting-group {
    margin-bottom: 1rem;
}

.setting-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.grid-dimension-controls {
    display: flex;
    gap: 0.5rem;
}

.dimension-control {
    flex: 1;
}

.dimension-control label {
    display: block;
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.dimension-input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 0.875rem;
}

.preset-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}

.preset-btn {
    padding: 0.5rem;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.preset-btn:hover {
    border-color: #007bff;
    background: #f8f9fa;
}

.preset-btn.active {
    border-color: #007bff;
    background: #e3f2fd;
    color: #007bff;
}

.preset-btn small {
    color: #6c757d;
    font-size: 0.65rem;
}

.gap-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.gap-slider {
    flex: 1;
}

.gap-value {
    font-size: 0.75rem;
    color: #495057;
    font-weight: 500;
    min-width: 50px;
    text-align: right;
}

.gap-presets {
    display: flex;
    gap: 0.25rem;
}

.gap-preset-btn {
    padding: 0.25rem 0.5rem;
    border: 1px solid #dee2e6;
    background: white;
    border-radius: 4px;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s;
}

.gap-preset-btn:hover {
    border-color: #007bff;
}

.gap-preset-btn.active {
    border-color: #007bff;
    background: #007bff;
    color: white;
}

.setting-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label {
    font-size: 0.875rem;
    color: #495057;
}

.instructions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.instruction-item {
    font-size: 0.75rem;
    color: #6c757d;
    line-height: 1.4;
}

.instruction-item strong {
    color: #495057;
}
</style>
