<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import Panzoom from '@panzoom/panzoom'
import SeatBlock from './SeatBlock.vue'
import { ArrowUp, ArrowRight, ArrowDown, ArrowLeft } from 'lucide-vue-next'
import type { LayoutBlock, PropStageBlock, PropMarkerBlock, Orientation } from '@/types/layout'

interface GridCellNamed { type: 'stage' | 'comment'; name: string; colSpan?: number; rowSpan?: number }
interface GridCellBlock extends LayoutBlock { type: 'block'; colSpan?: number; rowSpan?: number }
interface GridCellEntrance { type: 'entrance'; name: string; rotation: number; orientation: Orientation; colSpan?: number; rowSpan?: number }
interface GridCellCovered { type: 'covered' }
type GridCell = GridCellNamed | GridCellBlock | GridCellEntrance | GridCellCovered

const arrow = (rotation: number | undefined) =>
  ({ 0: ArrowUp, 90: ArrowRight, 180: ArrowDown, 270: ArrowLeft }[rotation ?? 0] || ArrowUp)

interface Props {
  event?: object
  room?: object
  blocks?: LayoutBlock[]
  stageBlocks?: PropStageBlock[]
  markerBlocks?: PropMarkerBlock[]
  selectedSeats?: number[]
  bookedSeats?: number[]
  reservedSeats?: number[]
  adminMode?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  markerBlocks: () => [],
  reservedSeats: () => [],
  adminMode: false,
})

const emit = defineEmits<{
  'seats-changed': [seats: number[]]
  'booked-seat-click': [seat: { id: number }]
}>()

const selectedSeatIds = ref<number[]>([...(props.selectedSeats ?? [])])

const panzoomContainer = ref<HTMLElement | null>(null)
const panzoomInstance = ref<ReturnType<typeof Panzoom> | null>(null)

const gridDimensions = computed(() => {
  const b = blockBounds.value
  let { minX, maxX, minY, maxY } = b

  props.markerBlocks?.forEach(m => {
    if (m.type !== 'entrance') return
    if (m.position_x >= 0) { minX = Math.min(minX, m.position_x); maxX = Math.max(maxX, m.position_x) }
    if (m.position_y >= 0) { minY = Math.min(minY, m.position_y); maxY = Math.max(maxY, m.position_y) }
  })

  if (!b.hasCols || !b.hasRows) {
    return { cols: 1, rows: 1, offsetX: 0, offsetY: 0 }
  }
  return { cols: maxX - minX + 1, rows: maxY - minY + 1, offsetX: minX, offsetY: minY }
})

const layoutGrid = computed(() => {
  const { rows, cols, offsetX, offsetY } = gridDimensions.value
  const grid: (GridCell | null)[][] = Array(rows).fill(null).map(() => Array(cols).fill(null))

  const inGrid = (col: number, row: number) => col >= 0 && col < cols && row >= 0 && row < rows

  props.stageBlocks?.forEach(stageBlock => {
    const col = stageBlock.position_x - offsetX
    const row = stageBlock.position_y - offsetY
    if (stageBlock.position_x >= 0 && stageBlock.position_y >= 0 && inGrid(col, row)) {
      grid[row][col] = { type: 'stage', name: stageBlock.name }
    }
  })

  props.blocks?.forEach(block => {
    const col = block.position_x - offsetX
    const row = block.position_y - offsetY
    if (block.position_x >= 0 && block.position_y >= 0 && inGrid(col, row)) {
      grid[row][col] = { type: 'block', ...block }
    }
  })

  const bounds = blockBounds.value

  props.markerBlocks?.forEach(marker => {
    const { position_x: x, position_y: y, name } = marker

    if (marker.type === 'comment') {
      const col = x - offsetX
      const row = y - offsetY
      if (x >= 0 && y >= 0 && inGrid(col, row)) {
        grid[row][col] = { type: 'comment', name }
      }
      return
    }

    const rotation = marker.rotation || 0

    if (x === -1 && y >= 0 && bounds.hasCols && inGrid(0, y - offsetY)) {
      placeEntrance('row', y - offsetY, bounds.minX - offsetX, bounds.maxX - bounds.minX + 1, name, rotation)
    } else if (y === -1 && x >= 0 && bounds.hasRows && inGrid(x - offsetX, 0)) {
      placeEntrance('column', x - offsetX, bounds.minY - offsetY, bounds.maxY - bounds.minY + 1, name, rotation)
    }
  })

  mergeAdjacentCells('stage')
  mergeAdjacentCells('comment')

  return grid

  function isCandidate(cell: GridCell | null | undefined, ref: GridCellNamed): cell is GridCellNamed {
    return cell?.type === ref.type && (cell as GridCellNamed)?.name === ref.name
  }

  function rowMatchesSpan(r: number, c: number, colSpan: number, ref: GridCellNamed) {
    for (let dc = 0; dc < colSpan; dc++) {
      if (!isCandidate(grid[r]?.[c + dc], ref)) return false
    }
    return true
  }

  function mergeAdjacentCells(type: GridCellNamed['type']) {
    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        const cell = grid[r][c]
        if (cell?.type !== type) continue

        const ref: GridCellNamed = { type, name: (cell as GridCellNamed).name }

        let colSpan = 1
        while (c + colSpan < cols && isCandidate(grid[r][c + colSpan], ref)) colSpan++

        let rowSpan = 1
        while (r + rowSpan < rows && rowMatchesSpan(r + rowSpan, c, colSpan, ref)) rowSpan++

        for (let dr = 0; dr < rowSpan; dr++) {
          for (let dc = 0; dc < colSpan; dc++) {
            if (dr === 0 && dc === 0) continue
            grid[r + dr][c + dc] = { type: 'covered' }
          }
        }

        grid[r][c] = { ...ref, colSpan, rowSpan }
      }
    }
  }

  function placeEntrance(orientation: Orientation, line: number, start: number, span: number, name: string, rotation: number) {
    const cell = (offset: number) => orientation === 'row' ? [line, start + offset] : [start + offset, line]
    const spanKey = orientation === 'row' ? 'colSpan' : 'rowSpan'

    for (let i = 0; i < span; i++) {
      const [r, c] = cell(i)
      grid[r][c] = i === 0
        ? { type: 'entrance', name, rotation, orientation, [spanKey]: span }
        : { type: 'covered' }
    }
  }
})

