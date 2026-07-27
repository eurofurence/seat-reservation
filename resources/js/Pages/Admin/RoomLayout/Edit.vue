<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import { Button } from '@/Components/ui/button'
import { Card } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { useToast } from '@/Components/ui/toast'
import { Trash2, RotateCw, ArrowUp, ArrowRight, ArrowDown, ArrowLeft, TriangleAlert, ChevronDown, ChevronRight } from 'lucide-vue-next'
import type {
  BlockId, Orientation, MarkerType,
  PropBlock, PropStageBlock, PropMarkerBlock,
  FormBlock, FormStageBlock, FormMarkerBlock, FormRow
} from '@/types/layout'

defineOptions({ layout: AdminLayout })

const DEFAULT_SEAT_COUNT = 25
const DEFAULT_ROW_COUNT = 10

const { error: errorToast } = useToast()

const props = withDefaults(defineProps<{
  room: { id: number, name: string }
  bookingsCount?: number
  blocks: PropBlock[]
  stageBlocks: PropStageBlock[]
  markerBlocks?: PropMarkerBlock[]
  blockIdMap?: Record<string, number>
  title: string
  breadcrumbs?: Array<{ title: string, url: string | null }>
}>(), {
  bookingsCount: 0,
  markerBlocks: () => [],
  blockIdMap: () => ({})
})

let tempBlockCounter = 0
const nextTempKey = (): string => `temp-${++tempBlockCounter}`

const GRID_ROWS = 8
const GRID_COLS = 12

const UNPLACED = { position_x: -1, position_y: -1 }

const inGrid = (x: number, y: number) => x >= 0 && x < GRID_COLS && y >= 0 && y < GRID_ROWS

const isPlaced = (block: { position_x: number, position_y: number }) => block.position_x >= 0 && block.position_y >= 0

const CELL_BASE = 'w-full h-full border rounded cursor-pointer flex flex-col items-center justify-center text-center hover:shadow-md transition-shadow'

const pos = (value: number | null | undefined) => (value != null ? value : -1)

const span = (value: number | null | undefined) => (value != null && value >= 1 ? value : 1)

const stageFromProp = (block: PropStageBlock): FormStageBlock => ({
  id: block.id,
  name: block.name,
  position_x: pos(block.position_x),
  position_y: pos(block.position_y),
  colspan: span(block.colspan),
  rowspan: span(block.rowspan)
})

const markerFromProp = (block: PropMarkerBlock): FormMarkerBlock => {
  const x = pos(block.position_x)
  const y = pos(block.position_y)
  let orientation: Orientation = 'row'
  if (block.type === 'entrance') {
    if (x === -1 && y === -1) {
      // cant figure at axis, so use the rotation to get some semblance of it
      const r = block.rotation ?? 0
      orientation = (r === 90 || r === 270) ? 'column' : 'row'
    } else {
      orientation = y === -1 ? 'column' : 'row'
    }
  }
  return {
    id: block.id,
    type: block.type,
    name: block.name,
    position_x: x, position_y: y,
    orientation,
    rotation: block.rotation || 0,
    colspan: span(block.colspan), rowspan: span(block.rowspan)
  }
}

const rowsFromBlock = (block: PropBlock | null): FormRow[] => {
  const rowCount = block?.rows?.length || DEFAULT_ROW_COUNT
  return Array.from({ length: rowCount }, (_, i) => {
    const existingRow = block?.rows?.[i]
    return {
      rowNumber: i + 1,
      seatCount: existingRow?.seats_count || DEFAULT_SEAT_COUNT,
      isCustom: existingRow?.custom_seat_count != null,
      alignment: existingRow?.alignment || 'center'
    }
  })
}

const blockFromProp = (block: PropBlock): FormBlock => ({
  id: block.id,
  name: block.name,
  position_x: pos(block.position_x),
  position_y: pos(block.position_y),
  rotation: block.rotation || 0,
  colspan: span(block.colspan),
  rowspan: span(block.rowspan),
  rows: rowsFromBlock(block)
})

const form = useForm({
  stageBlocks: props.stageBlocks.map(stageFromProp),
  markerBlocks: props.markerBlocks.map(markerFromProp),
  blocks: props.blocks.map(blockFromProp)
})

type SelectedType = 'stage' | 'seating' | 'marker'

type CellType = 'stage' | 'seating' | 'comment' | 'entrance'

interface Positioned {
  id: BlockId
  position_x: number
  position_y: number
}

