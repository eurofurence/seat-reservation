<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  room: Object,
  blocks: Array,
  stageBlocks: Array,
  title: String,
  breadcrumbs: Array
})

// Layout grid size (HTML table cells)
const GRID_ROWS = 8
const GRID_COLS = 12

// Form data for entire layout
const form = useForm({
  stageBlocks: props.stageBlocks.map(block => ({
    id: block.id,
    name: block.name,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1
  })),
  blocks: props.blocks.map(block => ({
    id: block.id,
    name: block.name,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1,
    rotation: block.rotation || 0
  }))
})

// UI State
const selectedBlock = ref(null)
const selectedBlockType = ref(null) // 'stage' or 'seating'
const expandedBlocks = ref(new Set())
const expandedRows = ref(new Set())
const showNewBlockDialog = ref(false)
const newBlockName = ref('')

// Block editing state for seating blocks
const blockEditor = ref({
  blockId: null,
  rows: 10,
  seatsPerRow: 25,
  customRowSeats: {}
})

// Create empty grid with block assignments
const layoutGrid = computed(() => {
  const grid = Array(GRID_ROWS).fill(null).map(() => Array(GRID_COLS).fill(null))

  // Place stage blocks
  form.stageBlocks.forEach(stageBlock => {
    const x = stageBlock.position_x
    const y = stageBlock.position_y
    if (x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS) {
      grid[y][x] = { type: 'stage', ...stageBlock }
    }
  })

  // Place seating blocks
  form.blocks.forEach(block => {
    const x = block.position_x
    const y = block.position_y
    if (x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS) {
      grid[y][x] = { type: 'seating', ...block }
    }
  })

  return grid
})

// Get all blocks with their placement status
const allSeatingBlocks = computed(() => {
  return form.blocks.map(block => {
    const isPlaced = block.position_x >= 0 && block.position_x < GRID_COLS &&
                     block.position_y >= 0 && block.position_y < GRID_ROWS &&
                     block.position_x != null && block.position_y != null
    return {
      ...block,
      isPlaced,
      originalData: getOriginalSeatingBlock(block.id)
    }
  })
})

const allStageBlocks = computed(() => {
  return form.stageBlocks.map(stageBlock => {
    const isPlaced = stageBlock.position_x >= 0 && stageBlock.position_x < GRID_COLS &&
                     stageBlock.position_y >= 0 && stageBlock.position_y < GRID_ROWS &&
                     stageBlock.position_x != null && stageBlock.position_y != null
    return {
      ...stageBlock,
      isPlaced,
      originalData: getOriginalStageBlock(stageBlock.id)
    }
  })
})

// Get original block data for display
const getOriginalSeatingBlock = (blockId) => {
  return props.blocks.find(block => block.id === blockId)
}

const getOriginalStageBlock = (stageBlockId) => {
  return props.stageBlocks.find(block => block.id === stageBlockId)
}