const blockBounds = computed(() => {
  let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity

  const consider = (x: number, y: number) => {
    if (x >= 0 && y >= 0) {
      minX = Math.min(minX, x); maxX = Math.max(maxX, x)
      minY = Math.min(minY, y); maxY = Math.max(maxY, y)
    }
  }

  props.blocks?.forEach(b => consider(b.position_x, b.position_y))
  props.stageBlocks?.forEach(b => consider(b.position_x, b.position_y))
  props.markerBlocks?.forEach(m => {
    if (m.type === 'comment') consider(m.position_x, m.position_y)
  })

  return {
    minX, maxX, minY, maxY,
    hasCols: maxX >= minX,
    hasRows: maxY >= minY
  }
})

const unplacedBlocks = computed(() =>
  props.blocks?.filter(block => block.position_x < 0 || block.position_y < 0) || []
)

const handleSeatClick = (seat: { id: number }) => {
  const seatId = seat.id

  if (props.bookedSeats?.includes(seatId)) return

  const index = selectedSeatIds.value.indexOf(seatId)
  if (index > -1) {
    selectedSeatIds.value.splice(index, 1)
  } else {
    selectedSeatIds.value.push(seatId)
  }

  emit('seats-changed', [...selectedSeatIds.value])
}

const handleBookedSeatClick = (seat: { id: number }) => {
  emit('booked-seat-click', seat)
}


