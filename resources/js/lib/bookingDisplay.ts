// Shared display helpers for admin booking rows (Dashboard "Recent Bookings" + EventShow booking table).
import type { Booking } from '@/types/booking'

// Name of the guest the seat/ticket is for (not necessarily who made the booking).
export function getGuestName(booking: Booking): string {
  if (booking.name) return booking.name
  if (booking.user && booking.user.name) return booking.user.name
  return 'Unknown'
}

// 'User', 'Admin', or the raw booking.type - who/what created the booking.
export function getBookerType(booking: Booking): string | undefined {
  if (booking.user) return 'User'
  if (booking.type === 'admin') return 'Admin'
  return booking.type
}

// Name of whoever created the booking (pairs with getBookerType), e.g. the
// account name for self-service bookings or the staff name for admin-created ones.
export function getBookerName(booking: Booking): string | null {
  if (booking.user && booking.user.name) return booking.user.name
  if (booking.created_by_name) return booking.created_by_name
  return null
}

export function getSeatInfo(booking: Booking): string {
  if (!booking.seat) return 'N/A'
  return `${booking.seat.row.block.name} - ${booking.seat.row.name} - Seat ${booking.seat.label}`
}

