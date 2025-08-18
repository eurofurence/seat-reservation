<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import Alert from '@/Components/ui/Alert.vue'

const props = defineProps({
  room: Object,
  blocks: Array
})

// Form for updating block positions and stage
const form = useForm({
  stage_x: props.room.stage_x || 0,
  stage_y: props.room.stage_y || 0,
  blocks: props.blocks.map(block => ({
    id: block.id,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1,
    rotation: block.rotation || 0
  }))
})

// Layout grid size (HTML table cells)
const GRID_ROWS = 8
const GRID_COLS = 12

// Dragging state
const dragState = ref({
  isDragging: false,
  dragBlockId: null,
  sourceType: null // 'grid' or 'sidebar'
})

// Create empty grid with block assignments and stage
const layoutGrid = computed(() => {
  const grid = Array(GRID_ROWS).fill(null).map(() => Array(GRID_COLS).fill(null))
  
  // Place stage
  const stageX = form.stage_x
  const stageY = form.stage_y
  if (stageX >= 0 && stageX < GRID_COLS && stageY >= 0 && stageY < GRID_ROWS) {
    grid[stageY][stageX] = { type: 'stage' }
  }
  
  // Place blocks
  form.blocks.forEach(block => {
    const x = block.position_x
    const y = block.position_y
    if (x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS) {
      grid[y][x] = { type: 'block', ...block }
    }
  })
  
  return grid
})

// Get unplaced blocks (not on grid or without coordinates)
const unplacedBlocks = computed(() => {
  return form.blocks.filter(block => 
    block.position_x < 0 || block.position_x >= GRID_COLS || 
    block.position_y < 0 || block.position_y >= GRID_ROWS ||
    block.position_x == null || block.position_y == null
  )
})

// Check if stage is unplaced
const isStageUnplaced = computed(() => {
  return form.stage_x < 0 || form.stage_x >= GRID_COLS || 
         form.stage_y < 0 || form.stage_y >= GRID_ROWS ||
         form.stage_x == null || form.stage_y == null
})

// Get original block data for display
const getOriginalBlock = (blockId) => {
  return props.blocks.find(block => block.id === blockId)
}

// Get total seats from the optimized count
const getTotalSeats = (block) => {
  const originalBlock = getOriginalBlock(block.id)
  return originalBlock?.total_seats || 0
}

// Get orientation arrow
const getOrientationArrow = (rotation) => {
  const arrows = {
    0: '↑',    // Up (toward top)
    90: '→',   // Right
    180: '↓',  // Down
    270: '←'   // Left
  }
  return arrows[rotation] || '↑'
}

// Handle drag start from sidebar
const handleDragStart = (event, item, sourceType = 'sidebar') => {
  const data = item.type === 'stage' ? 
    { type: 'stage', sourceType } : 
    { type: 'block', blockId: item.id, sourceType }
  
  dragState.value = {
    isDragging: true,
    dragBlockId: item.type === 'stage' ? 'stage' : item.id,
    sourceType: sourceType
  }
  
  event.dataTransfer.setData('application/json', JSON.stringify(data))
  event.dataTransfer.effectAllowed = 'move'
}

// Handle drag start from grid
const handleGridDragStart = (event, item) => {
  handleDragStart(event, item, 'grid')
}

// Handle drop on grid cell
const handleDrop = (event, rowIndex, colIndex) => {
  event.preventDefault()
  
  try {
    const data = JSON.parse(event.dataTransfer.getData('application/json'))
    
    // Check if cell is empty
    if (layoutGrid.value[rowIndex][colIndex] === null) {
      if (data.type === 'stage') {
        form.stage_x = colIndex
        form.stage_y = rowIndex
      } else if (data.type === 'block') {
        const blockIndex = form.blocks.findIndex(block => block.id === data.blockId)
        if (blockIndex !== -1) {
          form.blocks[blockIndex].position_x = colIndex
          form.blocks[blockIndex].position_y = rowIndex
        }
      }
    }
  } catch (error) {
    console.error('Drop error:', error)
  }
  
  dragState.value.isDragging = false
}

