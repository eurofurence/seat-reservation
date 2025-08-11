<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import FullWidthLayout from "@/Layouts/FullWidthLayout.vue"
import Seat from "@/Components/Seat.vue"
import FloorPlanViewer from "@/Components/FloorPlan/FloorPlanViewer.vue"
import dayjs from "dayjs"

defineOptions({layout: FullWidthLayout})

const props = defineProps({
    event: Object,
    seats: Array,
    takenSeats: Array,
    availableToUser: Number,
})

const selectedSeats = ref([])
const selectedSeatIds = ref(props.seats)
const showInstructions = ref(true)

// Store loaded seat data for each row
const loadedSeatsData = ref(new Map())

// Convert seat data for FloorPlanViewer
const floorPlanData = computed(() => {
    return {
        room: props.event.room,
        blocks: props.event.room.blocks.map(block => ({
            ...block,
            rows: block.rows.map(row => {
                // Check if we have loaded seat data for this row
                const loadedSeats = loadedSeatsData.value.get(row.id)
                if (loadedSeats) {
                    return {
                        ...row,
                        seats: loadedSeats
                    }
                }
                
                // Generate placeholder seats for display
                return {
                    ...row,
                    seats: Array.from({ length: row.seat_count || 0 }, (_, i) => ({
                        id: `temp_${row.id}_${i + 1}`,
                        number: i + 1,
                        name: String(i + 1),
                        is_available: true,
                        is_placeholder: true
                    }))
                }
            })
        })),
        layoutConfig: props.event.room.layout_config || {
            grid_columns: 12,
            grid_rows: 8,
            gap_size: 48,
            show_grid_lines: true,
            stage: null
        }
    }
})

// Load actual seat data for a row when needed
async function loadRowSeats(rowId) {
    if (loadedSeatsData.value.has(rowId)) {
        return loadedSeatsData.value.get(rowId)
    }
    
    try {
        const response = await fetch(`/api/events/${props.event.id}/rows/${rowId}/seats`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin'
        })
        
        if (!response.ok) {
            throw new Error('Failed to load seat data')
        }
        
        const data = await response.json()
        loadedSeatsData.value.set(rowId, data.seats)
        return data.seats
    } catch (error) {
        console.error('Error loading seat data:', error)
        return []
    }
}

async function onSeatSelected(seatData) {
    // If it's a placeholder seat, load real seat data first
    if (seatData.seatId && seatData.seatId.toString().startsWith('temp_')) {
        const realSeats = await loadRowSeats(seatData.rowId)
        const seatIndex = parseInt(seatData.seatId.split('_')[2]) - 1
        const realSeat = realSeats[seatIndex]
        
        if (!realSeat) return
        
        // Update seatData with real seat ID
        seatData.seatId = realSeat.id
        seatData.is_available = realSeat.is_available
        seatData.is_booked = realSeat.is_booked
    }
    
    // Check if seat is already taken
    if (props.takenSeats.includes(seatData.seatId)) {
        return
    }
    
    if (selectedSeatIds.value.length >= props.availableToUser) {
        showToast('Maximum seats selected')
        return
    }
    
    selectedSeats.value.push(seatData)
    selectedSeatIds.value.push(seatData.seatId)
}

async function onSeatDeselected(seatData) {
    // If it's a placeholder seat, load real seat data first
    if (seatData.seatId && seatData.seatId.toString().startsWith('temp_')) {
        const realSeats = await loadRowSeats(seatData.rowId)
        const seatIndex = parseInt(seatData.seatId.split('_')[2]) - 1
        const realSeat = realSeats[seatIndex]
        
        if (!realSeat) return
        
        // Update seatData with real seat ID
        seatData.seatId = realSeat.id
    }
    
    selectedSeats.value = selectedSeats.value.filter(s => 
        !(s.blockId === seatData.blockId && s.rowId === seatData.rowId && s.seatId === seatData.seatId)
    )
    selectedSeatIds.value = selectedSeatIds.value.filter(id => id !== seatData.seatId)
}

// Legacy handler for old Seat component (if still needed)
function clickSeatHandler(seatId) {
    // Check if seat is already taken
    if (props.takenSeats.includes(seatId)) {
        return
    }
    
    // Check if seat is already selected, if so remove it, if not add it
    if (selectedSeatIds.value.find(function (item) {
        return item == seatId
    })) {
        selectedSeatIds.value.splice(selectedSeatIds.value.findIndex((item) => item == seatId), 1)
    } else {
        if (selectedSeatIds.value.length >= props.availableToUser) {
            showToast('Maximum seats selected')
            return
        }
        selectedSeatIds.value.push(seatId)
    }
}

const seatSelectionStatus = computed(() => {
    const selected = selectedSeatIds.value.length
    const available = props.availableToUser
    
    if (selected === 0) return { color: 'default', text: 'No seats selected' }
    if (selected === available) return { color: 'warning', text: 'Maximum seats selected' }
    return { color: 'primary', text: `${selected}/${available} seats selected` }
})

onMounted(() => {
    // Remove any seats that are already taken from the selectedSeats array
    selectedSeatIds.value = selectedSeatIds.value.filter((seat) => !props.takenSeats.includes(seat))
})

function showToast(message) {
    // This will be handled by Vant's toast component
    if (window.vant) {
        window.vant.showToast(message)
    }
}
</script>

