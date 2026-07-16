<script setup lang="ts">
import { computed } from 'vue'
import type { Seat, Row, LayoutBlock, Alignment } from '@/types/layout'

interface Props {
  block: LayoutBlock
  bookedSeats: number[]
  selectedSeats: number[]
  reservedSeats?: number[]
  adminMode?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  reservedSeats: () => [],
})

const emit = defineEmits<{
  'seat-click': [seat: Seat]
  'booked-seat-click': [seat: Seat]
}>()

const getSeatStatus = (seat: Seat) => {
  if (props.bookedSeats.includes(seat.id)) {
    return {
      classes: props.adminMode
        ? 'bg-red-500 border-red-600 text-white hover:bg-red-600 cursor-pointer'
        : 'bg-red-500 border-red-600 text-white cursor-not-allowed opacity-70',
      disabled: !props.adminMode
    }
  }
  // Reserved by another guest in the same import batch - blue like a selection, but not
  // truly booked yet and not clickable here (deselect it from that other guest instead).
  if (props.reservedSeats.includes(seat.id)) {
    return {
      classes: 'bg-blue-500 border-blue-600 text-white cursor-not-allowed opacity-70',
      disabled: true
    }
  }

  if (props.selectedSeats.includes(seat.id)) {
    return { classes: 'bg-blue-500 border-blue-600 text-white scale-110', disabled: false }
  }

  return { classes: 'bg-emerald-500 border-emerald-600 text-white hover:bg-emerald-600 hover:scale-110', disabled: false }
}

const handleSeatClick = (seat: Seat) => {
  if (props.bookedSeats.includes(seat.id)) {
    if (props.adminMode) emit('booked-seat-click', seat)
    return
  }
  if (props.reservedSeats.includes(seat.id)) {
    return
  }

  emit('seat-click', seat)
}

const getRowOrder = (rows: Row[], rotation: number) => {
  if (rotation === 90 || rotation === 180) return [...rows].reverse()
  return rows
}

const getJustificationClass = (alignment: Alignment | undefined, _rotation: number | undefined) => {
  switch (alignment ?? 'center') {
    case 'left': return 'justify-start'
    case 'right': return 'justify-end'
    default: return 'justify-center'
  }
}

const getLayoutClasses = computed(() => {
  const rotation = props.block.rotation || 0

  if (rotation === 90) {
    return {
      container: 'flex flex-row gap-1',
      rowSection: 'flex flex-row items-stretch',
      separator: 'flex flex-col items-center justify-center',
      separatorLine: 'flex-1 w-px bg-gray-300 min-h-10',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded transform rotate-180 ml-2 [writing-mode:vertical-rl] [text-orientation:mixed]',
      seats: 'flex flex-col gap-1 items-center'
    }
  } else if (rotation === 270) {
    return {
      container: 'flex flex-row gap-1',
      rowSection: 'flex flex-row items-stretch',
      separator: 'flex flex-col items-center justify-center',
      separatorLine: 'flex-1 w-px bg-gray-300 min-h-10',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded transform rotate-180 mr-2 [writing-mode:vertical-rl] [text-orientation:mixed]',
      seats: 'flex flex-col gap-1 items-start'
    }
  } else if (rotation === 180) {
    return {
      container: 'flex flex-col gap-1',
      rowSection: 'flex flex-col-reverse',
      separator: 'flex items-center gap-2 mt-1.5',
      separatorLine: 'flex-1 h-px bg-gray-300',
      separatorLabel: 'text-xs font-bold text-gray-600 bg-white px-2 py-1 border border-gray-300 rounded whitespace-nowrap',
      seats: 'flex flex-row gap-1 items-center flex-nowrap'
    }
  } else {
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

const getBlockNameClasses = computed(() => {
  switch (props.block.rotation || 0) {
    case 90:  return 'absolute top-1/2 -right-10 transform -translate-y-1/2 rotate-90'
    case 180: return 'absolute -bottom-5 left-1/2 transform -translate-x-1/2'
    case 270: return 'absolute top-1/2 -left-10 transform -translate-y-1/2 -rotate-90'
    default:  return 'absolute -top-5 left-1/2 transform -translate-x-1/2'
  }
})

</script>

<template>
  <div class="relative bg-white border border-gray-300 rounded-md p-3 shadow-sm w-fit h-fit max-w-none">
    <div
      :class="[
        'z-10 bg-white px-1.5 py-0.5 rounded text-sm font-bold text-gray-800 shadow-sm border border-gray-200',
        getBlockNameClasses
      ]"
    >
      {{ block.name }}
    </div>

    <div :class="getLayoutClasses.container">
      <div
        v-for="row in getRowOrder(block.rows ?? [], block.rotation ?? 0)"
        :key="row.id"
        :class="[getLayoutClasses.rowSection, 'mb-0']"
      >
        <template v-if="block.rotation === 270">
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="['w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0', getSeatStatus(seat).classes]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >{{ seat.label || seat.name }}</button>
          </div>
        </template>

        <template v-else-if="block.rotation === 90">
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="['w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0', getSeatStatus(seat).classes]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >{{ seat.label || seat.name }}</button>
          </div>
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>
        </template>

        <template v-else>
          <div :class="getLayoutClasses.separator">
            <span :class="getLayoutClasses.separatorLine"></span>
            <span :class="getLayoutClasses.separatorLabel">{{ row.name }}</span>
            <span :class="getLayoutClasses.separatorLine"></span>
          </div>
          <div :class="[getLayoutClasses.seats, getJustificationClass(row.alignment, block.rotation)]">
            <button
              v-for="seat in row.seats"
              :key="seat.id"
              :class="['w-7 h-7 border rounded text-xs font-bold transition-all duration-200 flex items-center justify-center p-0 shrink-0', getSeatStatus(seat).classes]"
              :disabled="getSeatStatus(seat).disabled"
              :title="`${block.name} - ${row.name} - ${seat.label || seat.name}`"
              @click="handleSeatClick(seat)"
            >{{ seat.label || seat.name }}</button>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

