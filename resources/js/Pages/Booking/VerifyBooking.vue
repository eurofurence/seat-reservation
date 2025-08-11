<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3"
import Layout from "@/Layouts/Layout.vue"
import dayjs from "dayjs"
import { reactive, ref } from "vue"

defineOptions({layout: Layout})

const props = defineProps({
    event: Object,
    seats: Array,
    seatsFalltrough: Array
})

// Create Form Model with seat id's as index
const formModel = reactive(
    Object.fromEntries(
        props.seats.map((seat) => [seat.id, {name: '', comment: '', seat_id: seat.id}]
        )
    ))

function copyNameToAllSeats(text) {
    for (const [key, value] of Object.entries(formModel)) {
        value.name = text
    }
    showSuccessToast('Name copied to all seats')
}

function copyCommentToAllSeats(text) {
    for (const [key, value] of Object.entries(formModel)) {
        value.comment = text
    }
    showSuccessToast('Comment copied to all seats')
}

const form = useForm({
    'seats': formModel
})

const isSubmitting = ref(false)

function confirmBooking() {
    if (isSubmitting.value) return
    
    isSubmitting.value = true
    form.seats = formModel
    form.post(route('bookings.store', {event: props.event.id}), {
        onFinish: () => {
            isSubmitting.value = false
        }
    })
}

function showSuccessToast(message) {
    // Vant toast integration would go here
    console.log(message)
}

function getSeatDisplayName(seat) {
    return `${seat.row.block.name} - Row ${seat.row.name} - Seat ${seat.name}`
}
</script>

<template>
  <Head title="Confirm Booking" />
  
  <!-- Mobile Booking Verification Page -->
  <div class="verify-page">
    <!-- Navigation Header -->
    <van-nav-bar 
      title="Confirm Booking"
      left-text="Back"
      left-arrow
      @click-left="$inertia.get(route('bookings.create', {event: event.id}), {seats: seatsFalltrough, verifyBooking: 0})"
    />
    
    <!-- Event Information -->
    <van-cell-group inset>
      <van-cell 
        title="Event"
        :value="event.name"
        icon="calendar-o"
      />
      <van-cell 
        title="Date & Time"
        :value="dayjs(event.starts_at).format('MMM DD, YYYY - HH:mm')"
        icon="clock-o"
      />
      <van-cell 
        title="Venue"
        :value="event.room.name"
        icon="location-o"
      />
    </van-cell-group>
    
    <!-- Error Display -->
    <van-notice-bar
      v-if="form.errors.seats"
      type="danger"
      :text="form.errors.seats"
      left-icon="warning-o"
    />
    
    <!-- Form Content -->
    <form @submit.prevent="confirmBooking" class="form-content">
      <!-- Selected Seats -->
      <div class="seats-section">
        <h3 class="section-title">Selected Seats ({{ seats.length }})</h3>
        
        <div v-for="(seat, index) in seats" :key="seat.id" class="seat-form">
          <!-- Seat Information -->
          <van-cell-group>
            <van-cell 
              :title="getSeatDisplayName(seat)"
              icon="location"
              :border="false"
            />
          </van-cell-group>
          
          <!-- Booking Details Form -->
          <van-cell-group>
            <van-field
              v-model="formModel[seat.id].name"
              label="Name"
              placeholder="Enter name for reservation"
              required
              :rules="[{ required: true, message: 'Name is required' }]"
            >
              <template #button>
                <van-button 
                  size="small" 
                  type="primary" 
                  plain
                  @click="copyNameToAllSeats(formModel[seat.id].name)"
                  :disabled="!formModel[seat.id].name"
                >
                  Copy to All
                </van-button>
              </template>
            </van-field>
            
            <van-field
              v-model="formModel[seat.id].comment"
              label="Comment"
              placeholder="Additional notes (optional)"
              type="textarea"
              rows="2"
              autosize
            >
              <template #button>
                <van-button 
                  size="small" 
                  type="primary" 
                  plain
                  @click="copyCommentToAllSeats(formModel[seat.id].comment)"
                  :disabled="!formModel[seat.id].comment"
                >
                  Copy to All
                </van-button>
              </template>
            </van-field>
          </van-cell-group>
        </div>
      </div>
      
      <!-- Fixed Bottom Action -->
      <div class="bottom-action">
        <div class="action-content">
          <div class="booking-summary">
            <span class="summary-text">
              Booking {{ seats.length }} seat{{ seats.length > 1 ? 's' : '' }}
            </span>
          </div>
          
          <van-button 
            type="primary" 
            size="large" 
            block
            native-type="submit"
            :loading="isSubmitting"
            loading-text="Creating booking..."
            icon="checked"
          >
            Confirm Booking
          </van-button>
        </div>
      </div>
    </form>
  </div>
</template>

<style scoped>
.verify-page {
  min-height: 100vh;
  min-height: 100dvh;
  background-color: #f7f8fa;
  display: flex;
  flex-direction: column;
}

.form-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding-bottom: 100px; /* Space for fixed bottom action */
}

.seats-section {
  flex: 1;
  padding: 12px 0;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #323233;
  padding: 0 16px 12px 16px;
  margin: 0;
}

.seat-form {
  margin-bottom: 16px;
}

.seat-form:last-child {
  margin-bottom: 0;
}

.bottom-action {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  border-top: 1px solid #ebedf0;
  padding: 16px;
  padding-bottom: calc(16px + env(safe-area-inset-bottom));
  z-index: 100;
}

.action-content {
  max-width: 414px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.booking-summary {
  text-align: center;
}

.summary-text {
  font-size: 14px;
  color: #646566;
  font-weight: 500;
}

/* Vant component customizations */
:deep(.van-cell-group--inset) {
  margin: 0 16px;
  border-radius: 12px;
}

:deep(.van-cell-group) {
  margin-bottom: 12px;
}

:deep(.van-field__label) {
  font-weight: 500;
  min-width: 60px;
}

:deep(.van-field__control) {
  font-size: 16px;
}

:deep(.van-button--small.van-button--plain) {
  padding: 0 8px;
  height: 28px;
  font-size: 12px;
}

:deep(.van-notice-bar--danger) {
  margin: 12px 16px;
  border-radius: 8px;
}

@media (max-width: 414px) {
  .action-content {
    max-width: 100%;
  }
}
</style>