// Get total seats from the optimized count
const getTotalSeats = (block) => {
  const originalBlock = getOriginalSeatingBlock(block.id)
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

// Block management functions
const toggleBlockExpanded = (blockId) => {
  if (expandedBlocks.value.has(blockId)) {
    expandedBlocks.value.delete(blockId)
  } else {
    expandedBlocks.value.add(blockId)
  }
}

const isBlockExpanded = (blockId) => {
  return expandedBlocks.value.has(blockId)
}

const selectBlock = (block, type) => {
  selectedBlock.value = block
  selectedBlockType.value = type
  
  if (type === 'seating') {
    const originalBlock = getOriginalSeatingBlock(block.id)
    expandedRows.value.clear()

    // Build custom row seats from existing data using the new custom_seat_count column
    const customRowSeats = {}
    let defaultSeatsPerRow = 25
    let actualRowCount = originalBlock?.rows_count || 10

    // If the block has rows data from database, load them
    if (originalBlock?.rows && originalBlock.rows.length > 0) {
      actualRowCount = originalBlock.rows.length
      
      // Calculate default from non-custom rows, or use most common if all are custom
      const nonCustomSeatCounts = []
      const allSeatCounts = []
      
      originalBlock.rows.forEach((row, index) => {
        const rowNumber = index + 1
        const seatCount = row.seats_count || 0
        const customSeatCount = row.custom_seat_count
        
        if (seatCount > 0) {
          allSeatCounts.push(seatCount)
          
          // If this row has a custom seat count, load it
          if (customSeatCount !== null && customSeatCount !== undefined) {
            customRowSeats[rowNumber] = customSeatCount
          } else {
            // This row uses the default
            nonCustomSeatCounts.push(seatCount)
          }
        }
      })

      // Calculate default seats per row
      if (nonCustomSeatCounts.length > 0) {
        // Use the most common non-custom seat count as default
        const countFrequency = {}
        nonCustomSeatCounts.forEach(count => {
          countFrequency[count] = (countFrequency[count] || 0) + 1
        })
        defaultSeatsPerRow = parseInt(Object.keys(countFrequency).reduce((a, b) => 
          countFrequency[a] > countFrequency[b] ? a : b
        ))
      } else if (allSeatCounts.length > 0) {
        // If all rows are custom, use the most common seat count as default
        const countFrequency = {}
        allSeatCounts.forEach(count => {
          countFrequency[count] = (countFrequency[count] || 0) + 1
        })
        defaultSeatsPerRow = parseInt(Object.keys(countFrequency).reduce((a, b) => 
          countFrequency[a] > countFrequency[b] ? a : b
        ))
      }
    }

    blockEditor.value = {
      blockId: block.id,
      rows: actualRowCount,
      seatsPerRow: defaultSeatsPerRow,
      customRowSeats: customRowSeats
    }
  }
}

const closeBlockEditor = () => {
  selectedBlock.value = null
  selectedBlockType.value = null
  expandedRows.value.clear()
}

// Grid cell click handlers
const handleCellClick = (rowIndex, colIndex) => {
  // Check if cell is empty
  if (layoutGrid.value[rowIndex][colIndex] === null) {
    // If we have a selected block, move it here
    if (selectedBlock.value && selectedBlockType.value) {
      if (selectedBlockType.value === 'stage') {
        updateStageBlockPosition(selectedBlock.value.id, colIndex, rowIndex)
      } else {
        updateSeatingBlockPosition(selectedBlock.value.id, colIndex, rowIndex)
      }
    }
  }
}

// Position management
const updateSeatingBlockPosition = (blockId, x, y) => {
  const blockIndex = form.blocks.findIndex(block => block.id === blockId)
  if (blockIndex !== -1) {
    form.blocks[blockIndex].position_x = x
    form.blocks[blockIndex].position_y = y
  }
}

const updateStageBlockPosition = (stageBlockId, x, y) => {
  const stageBlockIndex = form.stageBlocks.findIndex(block => block.id === stageBlockId)
  if (stageBlockIndex !== -1) {
    form.stageBlocks[stageBlockIndex].position_x = x
    form.stageBlocks[stageBlockIndex].position_y = y
  }
}

// Rotate seating block
const rotateBlock = (blockId) => {
  const blockIndex = form.blocks.findIndex(block => block.id === blockId)
  if (blockIndex !== -1) {
    const currentRotation = form.blocks[blockIndex].rotation
    form.blocks[blockIndex].rotation = (currentRotation + 90) % 360
  }
}

// Add new stage block
const addStageBlock = () => {
  const nextSort = form.stageBlocks.length
  form.stageBlocks.push({
    id: null, // Will be created on save
    name: `Stage ${nextSort + 1}`,
    position_x: -1,
    position_y: -1
  })
}

// Remove stage block
const removeStageBlock = (index) => {
  if (confirm('Are you sure you want to remove this stage block?')) {
    form.stageBlocks.splice(index, 1)
  }
}

// Delete seating block
const deleteSeatingBlock = (blockId) => {
  if (confirm('Are you sure you want to delete this block? This will permanently remove all rows and seats in this block.')) {
    const deleteForm = useForm({})
    
    deleteForm.delete(route('admin.rooms.blocks.delete', { room: props.room.id, block: blockId }), {
      preserveScroll: true,
      onSuccess: () => {
        // Remove the block from the form data after successful deletion
        const index = form.blocks.findIndex(b => b.id === blockId)
        if (index !== -1) {
          form.blocks.splice(index, 1)
        }
      }
    })
  }
}

// Save all changes
const saveLayout = () => {
  // Clean up custom row seats before saving
  if (blockEditor.value.blockId) {
    blockEditor.value.customRowSeats = cleanupCustomRowSeats()
  }
  
  // Include rows data for blocks that have been edited
  const blocksWithRowData = form.blocks.map(block => {
    const formattedBlock = { ...block }
    
    // If this block is currently being edited, include its row data
    if (blockEditor.value.blockId === block.id && blockEditor.value.rows > 0) {
      const rowsData = []
      for (let i = 1; i <= blockEditor.value.rows; i++) {
        const seatCount = getRowSeatCount(i)
        const isCustom = blockEditor.value.customRowSeats[i] !== undefined && 
                        blockEditor.value.customRowSeats[i] !== null && 
                        blockEditor.value.customRowSeats[i] !== ''
        
        rowsData.push({
          rowNumber: i,
          seatCount: seatCount,
          isCustom: isCustom
        })
      }
      formattedBlock.rowsData = rowsData
    }
    
    return formattedBlock
  })

  // Update the form data
  form.blocks = blocksWithRowData
  
  // Submit the form
  form.put(route('admin.rooms.layout.update', props.room.id))
}

// Create new seating block
const openNewBlockDialog = () => {
  showNewBlockDialog.value = true
  newBlockName.value = ''
}

const closeNewBlockDialog = () => {
  showNewBlockDialog.value = false
  newBlockName.value = ''
}

const createNewBlock = async () => {
  if (!newBlockName.value.trim()) return

  try {
    const response = await fetch(route('admin.rooms.blocks.create', props.room.id), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        name: newBlockName.value
      })
    })

    if (response.ok) {
      // Refresh the page to show the new block
      window.location.reload()
    } else {
      console.error('Failed to create block')
    }
  } catch (error) {
    console.error('Error creating block:', error)
  }
}