<template>
  <Head title="Create Booking" />
  
  <!-- Mobile Seat Selection Page -->
  <div class="booking-page">
    <!-- Navigation Header -->
    <van-nav-bar 
      :title="event.name"
      left-text="Back"
      left-arrow
      @click-left="$inertia.get(route('events.index'))"
    >
      <template #right>
        <van-icon 
          name="info-o" 
          @click="showInstructions = true"
        />
      </template>
    </van-nav-bar>
    
    <!-- Seat Selection Status -->
    <div class="selection-status">
      <van-notice-bar
        :color="seatSelectionStatus.color === 'warning' ? '#ff976a' : '#1989fa'"
        :background="seatSelectionStatus.color === 'warning' ? '#fff7cc' : '#ecf9ff'"
        :text="seatSelectionStatus.text"
      />
    </div>
    
    <!-- Event Info -->
    <van-cell-group inset>
      <van-cell 
        title="Event"
        :value="event.name"
        icon="calendar-o"
      />
      <van-cell 
        title="Room"
        :value="event.room.name"
        icon="location-o"
      />
      <van-cell 
        title="Date & Time"
        :value="dayjs(event.starts_at).format('MMM DD, YYYY - HH:mm')"
        icon="clock-o"
      />
    </van-cell-group>
    
    
    <!-- Floor Plan Viewer -->
    <div class="floor-plan-container">
      <FloorPlanViewer
        :room="floorPlanData.room"
        :blocks="floorPlanData.blocks"
        :layout-config="floorPlanData.layoutConfig"
        :selected-seats="selectedSeats"
        @seat-selected="onSeatSelected"
        @seat-deselected="onSeatDeselected"
      />
    </div>
    
    <!-- Fixed Bottom Action -->
    <div class="bottom-action">
      <div class="action-content">
        <div class="selection-summary">
          <span class="seats-count">{{ selectedSeatIds.length }} seats selected</span>
          <span class="seats-limit">Max: {{ availableToUser }}</span>
        </div>
        
        <Link 
          :href="route('bookings.create', {event: event.id})"
          :data="{seats: selectedSeatIds, verifyBooking: 1}" 
          as="button" 
          type="submit"
        >
          <van-button 
            type="primary" 
            size="large" 
            block
            :disabled="selectedSeats.length === 0"
            icon="checked"
          >
            Continue Booking
          </van-button>
        </Link>
      </div>
    </div>
    
    <!-- Instructions Popup -->
    <van-popup 
      v-model:show="showInstructions" 
      position="bottom" 
      :style="{ height: '40%' }"
    >
      <div class="instructions-popup">
        <div class="popup-header">
          <h3>How to Select Seats</h3>
          <van-icon 
            name="cross" 
            @click="showInstructions = false"
          />
        </div>
        
        <div class="instructions-content">
          <van-cell-group>
            <van-cell 
              title="Navigate"
              label="Use pinch gestures to zoom and drag to pan around the venue. Double-tap to reset zoom."
              icon="gesture"
            />
            <van-cell 
              title="Select Seats"
              label="Tap available seats to select them"
              icon="add-o"
            />
            <van-cell 
              :title="`Maximum ${availableToUser} seats`"
              label="You can select up to this many seats per event"
              icon="warning-o"
            />
          </van-cell-group>
          
          <div class="legend">
            <h4>Seat Legend</h4>
            <div class="legend-items">
              <div class="legend-item">
                <div class="seat-example free"></div>
                <span>Available</span>
              </div>
              <div class="legend-item">
                <div class="seat-example selected"></div>
                <span>Selected</span>
              </div>
              <div class="legend-item">
                <div class="seat-example taken"></div>
                <span>Taken</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<style scoped>
.booking-page {
  height: 100vh;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  background-color: #f7f8fa;
}

.selection-status {
  flex-shrink: 0;
}

.seat-map-container,
.floor-plan-container {
  flex: 1;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  padding: 0;
  background: #f8f9fa;
}

.stage-indicator {
  background: #6c757d;
  color: white;
  text-align: center;
  padding: 12px;
  margin-bottom: 24px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 16px;
}

.blocks-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.block {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.block-header {
  font-weight: 600;
  font-size: 16px;
  color: #323233;
  margin-bottom: 12px;
  text-align: center;
}

.row {
  margin-bottom: 16px;
}

.row:last-child {
  margin-bottom: 0;
}

.row-label {
  font-size: 12px;
  color: #646566;
  margin-bottom: 8px;
  font-weight: 500;
}

.seats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
}

.bottom-action {
  flex-shrink: 0;
  background: white;
  border-top: 1px solid #ebedf0;
  padding: 16px;
  padding-bottom: calc(16px + env(safe-area-inset-bottom));
}

.action-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.selection-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
}

.seats-count {
  font-weight: 600;
  color: #323233;
}

.seats-limit {
  color: #969799;
}

.instructions-popup {
  padding: 20px;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.popup-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.popup-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}

.instructions-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.legend h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
}

.legend-items {
  display: flex;
  justify-content: space-around;
  gap: 16px;
}

.legend-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.seat-example {
  width: 32px;
  height: 32px;
  border: 1px solid #dcdee0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  border-radius: 4px;
}

.seat-example.free {
  background-color: white;
}

.seat-example.selected {
  background-color: #07c160;
  color: white;
}

.seat-example.taken {
  background: repeating-linear-gradient(
    45deg,
    #afb7de,
    #afb7de 5px,
    #999ec0 5px,
    #999ec0 10px
  );
}

/* Vant component customizations */
:deep(.van-cell-group--inset) {
  margin: 12px 16px;
  border-radius: 12px;
}

:deep(.van-notice-bar) {
  padding: 12px 16px;
}

:deep(.van-button--disabled) {
  background-color: #c8c9cc !important;
  border-color: #c8c9cc !important;
}
</style>
