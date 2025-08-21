<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import Panzoom from '@panzoom/panzoom'
import SeatBlock from './SeatBlock.vue'

const props = defineProps({
  event: Object,
  room: Object,
  blocks: Array,
  selectedSeats: Array,
  bookedSeats: Array
})

const emit = defineEmits(['seats-changed'])

// Seat selection state
const selectedSeatIds = ref([...props.selectedSeats])

// Panzoom refs
const panzoomContainer = ref(null)
const panzoomInstance = ref(null)

// Calculate dynamic grid dimensions based on content
const gridDimensions = computed(() => {
  let maxX = 0
  let maxY = 0

  // Check stage position
  const stageX = props.room?.stage_x
  const stageY = props.room?.stage_y
  if (stageX >= 0 && stageY >= 0) {
    maxX = Math.max(maxX, stageX + 1)
    maxY = Math.max(maxY, stageY + 1)
  }

  // Check block positions
  props.blocks?.forEach(block => {
    if (block.position_x >= 0 && block.position_y >= 0) {
      maxX = Math.max(maxX, block.position_x + 1)
      maxY = Math.max(maxY, block.position_y + 1)
    }
  })

  // Minimum grid size
  return {
    cols: Math.max(maxX, 1),
    rows: Math.max(maxY, 1)
  }
})

// Create layout grid with blocks and stage positioned
const layoutGrid = computed(() => {
  const { rows, cols } = gridDimensions.value
  const grid = Array(rows).fill(null).map(() => Array(cols).fill(null))

  // Place stage if positioned
  const stageX = props.room?.stage_x
  const stageY = props.room?.stage_y
  if (stageX >= 0 && stageY >= 0 && stageX < cols && stageY < rows) {
    grid[stageY][stageX] = { type: 'stage' }
  }

  // Place blocks that have valid positions
  props.blocks?.forEach(block => {
    const x = block.position_x
    const y = block.position_y
    if (x >= 0 && y >= 0 && x < cols && y < rows) {
      grid[y][x] = { type: 'block', ...block }
    }
  })

  return grid
})

// Get blocks that are positioned off-grid (for separate display)
const unplacedBlocks = computed(() => {
  const { rows, cols } = gridDimensions.value
  return props.blocks?.filter(block =>
    block.position_x < 0 || block.position_x >= cols ||
    block.position_y < 0 || block.position_y >= rows ||
    block.position_x == null || block.position_y == null
  ) || []
})

// Get seat status styling
const getSeatStatus = (seat) => {
  const seatId = seat.id

  if (props.bookedSeats.includes(seatId)) {
    return { class: 'seat-booked', disabled: true }
  }

  if (selectedSeatIds.value.includes(seatId)) {
    return { class: 'seat-selected', disabled: false }
  }

  return { class: 'seat-available', disabled: false }
}

// Handle seat click
const handleSeatClick = (seat) => {
  const seatId = seat.id

  if (props.bookedSeats.includes(seatId)) return

  const index = selectedSeatIds.value.indexOf(seatId)
  if (index > -1) {
    selectedSeatIds.value.splice(index, 1)
  } else {
    selectedSeatIds.value.push(seatId)
  }

  emit('seats-changed', [...selectedSeatIds.value])
}


// Initialize Panzoom
onMounted(() => {
  // Use nextTick to ensure DOM is fully rendered
  nextTick(() => {
    if (panzoomContainer.value) {
      const panzoomContent = panzoomContainer.value.querySelector('.panzoom-content')
      if (panzoomContent) {
        try {
          panzoomInstance.value = Panzoom(panzoomContent, {
            maxScale: 3,
            minScale: 0.3,
            startScale: 0.6, // Default zoom-out scale
            contain: 'outside',
            cursor: 'grab',
            panOnlyWhenZoomed: false,
            excludeClass: 'seat', // Don't pan when clicking seats
            handleStartEvent: (event) => {
              // Allow seat clicks to work normally
              if (event.target.classList.contains('seat')) {
                return false
              }
              return true
            }
          })

          // Add mouse wheel zoom to container
          panzoomContainer.value.addEventListener('wheel', (event) => {
            if (panzoomInstance.value) {
              panzoomInstance.value.zoomWithWheel(event)
            }
          })
        } catch (error) {
          console.error('Error initializing Panzoom:', error)
        }
      }
    }
  })
})

