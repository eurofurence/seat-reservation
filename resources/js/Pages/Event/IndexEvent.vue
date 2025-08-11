<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Layout from "@/Layouts/Layout.vue"
import dayjs from "dayjs"

defineOptions({layout: Layout})

defineProps({
    events: Array
})

const getSeatAvailabilityStatus = (seatsLeft) => {
  if (seatsLeft === 0) return { color: '#ee0a24', text: 'Sold Out' }
  if (seatsLeft < 10) return { color: '#ff976a', text: 'Few Left' }
  return { color: '#07c160', text: 'Available' }
}

const formatDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, HH:mm')
}

const formatDeadline = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, YYYY - HH:mm')
}
</script>

<template>
  <Head title="Events" />
  
  <!-- Mobile Event Listing Page -->
  <div class="events-page">
    <!-- Navigation Header -->
    <van-nav-bar 
      title="Select Event"
      left-text="Back"
      left-arrow
      @click-left="$inertia.get(route('dashboard'))"
    />
    
    <!-- Page Content -->
    <div class="events-content">
      <!-- Page Description -->
      <van-notice-bar
        left-icon="volume-o"
        text="Select an event to book your seats"
        color="#1989fa"
        background="#ecf9ff"
      />
      
      <!-- Events List -->
      <div v-if="events.length" class="events-list">
        <van-cell-group>
          <Link 
            v-for="event in events" 
            :key="event.id"
            :href="route('bookings.create', {event: event.id})"
            class="event-link"
          >
            <van-cell 
              :title="event.name"
              :label="`Reservation deadline: ${formatDeadline(event.reservation_ends_at)}`"
              is-link
            >
              <template #value>
                <div class="event-info">
                  <!-- Room and Time -->
                  <div class="event-details">
                    <van-tag type="default" size="medium">
                      {{ event.room.name }}
                    </van-tag>
                    <span class="event-time">
                      {{ formatDateTime(event.starts_at) }}
                    </span>
                  </div>
                  
                  <!-- Seat Availability -->
                  <div class="seat-availability">
                    <van-tag 
                      :color="getSeatAvailabilityStatus(event.seats_left).color"
                      size="medium"
                    >
                      {{ event.seats_left }} seats left
                    </van-tag>
                  </div>
                </div>
              </template>
            </van-cell>
          </Link>
        </van-cell-group>
      </div>
      
      <!-- Empty State -->
      <div v-else class="empty-state">
        <van-empty 
          image="search"
          description="No events available"
        >
          <template #description>
            <p class="empty-text">
              There are currently no events available for booking.
              Please check back later.
            </p>
          </template>
        </van-empty>
      </div>
    </div>
  </div>
</template>

<style scoped>
.events-page {
  min-height: 100vh;
  min-height: 100dvh;
  background-color: #f7f8fa;
}

.events-content {
  padding-bottom: 20px;
}

.events-list {
  margin-top: 12px;
}

.event-link {
  text-decoration: none;
  color: inherit;
}

.event-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  min-width: 0;
}

.event-details {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.event-time {
  font-size: 12px;
  color: #646566;
}

.seat-availability {
  display: flex;
  justify-content: flex-end;
}

.empty-state {
  padding: 40px 16px;
}

.empty-text {
  color: #646566;
  font-size: 14px;
  line-height: 1.4;
  text-align: center;
}

/* Vant component customizations */
:deep(.van-cell) {
  padding: 16px;
}

:deep(.van-cell__title) {
  font-weight: 600;
  font-size: 16px;
  color: #323233;
}

:deep(.van-cell__label) {
  font-size: 12px;
  color: #969799;
  margin-top: 4px;
}

:deep(.van-cell__value) {
  flex: none;
  margin-left: 12px;
}

:deep(.van-tag) {
  border-radius: 6px;
  font-size: 11px;
  padding: 2px 6px;
}

:deep(.van-notice-bar) {
  padding: 12px 16px;
  border-radius: 0;
}
</style>