// Row management for seating blocks
const toggleRowExpanded = (rowIndex) => {
  if (expandedRows.value.has(rowIndex)) {
    expandedRows.value.delete(rowIndex)
  } else {
    expandedRows.value.add(rowIndex)
  }
}

const isRowExpanded = (rowIndex) => {
  return expandedRows.value.has(rowIndex)
}

const getRowSeatCount = (rowIndex) => {
  // Check if this row has a custom seat count set
  const customCount = blockEditor.value.customRowSeats[rowIndex]
  if (customCount !== undefined && customCount !== null && customCount !== '') {
    return parseInt(customCount) || blockEditor.value.seatsPerRow
  }
  return blockEditor.value.seatsPerRow
}

const calculateTotalSeats = () => {
  let total = 0
  for (let i = 1; i <= blockEditor.value.rows; i++) {
    total += getRowSeatCount(i)
  }
  return total
}

// Clean up custom row seats - remove entries that equal the default
const cleanupCustomRowSeats = () => {
  const cleanedCustomRowSeats = { ...blockEditor.value.customRowSeats }
  
  Object.keys(cleanedCustomRowSeats).forEach(rowIndex => {
    const customValue = cleanedCustomRowSeats[rowIndex]
    if (customValue === blockEditor.value.seatsPerRow || customValue === '' || customValue === null || customValue === undefined) {
      delete cleanedCustomRowSeats[rowIndex]
    }
  })
  
  return cleanedCustomRowSeats
}

// Clear custom seat count for a specific row (makes it use default)
const clearCustomRowSeat = (rowIndex) => {
  if (blockEditor.value.customRowSeats[rowIndex] !== undefined) {
    delete blockEditor.value.customRowSeats[rowIndex]
  }
}
</script>