interface SelectedBlock extends Positioned {
  type?: CellType
  colspan?: number
  rowspan?: number
  rotation?: number
  markerIndex?: number
}

type PlacementCell = SelectedBlock & {
  type: CellType
  name: string
  rotation?: number
  rows?: FormRow[]
  orientation?: Orientation
  colSpan?: number
  rowSpan?: number
}

type GridCell =
  | null
  | PlacementCell
  | { type: 'covered', id: BlockId }

const selectedBlock = ref<SelectedBlock | null>(null)
const selectedBlockType = ref<SelectedType | null>(null)
const expandedBlocks = ref<Set<BlockId>>(new Set())

type PanelKey = 'stage' | 'entrances' | 'comments' | 'seating'
const collapsedPanels = ref<Set<PanelKey>>(new Set())
const togglePanel = (panel: PanelKey) => {
  collapsedPanels.value.has(panel)
    ? collapsedPanels.value.delete(panel)
    : collapsedPanels.value.add(panel)
}
const isPanelCollapsed = (panel: PanelKey) => collapsedPanels.value.has(panel)
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


const markerOrientation = (marker: FormMarkerBlock): Orientation => (marker.position_y === -1 ? 'column' : 'row')

const markerCells = (marker: FormMarkerBlock): Array<[number, number]> => {
  const { position_x: x, position_y: y } = marker
  if (markerOrientation(marker) === 'column') {
    return Array.from({ length: GRID_ROWS }, (_, r) => [r, x])
  }
  return Array.from({ length: GRID_COLS }, (_, c) => [y, c])
}

const placeSpanned = (grid: GridCell[][], cell: PlacementCell) => {
  const { position_x: x, position_y: y } = cell
  if (!inGrid(x, y)) return
  const colSpan = span(cell.colspan)
  const rowSpan = span(cell.rowspan)
  for (let dr = 0; dr < rowSpan; dr++) {
    for (let dc = 0; dc < colSpan; dc++) {
      if (!inGrid(x + dc, y + dr)) continue
      grid[y + dr][x + dc] = (dr === 0 && dc === 0)
        ? { ...cell, colSpan, rowSpan }
        : { type: 'covered', id: cell.id }
    }
  }
}

const layoutGrid = computed<GridCell[][]>(() => {
  const grid: GridCell[][] = Array.from({ length: GRID_ROWS }, () => Array.from({ length: GRID_COLS }, (): GridCell => null))

  form.stageBlocks.forEach(stageBlock => placeSpanned(grid, { type: 'stage', ...stageBlock }))
  form.blocks.forEach(block => placeSpanned(grid, { type: 'seating', ...block }))

  form.markerBlocks.forEach((marker, index) => {
    const cell = { ...marker, markerIndex: index, orientation: markerOrientation(marker) }
    if (marker.type === 'comment') {
      placeSpanned(grid, cell)
      return
    }
    for (const [y, x] of markerCells(marker)) {
      if (grid[y]?.[x] === null) grid[y][x] = cell
    }
  })

  return grid
})

const getTotalSeats = (rows: FormRow[] = []) =>
  rows.reduce((total, row) => total + (row.seatCount || 0), 0)

const getOrientationArrow = (rotation: number | undefined) => {
  const arrows: Record<number, typeof ArrowUp> = {
    0: ArrowUp,
    90: ArrowRight,
    180: ArrowDown,
    270: ArrowLeft
  }
  return (rotation != null && arrows[rotation]) || ArrowUp
}

const toggleBlockExpanded = (blockId: BlockId) => {
  if (expandedBlocks.value.has(blockId)) {
    expandedBlocks.value.delete(blockId)
  } else {
    expandedBlocks.value.add(blockId)
  }
}

const isBlockExpanded = (blockId: BlockId) => {
  return expandedBlocks.value.has(blockId)
}

const selectBlock = (block: SelectedBlock, type: SelectedType) => {
  selectedBlock.value = block
  selectedBlockType.value = type
}

interface Rect { id: BlockId, x: number, y: number, w: number, h: number }

const rectsOverlap = (a: Rect, b: Rect) =>
  a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h

const blockRect = (b: SelectedBlock, x = b.position_x, y = b.position_y): Rect =>
  ({ id: b.id, x, y, w: span(b.colspan), h: span(b.rowspan) })

const rectOnGrid = (r: Rect) => inGrid(r.x, r.y) && inGrid(r.x + r.w - 1, r.y + r.h - 1)

const solidBlocks = () =>
  [...form.stageBlocks, ...form.blocks, ...form.markerBlocks.filter(m => m.type === 'comment')]

