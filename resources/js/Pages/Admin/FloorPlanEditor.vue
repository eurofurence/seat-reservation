<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, onMounted, ref, nextTick } from 'vue'
import FullWidthLayout from "@/Layouts/FullWidthLayout.vue"
import GridBlock from "@/Components/FloorPlan/GridBlock.vue"
import GridStage from "@/Components/FloorPlan/GridStage.vue"
import EditorSidebar from "@/Components/FloorPlan/EditorSidebar.vue"
import BlockPropertiesSidebar from "@/Components/FloorPlan/BlockPropertiesSidebar.vue"

defineOptions({layout: FullWidthLayout})

const props = defineProps({
    room: Object,
    blocks: Array,
    layout_config: Object
})

// Editor state
const gridRef = ref(null)
const editorBlocks = ref([])
const selectedElement = ref(null)
const isPreviewMode = ref(false)

// Layout configuration with CSS Grid settings
const layoutConfig = ref({
    grid_columns: 12,
    grid_rows: 8,
    gap_size: 48, // gap-12 = 3rem = 48px
    show_grid_lines: true,
    ...props.layout_config // Override with server data if available
})

// Initialize editor
onMounted(() => {
    initializeEditor()
})

function initializeEditor() {
    editorBlocks.value = props.blocks.map(block => ({
        ...block,
        grid_column: block.grid_column || 1,
        grid_row: block.grid_row || 1,
        grid_column_span: block.grid_column_span || 1,
        grid_row_span: block.grid_row_span || 1,
        isSelected: false
    }))
}

// Grid computed styles
const gridStyle = computed(() => {
    if (!layoutConfig.value) {
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
        gridTemplateColumns: `repeat(${layoutConfig.value.grid_columns || 12}, minmax(120px, 1fr))`,
        gridTemplateRows: `repeat(${layoutConfig.value.grid_rows || 8}, minmax(80px, auto))`,
        gap: `${layoutConfig.value.gap_size || 48}px`,
        minHeight: 'max-content',
        width: 'max-content',
        minWidth: '100%',
        padding: '2rem',
        background: '#f8f9fa',
        border: '2px solid #dee2e6',
        borderRadius: '12px'
    }
})

// Element selection
function selectElement(element) {
    // Deselect all elements
    editorBlocks.value.forEach(block => block.isSelected = false)
    
    // Select the clicked element
    element.isSelected = true
    selectedElement.value = element
}

function clearSelection() {
    editorBlocks.value.forEach(block => block.isSelected = false)
    selectedElement.value = null
}

// Grid position updates
function onBlockGridPositionChange(blockId, gridData) {
    if (!blockId || !gridData) return
    
    const block = editorBlocks.value.find(b => b.id === blockId)
    if (!block) return
    
    Object.assign(block, gridData)
    saveBlockPosition(block)
}

function onStageGridPositionChange(gridData) {
    if (!layoutConfig.value.stage || !gridData) return
    
    Object.assign(layoutConfig.value.stage, gridData)
    // Stage position is saved with layout config, so we don't need separate persistence
}

