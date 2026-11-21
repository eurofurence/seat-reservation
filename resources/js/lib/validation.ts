// Bookings
export const ATTENDEE_NAME_MAX = 24
export const BOOKING_COMMENT_MAX = 255
export const BOOKING_CODE_LENGTH = 3

// Bookings admin
export const GUEST_NAME_MAX = 255
export const ADMIN_COMMENT_MAX = 1000

// Events
export const EVENT_NAME_MAX = 255
export const MAX_TICKETS_MIN = 1

// Empty/null max_tickets means "unlimited" and is always valid; otherwise it must be a whole
// number no smaller than MAX_TICKETS_MIN.
export function isValidMaxTickets(value: string | number | null): boolean {
  if (value === '' || value === null) return true
  const num = Number(value)
  return Number.isInteger(num) && num >= MAX_TICKETS_MIN
}

// Rooms and layout
export const ROOM_NAME_MAX = 255
export const BLOCK_NAME_MAX = 255
export const ROW_SEAT_COUNT_MIN = 1
export const ROW_SEAT_COUNT_MAX = 100