const rectUnobstructed = (rect: Rect) =>
  solidBlocks()
    .filter(isPlaced)
    .filter(block => block.id !== rect.id)
    .every(block => !rectsOverlap(rect, blockRect(block)))

const canPlace = (rect: Rect) => rectOnGrid(rect) && rectUnobstructed(rect)

const clampSpan = (value: string | number, max: number) =>
  Math.min(max, Math.max(1, Math.floor(Number(value)) || 1))

const commitSpan = (block: SelectedBlock, axis: 'colspan' | 'rowspan', value: string | number, max: number) => {
  const previous = span(block[axis])
  block[axis] = clampSpan(value, max)

  const resizeCollides = isPlaced(block) && !canPlace(blockRect(block))
  if (resizeCollides) block[axis] = previous
}

const setPosition = (list: Positioned[], id: BlockId, x: number, y: number) => {
  const target = list.find(b => b.id === id)
  if (target) Object.assign(target, { position_x: x, position_y: y })
}

const handleCellClick = (rowIndex: number, colIndex: number) => {
  const block = selectedBlock.value
  const type = selectedBlockType.value
  if (!block || !type) return

  if (type === 'marker') {
    if (block.markerIndex !== undefined) placeMarker(block.markerIndex, rowIndex, colIndex)
  } else if (canPlace(blockRect(block, colIndex, rowIndex))) {
    setPosition(type === 'stage' ? form.stageBlocks : form.blocks, block.id, colIndex, rowIndex)
  }
}

const entranceBand = (marker: FormMarkerBlock, orientation: Orientation, index: number): Rect =>
  orientation === 'column'
    ? { id: marker.id, x: index, y: 0, w: 1, h: GRID_ROWS }
    : { id: marker.id, x: 0, y: index, w: GRID_COLS, h: 1 }

const placeMarker = (markerIndex: number, rowIndex: number, colIndex: number) => {
  const marker = form.markerBlocks[markerIndex]
  if (!marker) return

  if (marker.type === 'comment') {
    if (canPlace(blockRect(marker, colIndex, rowIndex))) {
      Object.assign(marker, { position_x: colIndex, position_y: rowIndex })
    }
  } else if (marker.orientation === 'column') {
    if (rectUnobstructed(entranceBand(marker, 'column', colIndex))) {
      Object.assign(marker, { position_x: colIndex, position_y: -1 })
    }
  } else {
    if (rectUnobstructed(entranceBand(marker, 'row', rowIndex))) {
      Object.assign(marker, { position_x: -1, position_y: rowIndex })
    }
  }
}

const rotateBlock = (blockId: BlockId) => {
  const block = form.blocks.find(b => b.id === blockId)
  if (block) block.rotation = (block.rotation + 90) % 360
}

const addStageBlock = () => {
  form.stageBlocks.push({
    id: nextTempKey(),
    name: `Stage ${form.stageBlocks.length + 1}`,
    colspan: 1,
    rowspan: 1,
    ...UNPLACED
  })
}

const removeStageBlock = (index: number) => {
  if (confirm('Are you sure you want to remove this stage block?')) {
    form.stageBlocks.splice(index, 1)
  }
}

const ENTRANCE_DIRECTIONS: Record<Orientation, number[]> = {
  row: [0, 180],
  column: [90, 270]
}

const addMarker = (type: MarkerType) => {
  const id = nextTempKey()
  if (type === 'comment') {
    form.markerBlocks.push({ id, type, name: 'Note', colspan: 1, rowspan: 1, ...UNPLACED })
    return
  }
  const count = form.markerBlocks.filter(m => m.type === 'entrance').length
  form.markerBlocks.push({
    id,
    type,
    name: count === 0 ? 'Entrance' : `Entrance ${count + 1}`,
    orientation: 'row',
    rotation: ENTRANCE_DIRECTIONS.row[0],
    colspan: 1,
    rowspan: 1,
    ...UNPLACED
  })
}

const setEntranceOrientation = (marker: FormMarkerBlock, orientation: Orientation) => {
  Object.assign(marker, {
    orientation,
    rotation: ENTRANCE_DIRECTIONS[orientation][0],
    ...UNPLACED
  })
}

const rotateEntrance = (marker: FormMarkerBlock) => {
  const directions = ENTRANCE_DIRECTIONS[marker.orientation ?? 'row']
  const current = directions.indexOf(marker.rotation ?? directions[0])
  marker.rotation = directions[(current + 1) % directions.length]
}

