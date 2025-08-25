<script setup lang="ts">
import { computed } from 'vue'

interface Seat {
  id: number
  label: string
  name: string
}

interface Row {
  id: number
  name: string
  seats: Seat[]
}

interface Block {
  id: number
  name: string
  rotation: number
  rows: Row[]
}

interface Props {
  block: Block
  bookedSeats: number[]
  selectedSeats: number[]
  adminMode?: boolean
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'seat-click': [seat: Seat]
  'booked-seat-click': [seat: Seat]
}>()

// Get seat status styling
const getSeatStatus = (seat: Seat) => {
  const seatId = seat.id

  if (props.bookedSeats.includes(seatId)) {
    return {
      classes: props.adminMode
        ? 'bg-red-500 border-red-600 text-white hover:bg-red-600 cursor-pointer'
        : 'bg-red-500 border-red-600 text-white cursor-not-allowed opacity-70',
      disabled: !props.adminMode
    }
  }

  if (props.selectedSeats.includes(seatId)) {
    return {
      classes: 'bg-blue-500 border-blue-600 text-white scale-110',
      disabled: false
    }
  }

  return {
    classes: 'bg-emerald-500 border-emerald-600 text-white hover:bg-emerald-600 hover:scale-110',
    disabled: false
  }
}

// Handle seat click
const handleSeatClick = (seat: Seat) => {
  const isBooked = props.bookedSeats.includes(seat.id)

  if (isBooked) {
    if (props.adminMode) {
      emit('booked-seat-click', seat)
    }
    return
  }

  emit('seat-click', seat)
}

// Get row order based on rotation
const getRowOrder = (rows: Row[], rotation: number) => {
  if (rotation === 180) {
    return [...rows].reverse() // 180°: count down (10→1)
  }
  if (rotation === 270) {
    return rows // 270°: normal order (1→10) - Row 1 on left, Row 10 on right
  }
  if (rotation === 90) {
    return [...rows].reverse() // 90°: count down (10→1) - Row 1 on right, Row 10 on left
  }
  return rows // 0°: count up (1→10)
}

// Get justification class based on alignment and rotation
const getJustificationClass = (alignment, rotation) => {
  // Default to center if no alignment specified
  if (!alignment) alignment = 'center'
  
  if (rotation === 90 || rotation === 270) {
    // For vertical orientations, reverse the mapping
    switch (alignment) {
      case 'left': return 'justify-start'    // left becomes top (start)
      case 'center': return 'justify-center' // center stays center
      case 'right': return 'justify-end'     // right becomes bottom (end)
      default: return 'justify-center'
    }
  } else {
    // For horizontal orientations (0° and 180°)
    switch (alignment) {
      case 'left': return 'justify-start'
      case 'center': return 'justify-center'
      case 'right': return 'justify-end'
      default: return 'justify-center'
    }
  }
}

// Get layout classes based on rotation
const getLayoutClasses = computed(() => {
  const rotation = props.block.rotation || 0

  if (rotation === 90) {
    // 90° (→ arrow pointing right): separator on left, seats on right
    return {
      container: 'flex flex-row gap-1',
      rowSection: 'flex flex-row items-stretch',
      separator: 'flex flex-col items-center justify-center',
      separatorLine: 'flex-1 w-px bg-gray-300 min-h-10',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded transform rotate-180 ml-2 [writing-mode:vertical-rl] [text-orientation:mixed]',
      seats: 'flex flex-col gap-1 items-center'
    }
  } else if (rotation === 270) {
    // 270° (← arrow pointing left): seats on left, separator on right
    return {
      container: 'flex flex-row gap-1',
      rowSection: 'flex flex-row items-stretch',
      separator: 'flex flex-col items-center justify-center',
      separatorLine: 'flex-1 w-px bg-gray-300 min-h-10',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded transform rotate-180 mr-2 [writing-mode:vertical-rl] [text-orientation:mixed]',
      seats: 'flex flex-col gap-1 items-start'
    }
  } else if (rotation === 180) {
    // For 180°: reverse the row order (seats first, then separator)
    return {
      container: 'flex flex-col gap-1',
      rowSection: 'flex flex-col-reverse',
      separator: 'flex items-center gap-2 mt-1.5',
      separatorLine: 'flex-1 h-px bg-gray-300',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded whitespace-nowrap',
      seats: 'flex flex-row gap-1 items-center flex-nowrap'
    }
  } else {
    // For 0°: normal order (separator first, then seats)
    return {
      container: 'flex flex-col gap-1',
      rowSection: 'flex flex-col',
      separator: 'flex items-center gap-2 mb-1.5',
      separatorLine: 'flex-1 h-px bg-gray-300',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded whitespace-nowrap',
      seats: 'flex flex-row gap-1 items-center flex-nowrap'
    }
  }
})

// Get block name position classes
const getBlockNameClasses = computed(() => {
  const rotation = props.block.rotation || 0

  switch (rotation) {
    case 0:
      return 'absolute -top-5 left-1/2 transform -translate-x-1/2'
    case 90:
      return 'absolute top-1/2 -right-10 transform -translate-y-1/2 rotate-90'
    case 180:
      return 'absolute -bottom-5 left-1/2 transform -translate-x-1/2'
    case 270:
      return 'absolute top-1/2 -left-10 transform -translate-y-1/2 -rotate-90'
    default:
      return 'absolute -top-5 left-1/2 transform -translate-x-1/2'
  }
})

</script>

<template>
  <div class="relative bg-white border border-gray-300 rounded-md p-3 shadow-sm w-fit h-fit max-w-none">
    <!-- Block Name Label -->
    <div
      :class="[
        'z-10 bg-white px-1.5 py-0.5 rounded text-sm font-bold text-gray-800 shadow-sm border border-gray-200',
        getBlockNameClasses
      ]"
    >
      {{ block.name }}
    </div>

    <!-- Rows Container -->
    <div :class="getLayoutClasses.container">
      <div
        v-for="row in getRowOrder(block.rows, block.rotation)"
        :key="row.id"
        :class="[getLayoutClasses.rowSection, 'mb-0 mr-3']"
      >
        <!-- For 270°: separator first, then seats -->
        <template v-if="block.rotation === 270">
          <!-- Row Separator -->
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>

          <!-- Seats Container -->
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="[
                'w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0',
                getSeatStatus(seat).classes
              ]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >
              {{ seat.label || seat.name }}
            </button>
          </div>
        </template>

        <!-- For 90°: seats first, then separator (Seats | Row) -->
        <template v-else-if="block.rotation === 90">
          <!-- Seats Container -->
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="[
                'w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0',
                getSeatStatus(seat).classes
              ]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >
              {{ seat.label || seat.name }}
            </button>
          </div>

          <!-- Row Separator -->
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>
        </template>

        <!-- For 0° and 180°: separator first, then seats (or reversed by flex-col-reverse) -->
        <template v-else>
          <!-- Row Separator -->
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>

          <!-- Seats Container -->
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="[
                'w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0',
                getSeatStatus(seat).classes
              ]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >
              {{ seat.label || seat.name }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

