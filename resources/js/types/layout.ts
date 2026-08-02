export type Rotation = 0 | 90 | 180 | 270
export type Orientation = 'row' | 'column'
export type MarkerType = 'entrance' | 'comment'
export type BlockId = number | string
export type Alignment = 'left' | 'center' | 'right'

// Fields getGuestName/getBookerType/getBookerName/getSeatInfo (resources/js/lib/bookingDisplay.ts) read.
export interface Booking {
  name?: string | null
  type?: string
  created_by_name?: string | null
  user?: { name: string } | null
  seat?: { label: string; row: { name: string; block: { name: string } } } | null
}

export interface Seat {
  id: number
  label: string
  name: string
}

export interface Row {
  id: number
  name: string
  alignment?: Alignment
  seats: Seat[]
}

export interface LayoutBlock {
  id: BlockId
  name: string
  rotation?: number
  position_x: number
  position_y: number
  colspan?: number
  rowspan?: number
  rows?: Row[]
}

export interface PropRow {
  seats_count?: number
  custom_seat_count?: number | null
  alignment?: string
}

export interface PropBlock {
  id: BlockId
  name: string
  position_x: number
  position_y: number
  rotation?: number
  colspan?: number
  rowspan?: number
  rows?: PropRow[]
}

export interface PropStageBlock {
  id: BlockId
  name: string
  position_x: number
  position_y: number
  colspan?: number
  rowspan?: number
}

export interface PropMarkerBlock {
  id: BlockId
  type: MarkerType
  name: string
  position_x: number
  position_y: number
  rotation?: number
  colspan?: number
  rowspan?: number
}

export interface FormRow {
  rowNumber: number
  seatCount: number
  isCustom: boolean
  alignment: string
}

export interface FormBlock {
  id: BlockId
  name: string
  position_x: number
  position_y: number
  rotation: number
  colspan: number
  rowspan: number
  rows: FormRow[]
}

export interface FormStageBlock {
  id: BlockId
  name: string
  position_x: number
  position_y: number
  colspan: number
  rowspan: number
}

export interface FormMarkerBlock {
  id: BlockId
  type: MarkerType
  name: string
  position_x: number
  position_y: number
  orientation?: Orientation
  rotation?: number
  colspan: number
  rowspan: number
}
