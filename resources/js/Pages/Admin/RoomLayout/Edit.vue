<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { useToast } from '@/Components/ui/toast'
import { Trash2, RotateCw, ArrowUp, ArrowRight, ArrowDown, ArrowLeft, TriangleAlert } from 'lucide-vue-next'

defineOptions({ layout: AdminLayout })

const DEFAULT_SEAT_COUNT = 25

const { error: errorToast } = useToast()

const props = defineProps({
  room: Object,
  bookingsCount: {
    type: Number,
    default: 0
  },
  blocks: Array,
  stageBlocks: Array,
  markerBlocks: { type: Array, default: () => [] },
  blockIdMap: { type: Object, default: () => ({}) },
  title: String,
  breadcrumbs: Array
})

let tempBlockCounter = 0

const GRID_ROWS = 8
const GRID_COLS = 12

// Shared skeleton for a placed cell in the layout grid; type-specific colors/rings are added per branch.
const CELL_BASE = 'w-full h-full border rounded cursor-pointer flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow'

const getOriginalSeatingBlock = (blockId) => {
  return props.blocks.find(block => block.id === blockId)
}

// Build complete form data
const form = useForm({
  stageBlocks: props.stageBlocks.map(block => ({
    id: block.id,
    name: block.name,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1
  })),
  markerBlocks: props.markerBlocks.map(block => ({
    id: block.id,
    type: block.type,
    name: block.name,
    position_x: block.position_x != null ? block.position_x : -1,
    position_y: block.position_y != null ? block.position_y : -1,
    orientation: block.type === 'entrance' && block.position_y === -1 ? 'column' : 'row',
    rotation: block.rotation || 0
  })),
  blocks: props.blocks.map(block => {
    const originalBlock = getOriginalSeatingBlock(block.id)

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
const selectedBlockType = ref(null) // 'stage' or 'seating' or 'comment' or 'entrance'
const expandedBlocks = ref(new Set())
const showNewBlockDialog = ref(false)
const newBlockName = ref('')

// Confirmation dialog shown before saving when the room has bookings, since saving
// rebuilds the seat layout and deletes all existing bookings for this room.
//
// TEMPORARY: this whole warn-and-confirm flow is a stopgap. Editing the layout currently
// wipes all bookings for the room; remove this banner + dialog once layout edits preserve
// existing bookings. See RoomLayoutController::edit/update.
const showDeleteBookingsDialog = ref(false)
const deleteBookingsConfirmText = ref('')
const DELETE_BOOKINGS_PHRASE = 'remove bookings'
const hasBookings = computed(() => props.bookingsCount > 0)
const canConfirmDelete = computed(
  () => deleteBookingsConfirmText.value.trim().toLowerCase() === DELETE_BOOKINGS_PHRASE
)


const markerOrientation = (marker) => (marker.position_y === -1 ? 'column' : 'row')

const markerCells = (marker) => {
  const { position_x: x, position_y: y } = marker
  if (marker.type === 'comment') return [[y, x]]
  if (markerOrientation(marker) === 'column') {
    return Array.from({ length: GRID_ROWS }, (_, r) => [r, x])
  }
  return Array.from({ length: GRID_COLS }, (_, c) => [y, c])
}

const layoutGrid = computed(() => {
  const grid = Array(GRID_ROWS).fill(null).map(() => Array(GRID_COLS).fill(null))

  form.stageBlocks.forEach(stageBlock => {
    const x = stageBlock.position_x
    const y = stageBlock.position_y
    if (x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS) {
      grid[y][x] = { type: 'stage', ...stageBlock }
    }
  })

  form.blocks.forEach(block => {
    const x = block.position_x
    const y = block.position_y
    if (x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS) {
      grid[y][x] = { type: 'seating', ...block }
    }
  })

  form.markerBlocks.forEach((marker, index) => {
    const cell = { ...marker, markerIndex: index, orientation: markerOrientation(marker) }
    for (const [y, x] of markerCells(marker)) {
      if (grid[y]?.[x] === null) grid[y][x] = cell
    }
  })

  return grid
})

// Placed blocks always sit inside the fixed grid; -1 on either axis means unplaced.
const getBlockPlacementStatus = (block) => block.position_x >= 0 && block.position_y >= 0

const getTotalSeats = (block) => {
  if (!block.rows || block.rows.length === 0) return 0
  return block.rows.reduce((total, row) => total + (row.seatCount || 0), 0)
}

const getOrientationArrow = (rotation) => {
  const arrows = {
    0: ArrowUp,
    90: ArrowRight,
    180: ArrowDown,
    270: ArrowLeft
  }
  return arrows[rotation] || ArrowUp
}

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

const handleCellClick = (rowIndex, colIndex) => {
  const block = selectedBlock.value
  const type = selectedBlockType.value
  if (!block || !type) return

  if (type === 'marker' && block.type === 'entrance') {
    return placeMarker(block.markerIndex, rowIndex, colIndex)
  }
  if (layoutGrid.value[rowIndex][colIndex] !== null) return

  if (type === 'stage') setPosition(form.stageBlocks, block.id, colIndex, rowIndex)
  else if (type === 'marker') placeMarker(block.markerIndex, rowIndex, colIndex)
  else setPosition(form.blocks, block.id, colIndex, rowIndex)
}

const setPosition = (list, id, x, y) => {
  const target = list.find(b => b.id === id)
  if (target) Object.assign(target, { position_x: x, position_y: y })
}

const placeMarker = (markerIndex, rowIndex, colIndex) => {
  const marker = form.markerBlocks[markerIndex]
  if (!marker) return
  if (marker.type === 'comment') Object.assign(marker, { position_x: colIndex, position_y: rowIndex })
  else if (marker.orientation === 'column') Object.assign(marker, { position_x: colIndex, position_y: -1 })
  else Object.assign(marker, { position_x: -1, position_y: rowIndex })
}

const rotateBlock = (blockId) => {
  const block = form.blocks.find(b => b.id === blockId)
  if (block) block.rotation = (block.rotation + 90) % 360
}

const addStageBlock = () => {
  form.stageBlocks.push({
    id: null,
    name: `Stage ${form.stageBlocks.length + 1}`,
    position_x: -1,
    position_y: -1
  })
}

const removeStageBlock = (index) => {
  if (confirm('Are you sure you want to remove this stage block?')) {
    form.stageBlocks.splice(index, 1)
  }
}

const ENTRANCE_DIRECTIONS = {
  row: [180, 0],
  column: [90, 270]
}

const addMarker = (type) => {
  const base = { id: null, type, position_x: -1, position_y: -1 }
  if (type === 'comment') {
    form.markerBlocks.push({ ...base, name: 'Note' })
    return
  }
  const count = form.markerBlocks.filter(m => m.type === 'entrance').length
  form.markerBlocks.push({
    ...base,
    name: count === 0 ? 'Entrance' : `Entrance ${count + 1}`,
    orientation: 'row',
    rotation: ENTRANCE_DIRECTIONS.row[0]
  })
}

const setEntranceOrientation = (marker, orientation) => {
  Object.assign(marker, {
    orientation,
    rotation: ENTRANCE_DIRECTIONS[orientation][0],
    position_x: -1,
    position_y: -1
  })
}

const rotateEntrance = (marker) => {
  const directions = ENTRANCE_DIRECTIONS[marker.orientation]
  const current = directions.indexOf(marker.rotation)
  marker.rotation = directions[(current + 1) % directions.length]
}

const removeMarkerBlock = (index) => {
  if (confirm('Are you sure you want to remove this marker?')) {
    if (selectedBlockType.value === 'marker' && selectedBlock.value?.markerIndex === index) {
      selectedBlock.value = null
      selectedBlockType.value = null
    }
    form.markerBlocks.splice(index, 1)
  }
}

const getMarkerPlacementStatus = (marker) => {
  if (marker.type === 'comment') return getBlockPlacementStatus(marker)
  return marker.orientation === 'column' ? marker.position_x >= 0 : marker.position_y >= 0
}

const deleteSeatingBlock = (blockId) => {
  if (confirm('Are you sure you want to delete this block? This will permanently remove all rows and seats in this block.')) {
    const deleteForm = useForm({})

    deleteForm.delete(route('admin.rooms.blocks.delete', { room: props.room.id, block: blockId }), {
      preserveScroll: true,
      onSuccess: () => {
        const index = form.blocks.findIndex(b => b.id === blockId)
        if (index !== -1) {
          form.blocks.splice(index, 1)
        }
      }
    })
  }
}

const isSubmitting = ref(false)

const requestSaveLayout = () => {
  if (hasBookings.value) {
    deleteBookingsConfirmText.value = ''
    showDeleteBookingsDialog.value = true
    return
  }
  saveLayout()
}

const confirmDeleteBookingsAndSave = () => {
  if (!canConfirmDelete.value) return
  showDeleteBookingsDialog.value = false
  saveLayout()
}

const saveLayout = () => {
  isSubmitting.value = true
  // Build form data
  const formData = {
    stageBlocks: form.stageBlocks.map(stageBlock => ({
      id: stageBlock.id,
      name: stageBlock.name,
      position_x: stageBlock.position_x,
      position_y: stageBlock.position_y
    })),
    markerBlocks: form.markerBlocks.map(marker => ({
      id: marker.id,
      type: marker.type,
      name: marker.name,
      position_x: marker.position_x,
      position_y: marker.position_y,
      rotation: marker.rotation ?? 0
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

  // Create a new form instance with the data and submit
  const submitForm = useForm(formData)
  submitForm.put(route('admin.rooms.layout.update', props.room.id), {
    onSuccess: () => {
      isSubmitting.value = false
      const map = props.blockIdMap || {}
      form.blocks.forEach(block => {
        if (map[block.id] != null) {
          block.id = map[block.id]
        }
      })
    },
    onError: (errors) => {
      isSubmitting.value = false
      const messages = Object.values(errors)
      errorToast(
        'Layout could not be saved',
        messages.length ? messages.join('\n') : 'Please reload the page or try again.'
      )
    }
  })
}

// Create new seating block
const newBlockNameInput = ref(null)

const openNewBlockDialog = () => {
  showNewBlockDialog.value = true
  newBlockName.value = ''
  nextTick(() => newBlockNameInput.value?.focus())
}

const closeNewBlockDialog = () => {
  showNewBlockDialog.value = false
  newBlockName.value = ''
}

const findFirstFreeCell = () => {
  const grid = layoutGrid.value
  for (let y = 0; y < GRID_ROWS; y++) {
    for (let x = 0; x < GRID_COLS; x++) {
      if (grid[y][x] === null) {
        return { x, y }
      }
    }
  }
  return { x: -1, y: -1 }
}

const nextFreeCell = computed(() => findFirstFreeCell())

const createNewBlock = () => {
  const name = newBlockName.value.trim()
  if (!name) return

  const { x, y } = nextFreeCell.value

  form.blocks.push({
    id: `temp-${++tempBlockCounter}`,
    name,
    position_x: x,
    position_y: y,
    rotation: 0,
    rowCount: 10,
    defaultSeatsPerRow: DEFAULT_SEAT_COUNT,
    rows: Array.from({ length: 10 }, (_, i) => ({
      rowNumber: i + 1,
      seatCount: DEFAULT_SEAT_COUNT,
      isCustom: false,
      alignment: 'center'
    }))
  })

  closeNewBlockDialog()
}

const addRow = (block) => {
  const newRowNumber = block.rows.length + 1
  block.rows.push({
    rowNumber: newRowNumber,
    seatCount: DEFAULT_SEAT_COUNT,
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
    const index = block.rows.findIndex(row => row.rowNumber === rowNumber)
    if (index !== -1) {
      block.rows.splice(index, 1)

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
    <!-- Warning banner: shown only when this room has bookings, since saving the layout
         rebuilds the seats and deletes every booking for this room. -->
    <div
      v-if="hasBookings"
      class="mb-6 flex items-start gap-3 rounded-md border border-red-300 bg-red-50 p-4 text-red-800"
    >
      <TriangleAlert class="w-5 h-5 flex-shrink-0 mt-0.5" />
      <div class="text-sm">
        <p class="font-semibold">Saving will delete all bookings for this room.</p>
        <p class="mt-1">
          This room has {{ bookingsCount }} booking{{ bookingsCount === 1 ? '' : 's' }}.
          Editing the room layout removes every existing booking associated with it. This
          action cannot be undone.
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

      <!-- Left Column: Stage & Block Management -->
      <div class="lg:col-span-2 space-y-4">

        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center mb-2">
            <h3 class="font-semibold">Stage Blocks</h3>
            <Button size="sm" variant="outline" class="text-xs" @click="addStageBlock">
              + Add Stage
            </Button>
          </div>

          <div class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="(stageBlock, index) in form.stageBlocks"
              :key="`stage-${stageBlock.id || index}`"
              class="border border-gray-200 rounded-lg p-3"
              :class="{ 'ring-2 ring-inset ring-red-500': selectedBlock?.id === stageBlock.id && selectedBlockType === 'stage' }"
            >
              <div @click="selectBlock(stageBlock, 'stage')" class="cursor-pointer">
                <div class="flex items-end justify-between mb-2">
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
                    title="Remove stage"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>

                <div class="text-xs text-gray-500 mt-2">
                  <span v-if="getBlockPlacementStatus(stageBlock)" class="text-green-600">• Placed ({{ stageBlock.position_y }}, {{ stageBlock.position_x }})</span>
                  <span v-else class="text-orange-600">• Not placed</span>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Marker Blocks Management -->
        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center mb-2">
            <h3 class="font-semibold">Markers</h3>
            <div class="flex gap-1">
              <Button size="sm" variant="outline" class="text-xs" @click="addMarker('entrance')">
                + Entrance
              </Button>
              <Button size="sm" variant="outline" class="text-xs" @click="addMarker('comment')">
                + Comment
              </Button>
            </div>
          </div>

          <div class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="(marker, index) in form.markerBlocks"
              :key="`marker-${marker.id || 'new'}-${index}`"
              class="border border-gray-200 rounded-lg p-3"
              :class="{ 'ring-2 ring-inset ring-amber-500': selectedBlockType === 'marker' && selectedBlock?.markerIndex === index }"
              @click="selectBlock({ ...marker, markerIndex: index }, 'marker')"
            >
              <div class="flex items-end justify-between mb-2">
                <div class="flex-1">
                  <Label class="text-xs">
                    {{ marker.type === 'entrance' ? 'Entrance label' : 'Comment text' }}
                  </Label>
                  <Input
                    v-model="marker.name"
                    class="text-sm mt-1"
                    @click.stop
                  />
                </div>
                <Button
                  size="sm"
                  variant="destructive"
                  @click.stop="removeMarkerBlock(index)"
                  class="text-xs px-2 py-1 ml-2"
                  title="Remove marker"
                >
                  <Trash2 class="w-4 h-4" />
                </Button>
              </div>

              <div v-if="marker.type === 'entrance'" class="space-y-2 mb-2">
                <div class="flex items-center gap-2">
                  <Label class="text-xs w-16">Spans</Label>
                  <select
                    :value="marker.orientation"
                    @click.stop
                    @change="setEntranceOrientation(marker, $event.target.value)"
                    class="text-xs h-7 border border-gray-300 rounded px-2 flex-1"
                  >
                    <option value="row">Full row (horizontal)</option>
                    <option value="column">Full column (vertical)</option>
                  </select>
                </div>
                <div class="flex items-center gap-2">
                  <Label class="text-xs w-16">Direction</Label>
                  <component :is="getOrientationArrow(marker.rotation)" class="w-5 h-5" />
                  <Button
                    size="sm"
                    variant="outline"
                    @click.stop="rotateEntrance(marker)"
                    class="text-xs px-2 py-1"
                    title="Rotate direction"
                  >
                    <RotateCw class="w-4 h-4" />
                  </Button>
                </div>
              </div>

              <div class="text-xs text-gray-500">
                <span v-if="getMarkerPlacementStatus(marker)" class="text-green-600">
                  <template v-if="marker.type === 'comment'">• Placed ({{ marker.position_y }}, {{ marker.position_x }})</template>
                  <template v-else-if="marker.orientation === 'column'">• Column {{ marker.position_x }}</template>
                  <template v-else>• Row {{ marker.position_y }}</template>
                </span>
                <span v-else class="text-orange-600">• Not placed</span>
              </div>
            </div>
            <p v-if="form.markerBlocks.length === 0" class="text-xs text-gray-400">
              No markers yet. Add an entrance row/column or a comment note.
            </p>
          </div>
        </Card>

        <!-- Seating Blocks Management -->
        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center mb-2">
            <h3 class="font-semibold">Seating Blocks</h3>
            <Button size="sm" variant="outline" class="text-xs" @click="openNewBlockDialog">
              + Add Block
            </Button>
          </div>

          <div class="space-y-2 overflow-y-auto">
            <div
              v-for="block in form.blocks"
              :key="block.id"
              class="border border-gray-200 rounded-lg overflow-hidden"
              :class="{ 'ring-2 ring-inset ring-blue-500': selectedBlock?.id === block.id && selectedBlockType === 'seating' }"
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
                      <span v-if="getBlockPlacementStatus(block)" class="text-green-600">• Placed ({{ block.position_y }}, {{ block.position_x }})</span>
                      <span v-else class="text-orange-600">• Not placed</span>
                    </div>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <component :is="getOrientationArrow(block.rotation)" class="w-5 h-5" />
                  <Button
                    size="sm"
                    variant="outline"
                    @click.stop="rotateBlock(block.id)"
                    class="text-xs px-2 py-1"
                    title="Rotate block"
                  >
                    <RotateCw class="w-4 h-4" />
                  </Button>
                  <Button
                    size="sm"
                    variant="destructive"
                    @click.stop="deleteSeatingBlock(block.id)"
                    class="text-xs px-2 py-1"
                    title="Delete block"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>
              </div>

              <div v-if="isBlockExpanded(block.id)" class="border-t bg-gray-50 p-3">
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
                    <span class="font-medium w-12">Row {{ row.rowNumber }}</span>

                    <Input
                      v-model.number="row.seatCount"
                      type="number"
                      min="1"
                      max="100"
                      class="text-xs h-7 w-16"
                    />

                    <select
                      v-model="row.alignment"
                      class="text-xs h-7 border border-gray-300 rounded px-2 flex-1"
                    >
                      <option value="left">Left</option>
                      <option value="center">Center</option>
                      <option value="right">Right</option>
                    </select>

                    <span class="text-gray-500 w-16">{{ row.seatCount }} seats</span>

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
        </Card>
      </div>

      <!-- Main Layout Table -->
      <div class="lg:col-span-3">
        <Card class="p-6 gap-0">
          <div class="mb-4">
            <h3 class="font-semibold text-center text-lg mb-2">
              Room Layout Grid
            </h3>
            <div class="text-center text-sm text-gray-600 mb-4">
              Click empty cells to place selected blocks
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
                      'bg-blue-50': cell?.type === 'seating',
                      'bg-amber-50': cell?.type === 'entrance',
                      'bg-yellow-50': cell?.type === 'comment'
                    }"
                    @click="handleCellClick(rowIndex, colIndex)"
                  >
                    <div v-if="cell === null" class="w-full h-full flex items-center justify-center text-gray-400 text-xs">
                      {{ rowIndex }},{{ colIndex }}
                    </div>

                    <div
                      v-else-if="cell.type === 'stage'"
                      :class="[CELL_BASE, 'bg-red-600 text-white border-red-700', { 'ring-2 ring-red-300': selectedBlock?.id === cell.id && selectedBlockType === 'stage' }]"
                      @click.stop="selectBlock(cell, 'stage')"
                    >
                      <div class="font-medium text-xs">{{ cell.name }}</div>
                    </div>

                    <div
                      v-else-if="cell.type === 'seating'"
                      :class="[CELL_BASE, 'bg-white border-gray-400', { 'ring-2 ring-blue-500 bg-blue-50': selectedBlock?.id === cell.id && selectedBlockType === 'seating' }]"
                      @click.stop="selectBlock(cell, 'seating')"
                    >
                      <div class="font-medium text-xs mb-1 px-1">{{ cell.name }}</div>

                      <component :is="getOrientationArrow(cell.rotation)" class="w-5 h-5 mb-1" />

                      <div class="text-xs text-gray-600">{{ getTotalSeats(cell) }} seats</div>
                      <div class="text-xs text-gray-500">{{ cell.rows?.length || 0 }} rows</div>
                    </div>

                    <div
                      v-else-if="cell.type === 'entrance'"
                      :class="[CELL_BASE, 'bg-amber-500 text-white border-amber-600', { 'ring-2 ring-amber-300': selectedBlockType === 'marker' && selectedBlock?.markerIndex === cell.markerIndex }]"
                      @click.stop="selectBlock({ ...cell }, 'marker')"
                    >
                      <component :is="getOrientationArrow(cell.rotation)" class="w-4 h-4" />
                      <div class="font-medium text-xs px-1">{{ cell.name }}</div>
                    </div>

                    <div
                      v-else-if="cell.type === 'comment'"
                      :class="[CELL_BASE, 'bg-yellow-100 text-yellow-900 border-yellow-400 p-1', { 'ring-2 ring-amber-400': selectedBlockType === 'marker' && selectedBlock?.markerIndex === cell.markerIndex }]"
                      @click.stop="selectBlock({ ...cell }, 'marker')"
                    >
                      <div class="text-xs whitespace-normal break-words">{{ cell.name }}</div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Grid Legend -->
          <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-gray-500">
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-3 h-3 rounded-sm bg-red-600"></span>Stages
            </span>
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-3 h-3 rounded-sm bg-blue-50 border border-gray-400"></span>Seating
            </span>
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-3 h-3 rounded-sm bg-amber-500"></span>Entrances
            </span>
            <span class="flex items-center gap-1.5">
              <span class="inline-block w-3 h-3 rounded-sm bg-yellow-100 border border-yellow-400"></span>Comments
            </span>
          </div>
        </Card>

        <div class="mt-6 flex justify-center">
          <Button
            @click="requestSaveLayout"
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

  <div v-if="showDeleteBookingsDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
      <h3 class="text-lg font-semibold mb-2 text-red-700">Delete all bookings?</h3>

      <p class="text-sm text-gray-700 mb-4">
        This room has {{ bookingsCount }} booking{{ bookingsCount === 1 ? '' : 's' }}.
        Saving the room layout will permanently delete every booking associated with this
        room. This cannot be undone.
      </p>

      <div class="mb-4">
        <Label class="text-sm">
          Type <span class="font-mono font-semibold">remove bookings</span> to confirm
        </Label>
        <Input
          v-model="deleteBookingsConfirmText"
          type="text"
          placeholder="remove bookings"
          class="mt-1"
          @keyup.enter="confirmDeleteBookingsAndSave"
        />
      </div>

      <div class="flex justify-end space-x-3">
        <Button variant="outline" @click="showDeleteBookingsDialog = false">
          Cancel
        </Button>
        <Button
          @click="confirmDeleteBookingsAndSave"
          :disabled="!canConfirmDelete"
          class="bg-red-600 hover:bg-red-700 text-white"
        >
          Delete bookings & save
        </Button>
      </div>
    </div>
  </div>

  <div v-if="showNewBlockDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
      <h3 class="text-lg font-semibold mb-4">Create New Seating Block</h3>

      <div class="mb-4">
        <Label class="text-sm">Block Name</Label>
        <Input
          ref="newBlockNameInput"
          v-model="newBlockName"
          type="text"
          placeholder="Enter block name..."
          class="mt-1"
          @keyup.enter="createNewBlock"
        />
      </div>

      <div class="text-sm text-gray-600 mb-4">
        <span v-if="nextFreeCell.x !== -1">
          The new block will be placed at the first free cell ({{ nextFreeCell.y }}, {{ nextFreeCell.x }}).
        </span>
        <span v-else class="text-orange-600">
          The grid is full. The new block will be created but left unplaced. Free up a cell to place it.
        </span>
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

.layout-cell .bg-white {
  transition: all 0.2s ease;
}

.layout-cell .bg-white:hover {
  transform: scale(1.02);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.text-lg {
  line-height: 1;
  font-weight: bold;
}

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
