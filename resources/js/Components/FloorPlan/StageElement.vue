<script setup>
import { computed } from 'vue'

const props = defineProps({
    stage: Object,
    isPreview: Boolean,
    gridSize: Number
})

const emit = defineEmits(['mousedown'])

// Stage style with position and rotation
const stageStyle = computed(() => ({
    position: 'absolute',
    left: `${props.stage.position_x}px`,
    top: `${props.stage.position_y}px`,
    width: `${props.stage.width}px`,
    height: `${props.stage.height}px`,
    transform: `rotate(${props.stage.rotation}deg)`,
    transformOrigin: 'center center',
    zIndex: props.stage.z_index || 0,
    cursor: props.isPreview ? 'default' : 'grab',
    transition: props.stage.isDragging ? 'none' : 'all 0.2s ease'
}))
</script>

<template>
    <div
        class="stage-element draggable-element"
        :class="{
            'is-selected': stage.isSelected,
            'is-dragging': stage.isDragging,
            'is-preview': isPreview
        }"
        :style="stageStyle"
        @mousedown="emit('mousedown', $event)"
    >
        <!-- Stage Content -->
        <div class="stage-content drag-handle">
            <div class="stage-label">STAGE</div>
            <div class="stage-dimensions">{{ stage.width }}×{{ stage.height }}px</div>
        </div>

        <!-- Selection Handles -->
        <div v-if="stage.isSelected && !isPreview" class="selection-handles">
            <div class="handle handle-nw"></div>
            <div class="handle handle-ne"></div>
            <div class="handle handle-sw"></div>
            <div class="handle handle-se"></div>
            <div class="rotation-handle" title="Drag to rotate">⟲</div>
        </div>
    </div>
</template>

<style scoped>
.stage-element {
    background: linear-gradient(135deg, #6c757d, #495057);
    border: 3px solid #343a40;
    border-radius: 12px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    user-select: none;
}

.stage-element:hover:not(.is-preview) {
    border-color: #007bff;
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

.stage-element.is-selected {
    border-color: #28a745;
    box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.25);
}

.stage-element.is-dragging {
    opacity: 0.8;
    box-shadow: 0 12px 30px rgba(0,0,0,0.4);
}

.stage-content {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: grab;
}

.stage-content:active {
    cursor: grabbing;
}

.stage-label {
    font-size: 1.2rem;
    font-weight: 700;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    margin-bottom: 4px;
}

.stage-dimensions {
    font-size: 0.7rem;
    opacity: 0.8;
}

.selection-handles {
    position: absolute;
    top: -6px;
    left: -6px;
    right: -6px;
    bottom: -6px;
    pointer-events: none;
}

.handle {
    position: absolute;
    width: 10px;
    height: 10px;
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
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    background: #007bff;
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    cursor: grab;
    pointer-events: auto;
}

.rotation-handle:hover {
    background: #0056b3;
}

/* Preview mode styles */
.is-preview {
    cursor: default !important;
}

.is-preview .stage-content {
    cursor: default !important;
}

.is-preview:hover {
    border-color: #343a40 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
}
</style>
