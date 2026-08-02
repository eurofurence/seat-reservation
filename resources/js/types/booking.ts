export type BookingType = 'online' | 'admin'

export interface BookingBlock {
  name: string
}

export interface BookingRow {
  name: string
  block: BookingBlock
}

export interface BookingSeat {
  label: string
  row: BookingRow
}

export interface BookingUser {
  name: string
}

export interface BookingEvent {
  id: number
  name: string
  starts_at?: string | null
}

export interface Booking {
  id: number
  event_id: number
  name?: string | null
  type?: BookingType
  created_by_name?: string | null
  comment?: string | null
  booking_code?: string | null
  picked_up_at?: string | null
  created_at?: string | null
  user?: BookingUser | null
  seat?: BookingSeat | null
  event?: BookingEvent | null
}