const removeMarkerBlock = (index: number) => {
  if (confirm('Are you sure you want to remove this marker?')) {
    if (selectedBlockType.value === 'marker' && selectedBlock.value?.markerIndex === index) {
      selectedBlock.value = null
      selectedBlockType.value = null
    }
    form.markerBlocks.splice(index, 1)
  }
}

const getMarkerPlacementStatus = (marker: FormMarkerBlock) => {
  if (marker.type === 'comment') return isPlaced(marker)
  return marker.orientation === 'column' ? marker.position_x >= 0 : marker.position_y >= 0
}

const deleteSeatingBlock = (blockId: BlockId) => {
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

const toStagePayload = (s: FormStageBlock) => ({
  id: s.id,
  name: s.name,
  position_x: s.position_x,
  position_y: s.position_y,
  colspan: s.colspan,
  rowspan: s.rowspan
})

const toMarkerPayload = (m: FormMarkerBlock) => ({
  id: m.id,
  type: m.type,
  name: m.name,
  position_x: m.position_x,
  position_y: m.position_y,
  rotation: m.rotation ?? 0,
  colspan: m.colspan,
  rowspan: m.rowspan
})

const toRowPayload = (r: FormRow) => ({
  rowNumber: r.rowNumber,
  seatCount: r.seatCount,
  isCustom: r.isCustom,
  alignment: r.alignment
})

interface BlockPayload {
  id: BlockId
  name: string
  position_x: number
  position_y: number
  rotation: number
  colspan: number
  rowspan: number
  rowsData: ReturnType<typeof toRowPayload>[] // always >= 1 row; seeded on load, guarded on removal
}

const toBlockPayload = (b: FormBlock): BlockPayload => ({
  id: b.id,
  name: b.name,
  position_x: b.position_x,
  position_y: b.position_y,
  rotation: b.rotation,
  colspan: b.colspan,
  rowspan: b.rowspan,
  rowsData: b.rows.map(toRowPayload)
})

// Use server provided ids for new blocks
const applyBlockIdMap = () => {
  const map = props.blockIdMap || {}
  const remap = (list: Array<{ id: BlockId }>) => {
    list.forEach(block => {
      if (map[block.id] != null) block.id = map[block.id]
    })
  }
  remap(form.stageBlocks)
  remap(form.markerBlocks)
  remap(form.blocks)
}

const showSaveError = (errors: Record<string, string>) => {
  const messages = Object.values(errors)
  errorToast(
    'Layout could not be saved',
    messages.length ? messages.join('\n') : 'Please reload the page or try again.'
  )
}

const saveLayout = () => {
  isSubmitting.value = true

  const submitForm = useForm({
    stageBlocks: form.stageBlocks.map(toStagePayload),
    markerBlocks: form.markerBlocks.map(toMarkerPayload),
    blocks: form.blocks.map(toBlockPayload)
  })

  submitForm.put(route('admin.rooms.layout.update', props.room.id), {
    onFinish: () => { isSubmitting.value = false },
    onSuccess: applyBlockIdMap,
    onError: showSaveError
  })
}

const newBlockNameInput = ref<HTMLInputElement | null>(null)

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
    id: nextTempKey(),
    name,
    position_x: x,
    position_y: y,
    rotation: 0,
    colspan: 1,
    rowspan: 1,
    rows: rowsFromBlock(null)
  })

  closeNewBlockDialog()
}

const addRow = (block: FormBlock) => {
  const newRowNumber = block.rows.length + 1
  block.rows.push({
    rowNumber: newRowNumber,
    seatCount: DEFAULT_SEAT_COUNT,
    isCustom: false,
    alignment: 'center'
  })
}

const removeRow = (block: FormBlock) => {
  if (block.rows.length > 1) {
    block.rows.pop()
  }
}

