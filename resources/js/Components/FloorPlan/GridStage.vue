<script setup>
import { computed, ref } from 'vue'
import { Theater } from 'lucide-vue-next'

const props = defineProps({
    stage: Object,
    isPreview: Boolean,
    gridConfig: Object
})

const emit = defineEmits(['click', 'grid-position-change'])

const isDragging = ref(false)
const dragStartPosition = ref({ x: 0, y: 0 })

// Grid positioning computed properties
const gridItemStyle = computed(() => ({
    gridColumn: `${props.stage.grid_column || 1} / span ${props.stage.grid_column_span || 4}`,
    gridRow: `${props.stage.grid_row || 1} / span ${props.stage.grid_row_span || 1}`,
    backgroundColor: '#1a1a1a',
    color: 'white',
    border: props.stage.isSelected ? '2px solid #2196f3' : '1px solid #333',
    borderRadius: '8px',
    padding: '1rem',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    justifyContent: 'center',
    cursor: props.isPreview ? 'default' : (isDragging.value ? 'grabbing' : 'grab'),
    transition: isDragging.value ? 'none' : 'all 0.2s ease',
    position: 'relative',
    background: 'linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%)',
    opacity: isDragging.value ? 0.8 : 1
}))

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
                props.gridConfig.grid_columns - (props.stage.grid_column_span || 4) + 1
            ))
            const newRow = Math.max(1, Math.min(
                Math.floor(relativeY / cellHeight) + 1,
                props.gridConfig.grid_rows - (props.stage.grid_row_span || 1) + 1
            ))
            
            if (newColumn !== props.stage.grid_column || newRow !== props.stage.grid_row) {
                emit('grid-position-change', {
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
</script>

<template>
    <div
        class="grid-stage"
        :style="gridItemStyle"
        @mousedown="handleMouseDown"
        @click.stop="$emit('click')"
    >
        <!-- Stage Icon and Label -->
        <div class="stage-content">
            <Theater :size="32" class="stage-icon" />
            <span class="stage-label">STAGE</span>
        </div>

        <!-- Stage Lighting Effect -->
        <div class="stage-lighting"></div>

        <!-- Selection Indicator -->
        <div v-if="!isPreview && stage.isSelected" class="selection-indicator">
            <div class="resize-handles">
                <div class="resize-handle resize-handle-nw"></div>
                <div class="resize-handle resize-handle-ne"></div>
                <div class="resize-handle resize-handle-sw"></div>
                <div class="resize-handle resize-handle-se"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.grid-stage {
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    overflow: hidden;
}

.grid-stage:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}

.stage-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    z-index: 1;
}

.stage-icon {
    color: #ffd700;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

.stage-label {
    font-weight: 700;
    font-size: 1.125rem;
    letter-spacing: 2px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5);
}

.stage-lighting {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(
        ellipse at center top,
        rgba(255, 215, 0, 0.1) 0%,
        rgba(255, 215, 0, 0.05) 30%,
        transparent 70%
    );
    pointer-events: none;
}

.selection-indicator {
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    border: 2px solid #2196f3;
    border-radius: 8px;
    pointer-events: none;
}

.resize-handles {
    position: relative;
    width: 100%;
    height: 100%;
}

.resize-handle {
    position: absolute;
    width: 8px;
    height: 8px;
    background: #2196f3;
    border: 1px solid white;
    border-radius: 50%;
}

.resize-handle-nw {
    top: -4px;
    left: -4px;
    cursor: nw-resize;
}

.resize-handle-ne {
    top: -4px;
    right: -4px;
    cursor: ne-resize;
}

.resize-handle-sw {
    bottom: -4px;
    left: -4px;
    cursor: sw-resize;
}

.resize-handle-se {
    bottom: -4px;
    right: -4px;
    cursor: se-resize;
}
</style>