async function saveBlockPosition(block) {
    if (!block || !block.id) return
    
    try {
        const response = await fetch(`/admin/rooms/${props.room.id}/floor-plan/blocks/${block.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                grid_column: block.grid_column || 1,
                grid_row: block.grid_row || 1,
                grid_column_span: block.grid_column_span || 1,
                grid_row_span: block.grid_row_span || 1
            })
        })
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
    } catch (error) {
        console.error('Failed to save block position:', error)
        showNotification('Failed to save block position', 'error')
    }
}

// Add new block
function addNewBlock() {
    const newBlock = {
        id: 'temp-' + Date.now(),
        name: 'New Block',
        grid_column: 1,
        grid_row: 1,
        grid_column_span: 2,
        grid_row_span: 1,
        rows: [
            {
                id: 'temp-row-1',
                name: 'Row 1',
                seat_count: 10,
                seats: Array.from({length: 10}, (_, i) => ({
                    id: `temp-seat-${i + 1}`,
                    number: i + 1,
                    is_available: true
                }))
            }
        ],
        isSelected: false,
        isNew: true
    }

    editorBlocks.value.push(newBlock)
    selectElement(newBlock)
}

// Add stage
function addStage() {
    if (!layoutConfig.value.stage) {
        layoutConfig.value.stage = {
            id: 'stage',
            grid_column: Math.floor(layoutConfig.value.grid_columns / 2) - 1,
            grid_row: 1,
            grid_column_span: 4,
            grid_row_span: 1,
            isSelected: false
        }
    }
}

// Remove element
function removeElement(element) {
    const index = editorBlocks.value.findIndex(block => block.id === element.id)
    if (index > -1) {
        editorBlocks.value.splice(index, 1)
    }
    selectedElement.value = null
}

// Update element properties
async function updateElement(updates) {
    if (!selectedElement.value) return
    
    // Update the selected element
    Object.assign(selectedElement.value, updates)
    
    // Find and update the corresponding block in editorBlocks
    const blockIndex = editorBlocks.value.findIndex(block => block.id === selectedElement.value.id)
    if (blockIndex > -1) {
        // For better reactivity, replace the entire block object
        editorBlocks.value[blockIndex] = { ...editorBlocks.value[blockIndex], ...updates }
        
        // Also update the selected element to match
        selectedElement.value = editorBlocks.value[blockIndex]
        
        // Save the block changes to the server using fetch
        try {
            const response = await fetch(`/admin/rooms/${props.room.id}/floor-plan/blocks/${selectedElement.value.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    grid_column: selectedElement.value.grid_column,
                    grid_row: selectedElement.value.grid_row,
                    grid_column_span: selectedElement.value.grid_column_span,
                    grid_row_span: selectedElement.value.grid_row_span,
                    rows: selectedElement.value.rows || []
                })
            })
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }
            
            // Only show success message for row updates (not grid position changes)
            if (updates.rows) {
                showNotification('Block configuration saved!', 'success')
            }
        } catch (error) {
            console.error('Failed to update block:', error)
            showNotification('Failed to save block changes', 'error')
        }
    }
}

// Save layout
async function saveLayout() {
    const formData = {
        layout_config: layoutConfig.value,
        blocks: editorBlocks.value.filter(block => !block.isNew).map(block => ({
            id: block.id,
            grid_column: block.grid_column,
            grid_row: block.grid_row,
            grid_column_span: block.grid_column_span,
            grid_row_span: block.grid_row_span,
            rows: block.rows || []
        }))
    }
    
    // Debug logging
    console.log('Saving layout with data:', formData)
    
    try {
        const response = await fetch(`/admin/rooms/${props.room.id}/floor-plan/update`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(formData)
        })
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        showNotification('Floor plan saved successfully!', 'success')
    } catch (error) {
        console.error('Save errors:', error)
        showNotification('Failed to save floor plan', 'error')
    }
}

// Create new blocks
async function createNewBlocks() {
    const newBlocks = editorBlocks.value.filter(block => block.isNew)

    for (const block of newBlocks) {
        const formData = {
            name: block.name,
            grid_column: block.grid_column,
            grid_row: block.grid_row,
            grid_column_span: block.grid_column_span,
            grid_row_span: block.grid_row_span,
            rows: (block.rows || []).map(row => ({
                name: row.name,
                seat_count: parseInt(row.seat_count) || 0
            }))
        }

        try {
            const response = await fetch(`/admin/rooms/${props.room.id}/floor-plan/blocks`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(formData)
            })
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`)
            }
            
            showNotification(`Block "${block.name}" created successfully!`, 'success')
        } catch (error) {
            console.error('Error creating block:', error)
            showNotification(`Failed to create block "${block.name}"`, 'error')
        }
    }

    // Refresh the page to get the created blocks with proper IDs
    window.location.reload()
}

// Toggle preview mode
function togglePreviewMode() {
    isPreviewMode.value = !isPreviewMode.value
    clearSelection()
}

// Utility functions
function showNotification(message, type = 'info') {
    // Create a simple toast notification
    const toast = document.createElement('div')
    toast.className = `toast toast-${type}`
    toast.textContent = message
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.3s ease;
    `
    
    // Set color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        info: '#007bff',
        warning: '#ffc107'
    }
    toast.style.backgroundColor = colors[type] || colors.info
    
    document.body.appendChild(toast)
    
    // Animate in
    setTimeout(() => toast.style.opacity = '1', 10)
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0'
        setTimeout(() => toast.remove(), 300)
    }, 3000)
    
    console.log(`${type.toUpperCase()}: ${message}`)
}

