<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'

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

// Helper functions that need to be available during initialization
const getOriginalSeatingBlock = (blockId) => {
  return props.blocks.find(block => block.id === blockId)
}

const getOriginalStageBlock = (stageBlockId) => {
  return props.stageBlocks.find(block => block.id === stageBlockId)
}

// Build complete form data from props with all editing capabilities
const form = useForm({
  stageBlocks: props.stageBlocks.map(block => ({
    id: block.id,
    name: block.name,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1
  })),
  blocks: props.blocks.map(block => {
    const originalBlock = getOriginalSeatingBlock(block.id)

    // Build row data for each block
    const rows = []
    const rowCount = originalBlock?.rows?.length || 10

    for (let i = 1; i <= rowCount; i++) {
      const existingRow = originalBlock?.rows?.find((r, idx) => idx + 1 === i)
      rows.push({
        rowNumber: i,
        seatCount: existingRow?.seats_count || 25,
        isCustom: existingRow?.custom_seat_count !== null && existingRow?.custom_seat_count !== undefined,
        alignment: existingRow?.alignment || 'center'
      })
    }

    return {
      id: block.id,
      name: block.name,
      position_x: block.position_x != null ? block.position_x : -1,
      position_y: block.position_y != null ? block.position_y : -1,
      rotation: block.rotation || 0,
      rowCount: rowCount,
      defaultSeatsPerRow: 25,
      rows: rows,
      originalData: originalBlock
    }
  })
})

// UI State
const selectedBlock = ref(null)
const selectedBlockType = ref(null) // 'stage' or 'seating'
const expandedBlocks = ref(new Set())
const expandedRows = ref(new Set())
const showNewBlockDialog = ref(false)
const newBlockName = ref('')


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

// Helper function to check if block is placed
const getBlockPlacementStatus = (block) => {
  return block.position_x >= 0 && block.position_x < GRID_COLS &&
         block.position_y >= 0 && block.position_y < GRID_ROWS &&
         block.position_x != null && block.position_y != null
}