// Cleanup Panzoom
onUnmounted(() => {
  if (panzoomInstance.value) {
    panzoomInstance.value.destroy()
  }
})

// Watch for external seat selection changes
watch(() => props.selectedSeats, (newSeats) => {
  selectedSeatIds.value = [...newSeats]
}, { immediate: true })
</script>

<template>
  <div class="seating-layout-wrapper">
    <!-- Main Layout Grid with Panzoom -->
    <div class="layout-container" ref="panzoomContainer">
      <div class="panzoom-content">
        <table class="seat-layout-table">
          <tbody>
            <tr v-for="(row, rowIndex) in layoutGrid" :key="rowIndex">
              <td
                v-for="(cell, colIndex) in row"
                :key="colIndex"
                class="layout-cell"
              >
                <!-- Empty Cell -->
                <div v-if="cell === null" class="empty-cell"></div>

                <!-- Stage Cell -->
                <div
                  v-else-if="cell.type === 'stage'"
                  class="stage-cell"
                >
                  <div class="stage-content">
                    🎭 STAGE
                  </div>
                </div>

                <!-- Block Cell -->
                <div
                  v-else-if="cell.type === 'block'"
                  class="block-cell"
                >
                  <SeatBlock
                    :block="cell"
                    :booked-seats="bookedSeats"
                    :selected-seats="selectedSeatIds"
                    @seat-click="handleSeatClick"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Unplaced Blocks (inside panzoom content) -->
        <div v-if="unplacedBlocks.length > 0" class="unplaced-blocks">
          <h4 class="unplaced-title">Additional Blocks (Not Positioned)</h4>
          <div class="unplaced-container">
            <SeatBlock
              v-for="block in unplacedBlocks"
              :key="`unplaced-${block.id}`"
              :block="block"
              :booked-seats="bookedSeats"
              :selected-seats="selectedSeatIds"
              @seat-click="handleSeatClick"
              class="border-orange-400 bg-orange-50"
            />
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
.seating-layout-wrapper {
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  padding: 20px;
  background: #f8fafc;
  border-radius: 8px;
}

/* Layout Container */
.layout-container {
  overflow: hidden; /* Panzoom handles overflow */
  margin-bottom: 20px;
  height: 80vh;
  width: 100%;
  cursor: grab;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.layout-container:active {
  cursor: grabbing;
}

.panzoom-content {
  transform-origin: 0 0;
  display: inline-block;
  min-width: 100%;
  min-height: 100%;
}

.seat-layout-table {
  border-collapse: separate;
  border-spacing: 0;
  background: white;
}

.layout-cell {
  padding: 8px;
  vertical-align: top;
  border: none;
  background: transparent;
  width: auto;
  height: auto;
}

/* Empty Cell */
.empty-cell {
  width: 100%;
  height: 100%;
  background: transparent;
}

/* Stage Cell */
.stage-cell {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stage-content {
  background: #dc2626;
  color: white;
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: bold;
  font-size: 14px;
  text-align: center;
  box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

/* Block Cell */
.block-cell {
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  width: 100%;
  height: 100%;
  overflow: visible;
}

/* Unplaced Blocks */
.unplaced-blocks {
  margin-top: 20px;
  padding: 16px;
  background: white;
  border-radius: 8px;
  border: 2px dashed #d1d5db;
}

.unplaced-title {
  font-size: 16px;
  font-weight: bold;
  color: #374151;
  margin-bottom: 12px;
}

.unplaced-container {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

/* Legend */
.seat-legend {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin-top: 16px;
  padding: 12px;
  background: white;
  border-radius: 6px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: #374151;
}

.legend-item .seat {
  width: 20px;
  height: 20px;
  margin: 0;
}

/* Remove responsive styles - layout should never distort */
/* The layout maintains its exact proportions and becomes scrollable if needed */
</style>