<template>
  <Head :title="title" />

  <div>
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

      <!-- Left Column: Stage & Block Management -->
      <div class="lg:col-span-2 space-y-4">
        
        <!-- Stage Blocks Management -->
        <Card class="p-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold">🎭 Stage Blocks</h3>
            <Button size="sm" variant="outline" class="text-xs" @click="addStageBlock">
              + Add Stage
            </Button>
          </div>

          <div class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="(stageBlock, index) in allStageBlocks"
              :key="`stage-${stageBlock.id || index}`"
              class="border border-gray-200 rounded-lg p-3"
              :class="{ 'ring-2 ring-red-500': selectedBlock?.id === stageBlock.id && selectedBlockType === 'stage' }"
            >
              <div @click="selectBlock(stageBlock, 'stage')" class="cursor-pointer">
                <div class="flex items-center justify-between mb-2">
                  <div class="flex-1">
                    <Label class="text-xs">Stage Name</Label>
                    <Input
                      v-model="stageBlock.name"
                      class="text-sm mt-1"
                      @click.stop
                    />
                  </div>
                  <Button
                    size="sm"
                    variant="destructive"
                    @click.stop="removeStageBlock(index)"
                    class="text-xs px-2 py-1 ml-2"
                  >
                    🗑
                  </Button>
                </div>
                
                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <Label class="text-xs">X Position</Label>
                    <Input
                      v-model.number="stageBlock.position_x"
                      type="number"
                      min="-1"
                      :max="GRID_COLS - 1"
                      class="text-xs"
                      @click.stop
                    />
                  </div>
                  <div>
                    <Label class="text-xs">Y Position</Label>
                    <Input
                      v-model.number="stageBlock.position_y"
                      type="number"
                      min="-1"
                      :max="GRID_ROWS - 1"
                      class="text-xs"
                      @click.stop
                    />
                  </div>
                </div>
                
                <div class="text-xs text-gray-500 mt-2">
                  <span v-if="stageBlock.isPlaced" class="text-green-600">• Placed ({{ stageBlock.position_x }}, {{ stageBlock.position_y }})</span>
                  <span v-else class="text-orange-600">• Not placed</span>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Seating Blocks Management -->
        <Card class="p-4">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold">Seating Blocks</h3>
            <Button size="sm" variant="outline" class="text-xs" @click="openNewBlockDialog">
              + Add Block
            </Button>
          </div>

          <div class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="block in allSeatingBlocks"
              :key="block.id"
              class="border border-gray-200 rounded-lg"
              :class="{ 'ring-2 ring-blue-500': selectedBlock?.id === block.id && selectedBlockType === 'seating' }"
            >
              <!-- Block Header -->
              <div
                @click="selectBlock(block, 'seating')"
                class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50"
              >
                <div class="flex items-center space-x-3 flex-1">
                  <button
                    @click.stop="toggleBlockExpanded(block.id)"
                    class="text-gray-500 hover:text-gray-700"
                  >
                    <span v-if="isBlockExpanded(block.id)">▼</span>
                    <span v-else>▶</span>
                  </button>
                  <div class="flex-1">
                    <Label class="text-xs">Block Name</Label>
                    <Input
                      v-model="block.name"
                      class="text-sm mt-1"
                      @click.stop
                    />
                    <div class="text-xs text-gray-500 mt-1">
                      {{ getTotalSeats(block) }} seats • {{ block.originalData?.rows_count || 0 }} rows
                      <span v-if="block.isPlaced" class="text-green-600">• Placed ({{ block.position_x }}, {{ block.position_y }})</span>
                      <span v-else class="text-orange-600">• Not placed</span>
                    </div>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <span class="text-lg">{{ getOrientationArrow(block.rotation) }}</span>
                  <Button
                    size="sm"
                    variant="outline"
                    @click.stop="rotateBlock(block.id)"
                    class="text-xs px-2 py-1"
                  >
                    ↻
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    @click.stop="deleteSeatingBlock(block.id)"
                    class="text-xs px-2 py-1"
                  >
                    🗑
                  </Button>
                </div>
              </div>

              <!-- Block Details (Expandable) -->
              <div v-if="isBlockExpanded(block.id)" class="border-t bg-gray-50 p-3">
                <!-- Position Controls -->
                <div class="mb-3">
                  <Label class="text-xs">Position</Label>
                  <div class="grid grid-cols-2 gap-2 mt-1">
                    <Input
                      v-model.number="block.position_x"
                      type="number"
                      min="-1"
                      :max="GRID_COLS - 1"
                      placeholder="X"
                      class="text-xs"
                    />
                    <Input
                      v-model.number="block.position_y"
                      type="number"
                      min="-1"
                      :max="GRID_ROWS - 1"
                      placeholder="Y"
                      class="text-xs"
                    />
                  </div>
                </div>

                <!-- Row Configuration -->
                <div>
                  <Label class="text-xs">Rows</Label>
                  <div class="space-y-1 max-h-32 overflow-y-auto mt-1">
                    <div
                      v-for="(row, index) in block.originalData?.rows || []"
                      :key="row.id"
                      class="flex justify-between items-center py-1 px-2 bg-white rounded text-xs border"
                    >
                      <span class="font-medium">{{ row.name }}</span>
                      <span class="text-gray-500">{{ row.seats_count || 'Default' }} seats</span>
                    </div>
                    <div v-if="!block.originalData?.rows?.length" class="text-xs text-gray-500 italic py-2">
                      No rows configured
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Block Editor Card for Seating Blocks -->
        <Card v-if="selectedBlock && selectedBlockType === 'seating'" class="p-4">
          <h3 class="font-semibold mb-4 text-blue-600">Edit Block: {{ selectedBlock.name }}</h3>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <Label class="text-sm">Number of Rows</Label>
                <Input
                  v-model.number="blockEditor.rows"
                  type="number"
                  min="1"
                  max="50"
                  class="text-sm mt-1"
                />
              </div>

              <div>
                <Label class="text-sm">Default Seats/Row</Label>
                <Input
                  v-model.number="blockEditor.seatsPerRow"
                  type="number"
                  min="1"
                  max="100"
                  class="text-sm mt-1"
                />
              </div>
            </div>

            <!-- Row Customization -->
            <div class="space-y-2">
              <h4 class="font-medium text-sm">Row Configuration</h4>
              <div class="max-h-60 overflow-y-auto space-y-2">
                <div
                  v-for="rowIndex in blockEditor.rows"
                  :key="rowIndex"
                  class="border border-gray-200 rounded"
                >
                  <button
                    @click="toggleRowExpanded(rowIndex)"
                    class="w-full px-3 py-2 text-left text-sm font-medium bg-gray-50 hover:bg-gray-100 flex justify-between items-center"
                  >
                    <span>Row {{ rowIndex }}</span>
                    <span class="text-xs flex items-center gap-1">
                      {{ getRowSeatCount(rowIndex) }} seats
                      <span v-if="blockEditor.customRowSeats[rowIndex]" class="text-blue-600 font-bold" title="Custom seat count">●</span>
                    </span>
                  </button>

                  <div v-if="isRowExpanded(rowIndex)" class="p-3 border-t">
                    <Label class="text-xs">Custom Seat Count</Label>
                    <div class="flex gap-2 mt-1">
                      <Input
                        v-model.number="blockEditor.customRowSeats[rowIndex]"
                        type="number"
                        min="1"
                        max="100"
                        :placeholder="`Default: ${blockEditor.seatsPerRow}`"
                        class="text-sm flex-1"
                      />
                      <Button
                        v-if="blockEditor.customRowSeats[rowIndex] !== undefined"
                        variant="outline"
                        size="sm"
                        @click="clearCustomRowSeat(rowIndex)"
                        class="text-xs px-2"
                        title="Use default"
                      >
                        Reset
                      </Button>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      <span v-if="blockEditor.customRowSeats[rowIndex]">
                        Using custom count: {{ blockEditor.customRowSeats[rowIndex] }}
                      </span>
                      <span v-else>
                        Using default: {{ blockEditor.seatsPerRow }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="text-sm text-gray-600 bg-gray-50 p-2 rounded">
              Total seats: {{ calculateTotalSeats() }}
            </div>

            <Button variant="outline" size="sm" @click="closeBlockEditor">
              Close Editor
            </Button>
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
              Click empty cells to place selected blocks • Multiple stages supported
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
                      'bg-blue-50': cell?.type === 'seating'
                    }"
                    @click="handleCellClick(rowIndex, colIndex)"
                  >
                    <!-- Empty Cell -->
                    <div v-if="cell === null" class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                      {{ rowIndex }},{{ colIndex }}
                    </div>

                    <!-- Stage Cell -->
                    <div
                      v-else-if="cell.type === 'stage'"
                      class="w-full h-full bg-red-600 text-white border border-red-700 rounded cursor-pointer flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow"
                      @click.stop="selectBlock(cell, 'stage')"
                      :class="{ 'ring-2 ring-red-300': selectedBlock?.id === cell.id && selectedBlockType === 'stage' }"
                    >
                      <div class="font-bold text-sm">🎭</div>
                      <div class="font-medium text-xs">{{ cell.name }}</div>
                    </div>

                    <!-- Seating Block Cell -->
                    <div
                      v-else-if="cell.type === 'seating'"
                      @click.stop="selectBlock(cell, 'seating')"
                      class="w-full h-full bg-white border border-gray-400 rounded cursor-pointer flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow"
                      :class="{ 'ring-2 ring-blue-500 bg-blue-50': selectedBlock?.id === cell.id && selectedBlockType === 'seating' }"
                    >
                      <!-- Block Title -->
                      <div class="font-medium text-xs mb-1 px-1">{{ cell.name }}</div>

                      <!-- Orientation Arrow -->
                      <div class="text-lg mb-1">{{ getOrientationArrow(cell.rotation) }}</div>

                      <!-- Seat Count -->
                      <div class="text-xs text-gray-600">{{ getTotalSeats(cell) }} seats</div>
                      <div class="text-xs text-gray-500">{{ getOriginalSeatingBlock(cell.id)?.rows_count || 0 }} rows</div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Grid Legend -->
          <div class="mt-4 text-xs text-gray-500 text-center">
            Grid coordinates: Row,Column • 🎭 Red = Stages • Blue = Seating Blocks • Click empty cells to place selected blocks
          </div>
        </Card>

        <!-- Single Save Button -->
        <div class="mt-6 flex justify-center">
          <Button
            @click="saveLayout"
            :disabled="form.processing"
            class="bg-green-600 hover:bg-green-700 text-white px-12 py-3"
            size="lg"
          >
            <span v-if="form.processing">Saving...</span>
            <span v-else>Save Entire Layout</span>
          </Button>
        </div>
      </div>
    </div>
  </div>

  <!-- New Block Dialog -->
  <div v-if="showNewBlockDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
      <h3 class="text-lg font-semibold mb-4">Create New Seating Block</h3>

      <div class="mb-4">
        <Label class="text-sm">Block Name</Label>
        <Input
          v-model="newBlockName"
          type="text"
          placeholder="Enter block name..."
          class="mt-1"
          @keyup.enter="createNewBlock"
        />
      </div>

      <div class="text-sm text-gray-600 mb-4">
        The new block will be placed at position (1, 1) by default.
      </div>

      <div class="flex justify-end space-x-3">
        <Button variant="outline" @click="closeNewBlockDialog">
          Cancel
        </Button>
        <Button
          @click="createNewBlock"
          :disabled="!newBlockName.trim()"
          class="bg-blue-600 hover:bg-blue-700 text-white"
        >
          Create Block
        </Button>
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