// Get total seats from the block's row data
const getTotalSeats = (block) => {
  if (!block.rows || block.rows.length === 0) return 0
  return block.rows.reduce((total, row) => total + (row.seatCount || 0), 0)
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

// Track form submission state
const isSubmitting = ref(false)

// Save all changes
const saveLayout = () => {
  isSubmitting.value = true
  // Build form data with row data for blocks that have rows configured
  const formData = {
    stageBlocks: form.stageBlocks.map(stageBlock => ({
      id: stageBlock.id,
      name: stageBlock.name,
      position_x: stageBlock.position_x,
      position_y: stageBlock.position_y
    })),
    blocks: form.blocks.map(block => {
      const formattedBlock = {
        id: block.id,
        name: block.name,
        position_x: block.position_x,
        position_y: block.position_y,
        rotation: block.rotation
      }

      // Include row data if block has rows configured
      if (block.rows && block.rows.length > 0) {
        formattedBlock.rowsData = block.rows.map(row => ({
          rowNumber: row.rowNumber,
          seatCount: row.seatCount,
          isCustom: row.isCustom,
          alignment: row.alignment
        }))
      }

      return formattedBlock
    })
  }

  console.log('Submitting form data:', formData)

  // Create a new form instance with the data and submit
  const submitForm = useForm(formData)
  submitForm.put(route('admin.rooms.layout.update', props.room.id), {
    onSuccess: () => {
      isSubmitting.value = false
    },
    onError: () => {
      isSubmitting.value = false
    }
  })
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

const createNewBlock = () => {
  if (!newBlockName.value.trim()) return

  const createForm = useForm({
    name: newBlockName.value
  })

  createForm.post(route('admin.rooms.blocks.create', props.room.id), {
    onSuccess: () => {
      // Add the new block to the form data instead of refreshing
      const newBlock = {
        id: Date.now(), // Temporary ID until page refresh
        name: newBlockName.value,
        position_x: -1,
        position_y: -1,
        rotation: 0,
        rowCount: 10,
        defaultSeatsPerRow: 25,
        rows: Array.from({ length: 10 }, (_, i) => ({
          rowNumber: i + 1,
          seatCount: 25,
          isCustom: false,
          alignment: 'center'
        }))
      }
      form.blocks.push(newBlock)
      closeNewBlockDialog()
    },
    onError: (errors) => {
      console.error('Failed to create block:', errors)
    }
  })
}

// Row management for seating blocks
const toggleRowExpanded = (rowIndex) => {
  if (expandedRows.value.has(rowIndex)) {
    expandedRows.value.delete(rowIndex)
  } else {
    expandedRows.value.add(rowIndex)
  }
}

// Row management functions
const addRow = (block) => {
  const newRowNumber = block.rows.length + 1
  block.rows.push({
    rowNumber: newRowNumber,
    seatCount: 25, // Default seat count
    isCustom: false,
    alignment: 'center'
  })
}

const removeRow = (block) => {
  if (block.rows.length > 1) {
    block.rows.pop()
  }
}

const removeSpecificRow = (block, rowNumber) => {
  if (block.rows.length > 1) {
    // Remove the specific row
    const index = block.rows.findIndex(row => row.rowNumber === rowNumber)
    if (index !== -1) {
      block.rows.splice(index, 1)

      // Renumber all remaining rows to maintain sequential order
      block.rows.forEach((row, idx) => {
        row.rowNumber = idx + 1
      })
    }
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
              v-for="(stageBlock, index) in form.stageBlocks"
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

                <div class="text-xs text-gray-500 mt-2">
                  <span v-if="getBlockPlacementStatus(stageBlock)" class="text-green-600">• Placed ({{ stageBlock.position_x }}, {{ stageBlock.position_y }})</span>
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

          <div class="space-y-2 overflow-y-auto">
            <div
              v-for="block in form.blocks"
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
                      {{ getTotalSeats(block) }} seats • {{ block.rows.length }} rows
                      <span v-if="getBlockPlacementStatus(block)" class="text-green-600">• Placed ({{ block.position_x }}, {{ block.position_y }})</span>
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

                <!-- Row Configuration -->
                <div>
                  <div class="flex justify-between items-center mb-2">
                    <Label class="text-xs">Row Configuration ({{ block.rows.length }} rows)</Label>
                    <div class="flex gap-1">
                      <Button
                        size="sm"
                        variant="outline"
                        @click="addRow(block)"
                        class="text-xs px-2 py-1"
                        title="Add row"
                      >
                        + Row
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        @click="removeRow(block)"
                        :disabled="block.rows.length <= 1"
                        class="text-xs px-2 py-1"
                        title="Remove last row"
                      >
                        - Row
                      </Button>
                    </div>
                  </div>
                  <div class="space-y-1 max-h-60 overflow-y-auto">
                    <div
                      v-for="row in block.rows"
                      :key="row.rowNumber"
                      class="flex items-center gap-2 px-3 py-2 bg-white border border-gray-200 rounded text-xs"
                    >
                      <!-- Row Number -->
                      <span class="font-medium w-12">Row {{ row.rowNumber }}</span>
                      
                      <!-- Seat Count Input -->
                      <Input
                        v-model.number="row.seatCount"
                        type="number"
                        min="1"
                        max="100"
                        class="text-xs h-7 w-16"
                      />
                      
                      <!-- Alignment Select -->
                      <select
                        v-model="row.alignment"
                        class="text-xs h-7 border border-gray-300 rounded px-2 flex-1"
                      >
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                      </select>
                      
                      <!-- Seat Count Display -->
                      <span class="text-gray-500 w-16">{{ row.seatCount }} seats</span>
                      
                      <!-- Delete Button -->
                      <Button
                        size="sm"
                        variant="ghost"
                        @click="removeSpecificRow(block, row.rowNumber)"
                        :disabled="block.rows.length <= 1"
                        class="text-xs px-1 py-0 h-6 w-6 text-red-600 hover:bg-red-50 flex-shrink-0"
                        title="Delete this row"
                      >
                        ×
                      </Button>
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
                    <div class="grid grid-cols-2 gap-4">
                      <!-- Custom Seat Count -->
                      <div>
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

                      <!-- Seat Alignment -->
                      <div>
                        <Label class="text-xs">Seat Alignment</Label>
                        <Select
                          :value="blockEditor.rowAlignments[rowIndex] || 'center'"
                          @update:value="(value) => updateRowAlignment(rowIndex, value)"
                          class="mt-1 w-full"
                        >
                          <SelectTrigger class="text-sm">
                            <SelectValue>{{ blockEditor.rowAlignments[rowIndex] }}</SelectValue>
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="left">Left</SelectItem>
                            <SelectItem value="center">Center</SelectItem>
                            <SelectItem value="right">Right</SelectItem>
                          </SelectContent>
                        </Select>
                        <div class="text-xs text-gray-500 mt-1">
                          Controls how seats are aligned within this row
                        </div>
                      </div>
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
                      <div class="text-xs text-gray-500">{{ cell.rows?.length || 0 }} rows</div>
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
            :disabled="isSubmitting"
            class="bg-green-600 hover:bg-green-700 text-white px-12 py-3"
            size="lg"
          >
            <span v-if="isSubmitting">Saving...</span>
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