const removeSpecificRow = (block: FormBlock, rowNumber: number) => {
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
      <TriangleAlert class="w-5 h-5 shrink-0 mt-0.5" />
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
          <div class="flex justify-between items-center" :class="{ 'mb-2': !isPanelCollapsed('stage') }">
            <button type="button" :aria-expanded="!isPanelCollapsed('stage')" @click="togglePanel('stage')" class="flex items-center gap-1 font-semibold">
              <component :is="isPanelCollapsed('stage') ? ChevronRight : ChevronDown" class="w-4 h-4" />
              Stage Blocks
            </button>
            <Button size="sm" variant="outline" class="text-xs" @click="addStageBlock">
              + Add Stage
            </Button>
          </div>

          <div v-show="!isPanelCollapsed('stage')" class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="(stageBlock, index) in form.stageBlocks"
              :key="stageBlock.id"
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

                <div class="flex gap-4 mt-2" @click.stop>
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Cols</Label>
                    <Input :model-value="stageBlock.colspan" @update:model-value="commitSpan(stageBlock, 'colspan', $event, GRID_COLS)" type="number" min="1" max="12" class="text-xs h-7 w-16" />
                  </div>
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Rows</Label>
                    <Input :model-value="stageBlock.rowspan" @update:model-value="commitSpan(stageBlock, 'rowspan', $event, GRID_ROWS)" type="number" min="1" max="8" class="text-xs h-7 w-16" />
                  </div>
                </div>

                <div class="text-xs text-gray-500 mt-2">
                  <span v-if="isPlaced(stageBlock)" class="text-green-600">• Placed ({{ stageBlock.position_y }}, {{ stageBlock.position_x }})</span>
                  <span v-else class="text-orange-600">• Not placed</span>
                </div>
              </div>
            </div>
          </div>
        </Card>

        <!-- Entrance Management -->
        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center" :class="{ 'mb-2': !isPanelCollapsed('entrances') }">
            <button type="button" :aria-expanded="!isPanelCollapsed('entrances')" @click="togglePanel('entrances')" class="flex items-center gap-1 font-semibold">
              <component :is="isPanelCollapsed('entrances') ? ChevronRight : ChevronDown" class="w-4 h-4" />
              Entrances
            </button>
            <Button size="sm" variant="outline" class="text-xs" @click="addMarker('entrance')">
              + Entrance
            </Button>
          </div>

          <div v-show="!isPanelCollapsed('entrances')" class="space-y-2 max-h-96 overflow-y-auto">
            <template v-for="(marker, index) in form.markerBlocks" :key="marker.id">
              <div
                v-if="marker.type === 'entrance'"
                class="border border-gray-200 rounded-lg p-3"
                :class="{ 'ring-2 ring-inset ring-amber-500': selectedBlockType === 'marker' && selectedBlock?.markerIndex === index }"
                @click="selectBlock({ ...marker, markerIndex: index }, 'marker')"
              >
                <div class="flex items-end justify-between mb-2">
                  <div class="flex-1">
                    <Label class="text-xs">Entrance label</Label>
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
                    title="Remove entrance"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>

                <div class="space-y-2 mb-2">
                  <div class="flex items-center gap-2">
                    <Label class="text-xs w-16">Spans</Label>
                    <select
                      :value="marker.orientation"
                      @click.stop
                      @change="setEntranceOrientation(marker, ($event.target as HTMLSelectElement).value as Orientation)"
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
                    <template v-if="marker.orientation === 'column'">• Column {{ marker.position_x }}</template>
                    <template v-else>• Row {{ marker.position_y }}</template>
                  </span>
                  <span v-else class="text-orange-600">• Not placed</span>
                </div>
              </div>
            </template>
            <p v-if="!form.markerBlocks.some(m => m.type === 'entrance')" class="text-xs text-gray-400">
              No entrances yet. Add an entrance row or column.
            </p>
          </div>
        </Card>

        <!-- Comment Management -->
        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center" :class="{ 'mb-2': !isPanelCollapsed('comments') }">
            <button type="button" :aria-expanded="!isPanelCollapsed('comments')" @click="togglePanel('comments')" class="flex items-center gap-1 font-semibold">
              <component :is="isPanelCollapsed('comments') ? ChevronRight : ChevronDown" class="w-4 h-4" />
              Comments
            </button>
            <Button size="sm" variant="outline" class="text-xs" @click="addMarker('comment')">
              + Comment
            </Button>
          </div>

          <div v-show="!isPanelCollapsed('comments')" class="space-y-2 max-h-96 overflow-y-auto">
            <template v-for="(marker, index) in form.markerBlocks" :key="marker.id">
              <div
                v-if="marker.type === 'comment'"
                class="border border-gray-200 rounded-lg p-3"
                :class="{ 'ring-2 ring-inset ring-amber-500': selectedBlockType === 'marker' && selectedBlock?.markerIndex === index }"
                @click="selectBlock({ ...marker, markerIndex: index }, 'marker')"
              >
                <div class="flex items-end justify-between mb-2">
                  <div class="flex-1">
                    <Label class="text-xs">Comment text</Label>
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
                    title="Remove comment"
                  >
                    <Trash2 class="w-4 h-4" />
                  </Button>
                </div>

                <div class="flex gap-4 mb-2" @click.stop>
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Cols</Label>
                    <Input :model-value="marker.colspan" @update:model-value="commitSpan(marker, 'colspan', $event, GRID_COLS)" type="number" min="1" max="12" class="text-xs h-7 w-16" />
                  </div>
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Rows</Label>
                    <Input :model-value="marker.rowspan" @update:model-value="commitSpan(marker, 'rowspan', $event, GRID_ROWS)" type="number" min="1" max="8" class="text-xs h-7 w-16" />
                  </div>
                </div>

                <div class="text-xs text-gray-500">
                  <span v-if="getMarkerPlacementStatus(marker)" class="text-green-600">• Placed ({{ marker.position_y }}, {{ marker.position_x }})</span>
                  <span v-else class="text-orange-600">• Not placed</span>
                </div>
              </div>
            </template>
            <p v-if="!form.markerBlocks.some(m => m.type === 'comment')" class="text-xs text-gray-400">
              No comments yet. Add a comment note.
            </p>
          </div>
        </Card>

        <!-- Seating Blocks Management -->
        <Card class="p-4 gap-0">
          <div class="flex justify-between items-center" :class="{ 'mb-2': !isPanelCollapsed('seating') }">
            <button type="button" :aria-expanded="!isPanelCollapsed('seating')" @click="togglePanel('seating')" class="flex items-center gap-1 font-semibold">
              <component :is="isPanelCollapsed('seating') ? ChevronRight : ChevronDown" class="w-4 h-4" />
              Seating Blocks
            </button>
            <Button size="sm" variant="outline" class="text-xs" @click="openNewBlockDialog">
              + Add Block
            </Button>
          </div>

          <div v-show="!isPanelCollapsed('seating')" class="space-y-2 overflow-y-auto">
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
                      {{ getTotalSeats(block.rows) }} seats • {{ block.rows.length }} rows
                      <span v-if="isPlaced(block)" class="text-green-600">• Placed ({{ block.position_y }}, {{ block.position_x }})</span>
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
                <div class="flex gap-4 mb-3">
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Cols</Label>
                    <Input :model-value="block.colspan" @update:model-value="commitSpan(block, 'colspan', $event, GRID_COLS)" type="number" min="1" max="12" class="text-xs h-7 w-16" />
                  </div>
                  <div class="flex items-center gap-2">
                    <Label class="text-xs">Rows</Label>
                    <Input :model-value="block.rowspan" @update:model-value="commitSpan(block, 'rowspan', $event, GRID_ROWS)" type="number" min="1" max="8" class="text-xs h-7 w-16" />
                  </div>
                </div>

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
                      class="text-xs px-1 py-0 h-6 w-6 text-red-600 hover:bg-red-50 shrink-0"
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
                <tr v-for="(row, rowIndex) in layoutGrid" :key="rowIndex" class="layout-row">
                  <template v-for="(cell, colIndex) in row" :key="colIndex">
                  <td
                    v-if="cell?.type !== 'covered'"
                    :colspan="cell?.colSpan || 1"
                    :rowspan="cell?.rowSpan || 1"
                    class="layout-cell border border-gray-300 w-24 p-1 relative"
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

                      <div class="text-xs text-gray-600">{{ getTotalSeats(cell.rows) }} seats</div>
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
                  </template>
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

.layout-row {
  height: 96px;
}

.layout-cell {
  min-width: 96px;
  height: 96px;
  position: relative;
  transition: background-color 0.2s ease;
}

.layout-cell > div {
  position: absolute;
  inset: 4px;
  width: auto;
  height: auto;
}

.layout-cell:hover {
  background-color: #f0f9ff !important;
}

.layout-cell > div.cursor-pointer {
  transition: inset 0.15s ease;
}

.layout-cell > div.cursor-pointer:hover {
  inset: 2px;
  z-index: 10;
}

.text-lg {
  line-height: 1;
  font-weight: bold;
}

@media (max-width: 1024px) {
  .layout-table {
    min-width: 600px;
  }

  .layout-row {
    height: 80px;
  }

  .layout-cell {
    min-width: 80px;
    height: 80px;
  }
}

@media (max-width: 768px) {
  .layout-row {
    height: 60px;
  }

  .layout-cell {
    min-width: 60px;
    height: 60px;
  }

  .layout-cell .text-xs {
    font-size: 0.625rem;
  }
}
</style>
