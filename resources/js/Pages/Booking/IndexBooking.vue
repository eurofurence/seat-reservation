<script setup>
import { Head, Link } from "@inertiajs/vue3"
import Layout from "@/Layouts/Layout.vue"
import dayjs from "dayjs"

defineOptions({layout: Layout})

defineProps({
    bookings: Array
})

const formatDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, HH:mm')
}

const formatFullDateTime = (dateTime) => {
  return dayjs(dateTime).format('MMM DD, YYYY - HH:mm')
}

const getSeatInfo = (booking) => {
  return `${booking.seat.row.block.name} - Row ${booking.seat.row.name} - Seat ${booking.seat.name}`
}

const getBookingStatus = (booking) => {
  const now = dayjs()
  const eventStart = dayjs(booking.event.starts_at)
  const reservationEnd = dayjs(booking.event.reservation_ends_at)
  
  if (now.isAfter(eventStart)) {
    return { status: 'completed', color: '#323233', text: 'Event Completed' }
  }
  if (now.isAfter(reservationEnd)) {
    return { status: 'locked', color: '#ff976a', text: 'Reservation Locked' }
  }
  return { status: 'active', color: '#07c160', text: 'Active' }
}
</script>

<template>
  <Head title="My Bookings" />
  
  <!-- Mobile Bookings Dashboard -->
  <div class="bookings-page">
    <!-- Navigation Header with Logout -->
    <van-nav-bar title="My Reservations">
      <template #right>
        <Link method="post" :href="route('auth.logout')">
          <van-button type="primary" size="small" plain>
            Logout
          </van-button>
        </Link>
      </template>
    </van-nav-bar>
    
    <!-- Flash Message -->
    <van-notice-bar
      v-if="$page.props.flash.message"
      type="success"
      :text="$page.props.flash.message"
      left-icon="success"
    />
    
    <!-- Page Content -->
    <div class="page-content">
      <!-- Instructions -->
      <van-notice-bar
        left-icon="info-o"
        text="Tap any booking to view details or cancel"
        color="#1989fa"
        background="#ecf9ff"
      />
      
      <!-- Bookings List -->
      <div v-if="bookings.length" class="bookings-list">
        <van-cell-group>
          <Link 
            v-for="booking in bookings" 
            :key="booking.id"
            :href="route('bookings.show', {booking: booking.id, event: booking.event.id})"
            class="booking-link"
          >
            <van-cell is-link>
              <template #title>
                <div class="booking-header">
                  <div class="event-name">{{ booking.event.name }}</div>
                  <van-tag 
                    :color="getBookingStatus(booking).color"
                    size="small"
                    round
                  >
                    {{ getBookingStatus(booking).text }}
                  </van-tag>
                </div>
              </template>
              
              <template #label>
                <div class="booking-details">
                  <div class="detail-row">
                    <van-icon name="clock-o" />
                    <span>{{ formatDateTime(booking.event.starts_at) }}</span>
                  </div>
                  <div class="detail-row">
                    <van-icon name="location-o" />
                    <span>{{ booking.event.room.name }}</span>
                  </div>
                  <div class="detail-row">
                    <van-icon name="user-o" />
                    <span>{{ booking.name }}</span>
                  </div>
                </div>
              </template>
              
              <template #value>
                <div class="seat-info">
                  <div class="seat-location">{{ getSeatInfo(booking) }}</div>
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
          description="No reservations yet"
        >
          <template #description>
            <p class="empty-text">
              You haven't made any reservations yet.<br>
              Tap the button below to book your first seat!
            </p>
          </template>
        </van-empty>
      </div>
    </div>
    
    <!-- Fixed Bottom Action -->
    <div class="bottom-action">
      <div class="action-content">
        <Link :href="route('events.index')">
          <van-button 
            type="primary" 
            size="large" 
            block
            icon="add-o"
          >
            Make New Booking
          </van-button>
        </Link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bookings-page {
  min-height: 100vh;
  min-height: 100dvh;
  background-color: #f7f8fa;
  display: flex;
  flex-direction: column;
}

.page-content {
  flex: 1;
  padding-bottom: 100px; /* Space for fixed bottom action */
}

.bookings-list {
  margin-top: 12px;
}

.booking-link {
  text-decoration: none;
  color: inherit;
}

.booking-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.event-name {
  font-weight: 600;
  font-size: 16px;
  color: #323233;
  flex: 1;
  min-width: 0;
}

.booking-details {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #969799;
}

.detail-row .van-icon {
  font-size: 12px;
}

.seat-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.seat-location {
  font-size: 12px;
  color: #646566;
  text-align: right;
  line-height: 1.3;
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
}

/* Vant component customizations */
:deep(.van-nav-bar__right) {
  padding-right: 0;
}

:deep(.van-cell) {
  padding: 16px;
}

:deep(.van-cell__value) {
  flex: none;
  margin-left: 12px;
}

:deep(.van-tag) {
  border-radius: 10px;
  font-size: 10px;
  padding: 2px 6px;
  white-space: nowrap;
}

:deep(.van-notice-bar) {
  margin: 0;
  border-radius: 0;
}

:deep(.van-notice-bar + .van-notice-bar) {
  margin-top: 1px;
}

@media (max-width: 414px) {
  .action-content {
    max-width: 100%;
  }
}
</style>