// Handle drop on sidebar (remove from grid)
const handleSidebarDrop = (event) => {
  event.preventDefault()
  
  try {
    const data = JSON.parse(event.dataTransfer.getData('application/json'))
    if (data.sourceType === 'grid') {
      if (data.type === 'stage') {
        form.stage_x = -1
        form.stage_y = -1
      } else if (data.type === 'block') {
        const blockIndex = form.blocks.findIndex(block => block.id === data.blockId)
        if (blockIndex !== -1) {
          // Place outside grid to mark as unplaced
          form.blocks[blockIndex].position_x = -1
          form.blocks[blockIndex].position_y = -1
        }
      }
    }
  } catch (error) {
    console.error('Sidebar drop error:', error)
  }
  
  dragState.value.isDragging = false
}

// Allow drop
const allowDrop = (event) => {
  event.preventDefault()
}

// Rotate block
const rotateBlock = (blockId) => {
  const blockIndex = form.blocks.findIndex(block => block.id === blockId)
  if (blockIndex !== -1) {
    const currentRotation = form.blocks[blockIndex].rotation
    form.blocks[blockIndex].rotation = (currentRotation + 90) % 360
  }
}

// Save layout
const saveLayout = () => {
  form.put(route('admin.rooms.layout.update', props.room.id))
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <Head :title="`Edit Layout - ${room.name}`" />
    
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold">Room Layout Editor: {{ room.name }}</h1>
          </div>
          <div class="flex items-center space-x-4">
            <Button 
              variant="outline" 
              @click="$inertia.get('/admin')"
            >
              Back to Admin
            </Button>
            <Button 
              @click="saveLayout"
              :disabled="form.processing"
              :loading="form.processing"
            >
              Save Layout
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="$page.props.flash?.success" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <Alert class="bg-green-50 border-green-200">
        <div class="text-green-800">
          {{ $page.props.flash.success }}
        </div>
      </Alert>
    </div>

    <!-- Instructions -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <Card class="p-4 mb-6">
        <h3 class="font-semibold mb-2">How to Edit Layout:</h3>
        <ul class="text-sm text-gray-600 space-y-1">
          <li>• <strong>Drag stage and blocks</strong> from sidebar to table cells to position them</li>
          <li>• <strong>Drag items</strong> from table back to sidebar to remove them</li>
          <li>• <strong>Click rotate button</strong> to change block orientation (arrow points toward stage)</li>
          <li>• <strong>Stage starts at 0,0</strong> and can be positioned anywhere</li>
          <li>• <strong>Blocks without coordinates</strong> automatically go to available items</li>
          <li>• <strong>Block sizes</strong> are automatically determined by seat count</li>
        </ul>
      </Card>
    </div>

    <!-- Layout Editor -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Sidebar: Available Items -->
        <div class="lg:col-span-1">
          <Card class="p-4">
            <h3 class="font-semibold mb-4">Available Items</h3>
            <div 
              class="space-y-2 min-h-[200px] border-2 border-dashed border-gray-300 rounded-lg p-2"
              @drop="handleSidebarDrop"
              @dragover="allowDrop"
            >
              <!-- Unplaced Stage -->
              <div
                v-if="isStageUnplaced"
                draggable="true"
                @dragstart="handleDragStart($event, { type: 'stage' })"
                class="block-item cursor-move bg-red-50 border border-red-200 rounded p-3 hover:shadow-md transition-shadow"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <div class="font-medium text-sm text-red-700">🎭 STAGE</div>
                    <div class="text-xs text-red-600">Drag to position</div>
                  </div>
                </div>
              </div>

              <!-- Unplaced Blocks -->
              <div
                v-for="block in unplacedBlocks"
                :key="`sidebar-${block.id}`"
                draggable="true"
                @dragstart="handleDragStart($event, { type: 'block', id: block.id, ...block })"
                class="block-item cursor-move bg-white border border-gray-200 rounded p-3 hover:shadow-md transition-shadow"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <div class="font-medium text-sm">{{ getOriginalBlock(block.id)?.name }}</div>
                    <div class="text-xs text-gray-500">{{ getTotalSeats(block) }} seats • {{ getOriginalBlock(block.id)?.rows_count || 0 }} rows</div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="text-lg">{{ getOrientationArrow(block.rotation) }}</span>
                    <Button 
                      size="sm" 
                      variant="outline"
                      @click="rotateBlock(block.id)"
                      class="text-xs px-2 py-1"
                    >
                      ↻
                    </Button>
                  </div>
                </div>
              </div>
              
              <div v-if="unplacedBlocks.length === 0 && !isStageUnplaced" class="text-center text-gray-500 py-8">
                All items are placed<br>
                <span class="text-xs">Drag from table to add here</span>
              </div>
            </div>
          </Card>
        </div>

        <!-- Main Layout Table -->
        <div class="lg:col-span-3">
          <Card class="p-6">
            <div class="mb-4">
              <h3 class="font-semibold text-center text-lg mb-2">
                Room Layout Grid
              </h3>
              <div class="text-center text-sm text-gray-600 mb-4">
                Drag stage and blocks to position them • Blocks should face toward the stage
              </div>
            </div>
            
            <!-- Layout Table -->
            <div class="overflow-auto">
              <table class="layout-table w-full border-collapse border border-gray-300">
                <tbody>
                  <tr v-for="(row, rowIndex) in layoutGrid" :key="rowIndex">
                    <td
                      v-for="(cell, colIndex) in row"
                      :key="colIndex"
                      class="layout-cell border border-gray-300 w-24 h-24 p-1 relative"
                      :class="{
                        'bg-gray-50': cell === null,
                        'bg-red-100': cell?.type === 'stage',
                        'bg-blue-50': cell?.type === 'block'
                      }"
                      @drop="handleDrop($event, rowIndex, colIndex)"
                      @dragover="allowDrop"
                    >
                      <!-- Empty Cell -->
                      <div v-if="cell === null" class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                        {{ rowIndex }},{{ colIndex }}
                      </div>
                      
                      <!-- Stage Cell -->
                      <div
                        v-else-if="cell.type === 'stage'"
                        draggable="true"
                        @dragstart="handleGridDragStart($event, cell)"
                        class="w-full h-full bg-red-600 text-white border border-red-700 rounded cursor-move flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow"
                      >
                        <div class="font-bold text-sm">🎭</div>
                        <div class="font-medium text-xs">STAGE</div>
                      </div>
                      
                      <!-- Block Cell -->
                      <div
                        v-else-if="cell.type === 'block'"
                        draggable="true"
                        @dragstart="handleGridDragStart($event, cell)"
                        class="w-full h-full bg-white border border-gray-400 rounded cursor-move flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow"
                      >
                        <!-- Block Title -->
                        <div class="font-medium text-xs mb-1 px-1">{{ getOriginalBlock(cell.id)?.name }}</div>
                        
                        <!-- Orientation Arrow -->
                        <div class="text-lg mb-1">{{ getOrientationArrow(cell.rotation) }}</div>
                        
                        <!-- Seat Count -->
                        <div class="text-xs text-gray-600">{{ getTotalSeats(cell) }} seats</div>
                        <div class="text-xs text-gray-500">{{ getOriginalBlock(cell.id)?.rows_count || 0 }} rows</div>
                        
                        <!-- Rotate Button -->
                        <Button 
                          size="sm" 
                          variant="outline"
                          @click="rotateBlock(cell.id)"
                          class="absolute top-0 right-0 text-xs px-1 py-0 text-blue-600 hover:bg-blue-100"
                        >
                          ↻
                        </Button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <!-- Grid Legend -->
            <div class="mt-4 text-xs text-gray-500 text-center">
              Grid coordinates: Row,Column • 🎭 Red = Stage • Blue = Blocks • Drag items to position them
            </div>
          </Card>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Layout Table Styling */
.layout-table {
  min-width: 800px;
}

.layout-cell {
  min-width: 96px;
  min-height: 96px;
  position: relative;
  transition: background-color 0.2s ease;
}

.layout-cell:hover {
  background-color: #f0f9ff !important;
}

/* Block Item Styling */
.block-item {
  transition: all 0.2s ease;
}

.block-item:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Drag and Drop Visual Feedback */
.layout-cell.drag-over {
  background-color: #dbeafe !important;
  border-color: #3b82f6 !important;
}

/* Block in cell styling */
.layout-cell .bg-white {
  transition: all 0.2s ease;
}

.layout-cell .bg-white:hover {
  transform: scale(1.02);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Orientation arrow styling */
.text-lg {
  line-height: 1;
  font-weight: bold;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .layout-table {
    min-width: 600px;
  }
  
  .layout-cell {
    min-width: 80px;
    min-height: 80px;
  }
}

@media (max-width: 768px) {
  .layout-cell {
    min-width: 60px;
    min-height: 60px;
  }
  
  .layout-cell .text-xs {
    font-size: 0.625rem;
  }
}
</style>