// Keyboard shortcuts
onMounted(() => {
    function handleKeyDown(event) {
        if (!selectedElement.value || isPreviewMode.value) return

        switch (event.key) {
            case 'Delete':
            case 'Backspace':
                removeElement(selectedElement.value)
                break
            case 'Escape':
                clearSelection()
                break
        }
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
        document.removeEventListener('keydown', handleKeyDown)
    }
})
</script>

<template>
    <Head title="Floor Plan Editor" />

    <div class="floor-plan-editor">
        <!-- Header -->
        <div class="editor-header">
            <div class="header-left">
                <h1 class="editor-title">
                    Floor Plan Editor - {{ room?.name || 'Loading...' }}
                </h1>
                <div class="editor-mode-toggle">
                    <button
                        @click="togglePreviewMode"
                        :class="['mode-btn', { active: !isPreviewMode }]"
                    >
                        Edit
                    </button>
                    <button
                        @click="togglePreviewMode"
                        :class="['mode-btn', { active: isPreviewMode }]"
                    >
                        Preview
                    </button>
                </div>
            </div>
            <div class="header-right">
                <button
                    @click="createNewBlocks"
                    v-if="editorBlocks.some(b => b.isNew)"
                    class="btn btn-secondary"
                >
                    Create New Blocks
                </button>
                <button @click="saveLayout" class="btn btn-primary">
                    Save Layout
                </button>
            </div>
        </div>

        <!-- Main Editor Area -->
        <div class="editor-main">
            <!-- Left Sidebar -->
            <EditorSidebar
                v-if="!isPreviewMode"
                :layout-config="layoutConfig"
                @add-block="addNewBlock"
                @add-stage="addStage"
                @update-layout-config="(updates) => Object.assign(layoutConfig, updates)"
            />

            <!-- Grid Container -->
            <div class="grid-container">
                <div
                    ref="gridRef"
                    class="grid-editor"
                    :style="gridStyle"
                    @click="clearSelection"
                >
                    <!-- Grid Blocks -->
                    <GridBlock
                        v-for="block in editorBlocks"
                        :key="block.id"
                        :block="block"
                        :is-preview="isPreviewMode"
                        :grid-config="layoutConfig"
                        @click="selectElement(block)"
                        @grid-position-change="onBlockGridPositionChange"
                    />

                    <!-- Stage Element -->
                    <GridStage
                        v-if="layoutConfig.stage"
                        :stage="layoutConfig.stage"
                        :is-preview="isPreviewMode"
                        :grid-config="layoutConfig"
                        @click="selectElement(layoutConfig.stage)"
                        @grid-position-change="onStageGridPositionChange"
                    />
                </div>
            </div>

            <!-- Right Sidebar -->
            <BlockPropertiesSidebar
                v-if="!isPreviewMode"
                :element="selectedElement"
                :grid-config="layoutConfig"
                @update-element="updateElement"
                @remove-element="removeElement"
                @close="clearSelection"
            />
        </div>
    </div>
</template>

<style scoped>
.floor-plan-editor {
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background: white;
    border-bottom: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.editor-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #212529;
    margin: 0;
}

.editor-mode-toggle {
    display: flex;
    background: #e9ecef;
    border-radius: 6px;
    padding: 2px;
}

.mode-btn {
    padding: 0.5rem 1rem;
    border: none;
    background: transparent;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.mode-btn.active {
    background: white;
    color: #007bff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.header-right {
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.editor-main {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.grid-container {
    flex: 1;
    padding: 2rem;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.grid-editor {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    user-select: none;
    background-image: 
        linear-gradient(to right, rgba(0,0,0,0.1) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(0,0,0,0.1) 1px, transparent 1px);
    background-size: calc(100% / var(--grid-columns)) calc(100% / var(--grid-rows));
}

.grid-editor:not([data-show-grid]) {
    background-image: none;
}
</style>