onMounted(() => {
  nextTick(() => {
    if (panzoomContainer.value) {
      const panzoomContent = panzoomContainer.value.querySelector<HTMLElement>('.panzoom-content')
      if (panzoomContent) {
        try {
          panzoomInstance.value = Panzoom(panzoomContent, {
            maxScale: 3,
            minScale: 0.2,
            startScale: 0.2,
            contain: 'outside',
            cursor: 'grab',
            panOnlyWhenZoomed: false,
            excludeClass: 'seat',
            handleStartEvent: (event: Event) => {
              if ((event.target as Element).classList.contains('seat')) {
                return false
              }
              return true
            }
          })

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

onUnmounted(() => {
  if (panzoomInstance.value) {
    panzoomInstance.value.destroy()
  }
})

watch(() => props.selectedSeats, (newSeats) => {
  selectedSeatIds.value = [...(newSeats ?? [])]
}, { immediate: true })
</script>

<template>
  <div class="seating-layout-wrapper">
    <!-- Main Layout Grid with Panzoom -->
    <div class="layout-container" ref="panzoomContainer">
      <div class="panzoom-content">
        <table class="seat-layout-table p-32 bg-white">
          <tbody>
            <tr v-for="(row, rowIndex) in layoutGrid" :key="rowIndex">
              <template v-for="(cell, colIndex) in row" :key="colIndex">
              <td
                v-if="!cell || cell.type !== 'covered'"
                :colspan="cell?.colSpan || 1"
                :rowspan="cell?.rowSpan || 1"
                :class="[
                  'layout-cell',
                  {
                    'stage-layout-cell': cell && cell.type === 'stage',
                    'entrance-layout-cell': cell && cell.type === 'entrance',
                    'entrance-layout-row': cell && cell.type === 'entrance' && cell.orientation === 'row',
                    'entrance-layout-column': cell && cell.type === 'entrance' && cell.orientation === 'column'
                  }
                ]"
              >
                <div v-if="cell === null" class="empty-cell"></div>

                <div v-else-if="cell.type === 'stage'" class="stage-text" :style="{ minWidth: `${(cell.colSpan ?? 1) * 200}px`, minHeight: `${(cell.rowSpan ?? 1) * 60}px` }">
                  {{ cell.name || 'STAGE' }}
                </div>

                <div v-else-if="cell.type === 'block'" class="block-cell">
                  <SeatBlock
                    :block="cell"
                    :booked-seats="bookedSeats || []"
                    :selected-seats="selectedSeatIds"
                    :reserved-seats="reservedSeats"
                    :admin-mode="adminMode"
                    @seat-click="handleSeatClick"
                    @booked-seat-click="handleBookedSeatClick"
                  />
                </div>

                <div
                  v-else-if="cell.type === 'entrance'"
                  class="entrance-text"
                  :class="cell.orientation === 'column' ? 'entrance-column' : 'entrance-row'"
                >
                  <component :is="arrow(cell.rotation)" class="entrance-arrow" />
                  <span class="entrance-label" :class="{ 'entrance-label-flip': cell.rotation === 270 }">{{ cell.name || 'ENTRANCE' }}</span>
                  <component :is="arrow(cell.rotation)" class="entrance-arrow" />
                </div>

                <div v-else-if="cell.type === 'comment'" class="comment-text" :style="{ minWidth: `${(cell.colSpan ?? 1) * 60}px`, minHeight: `${(cell.rowSpan ?? 1) * 60}px` }">
                  {{ cell.name }}
                </div>
              </td>
              </template>
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
              :booked-seats="bookedSeats || []"
              :selected-seats="selectedSeatIds"
              :reserved-seats="reservedSeats"
              :admin-mode="adminMode"
              @seat-click="handleSeatClick"
              @booked-seat-click="handleBookedSeatClick"
              class="border-orange-400 bg-orange-50"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Legend -->
    <div v-if="!adminMode" class="seat-legend">
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
}

.layout-cell {
  padding: 8px;
  vertical-align: top;
  border: none;
  background: transparent;
  width: auto;
  height: auto;
}

.stage-layout-cell {
  background: linear-gradient(45deg, #e5e7eb 25%, transparent 25%),
              linear-gradient(-45deg, #e5e7eb 25%, transparent 25%),
              linear-gradient(45deg, transparent 75%, #e5e7eb 75%),
              linear-gradient(-45deg, transparent 75%, #e5e7eb 75%);
  background-size: 8px 8px;
  background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
  background-color: #f3f4f6;
  border: 2px solid #d1d5db;
  border-radius: 6px;
  margin: 4px;
  padding: 0;
  text-align: center;
  vertical-align: middle;
  cursor: not-allowed;
  user-select: none;
  min-width: 200px;
}

.entrance-layout-cell {
  position: relative;
  padding: 8px;
}

.entrance-layout-row {
  height: 76px;
}

.entrance-layout-column {
  min-width: 76px;
}

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

.empty-cell {
  width: 100%;
  height: 100%;
  background: transparent;
}

.stage-text {
  color: #6b7280;
  font-weight: bold;
  font-size: 14px;
  letter-spacing: 0.1em;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-height: 60px;
  min-width: 200px;
}

.block-cell {
  display: flex;
  align-items: flex-start;
  justify-content: center;
  width: 100%;
  height: 100%;
  overflow: visible;
}

.entrance-text {
  position: absolute;
  inset: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background-color: #fef3c7;
  border: 2px dashed #f59e0b;
  border-radius: 6px;
  color: #92400e;
  font-weight: bold;
  font-size: 12px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  user-select: none;
}

.entrance-arrow {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.entrance-column {
  flex-direction: column;
}

.entrance-column .entrance-label {
  writing-mode: vertical-rl;
  text-orientation: mixed;
}

.entrance-column .entrance-label-flip {
  transform: rotate(180deg);
}

.comment-text {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  min-height: 60px;
  padding: 6px;
  background-color: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  color: #1f2937;
  font-size: 12px;
  font-weight: 600;
  text-align: center;
  white-space: normal;
  word-break: break-word;
  user-select: none;
}
</style>
