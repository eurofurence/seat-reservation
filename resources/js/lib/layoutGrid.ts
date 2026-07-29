// Pure grid-placement geometry shared by the room layout editor (Edit.vue).

export interface Rect {
  id: string | number
  x: number
  y: number
  w: number
  h: number
}

export const span = (value: number | null | undefined): number => (value != null && value >= 1 ? value : 1)

export const rectsOverlap = (a: Rect, b: Rect): boolean =>
  a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h

export const blockRect = (
  block: { id: Rect['id'], colspan?: number | null, rowspan?: number | null },
  x: number,
  y: number
): Rect => ({ id: block.id, x, y, w: span(block.colspan), h: span(block.rowspan) })

const inGrid = (x: number, y: number, cols: number, rows: number) => x >= 0 && x < cols && y >= 0 && y < rows

export const rectOnGrid = (rect: Rect, cols: number, rows: number): boolean =>
  inGrid(rect.x, rect.y, cols, rows) && inGrid(rect.x + rect.w - 1, rect.y + rect.h - 1, cols, rows)

export const rectUnobstructed = (rect: Rect, others: Rect[]): boolean =>
  others.filter(other => other.id !== rect.id).every(other => !rectsOverlap(rect, other))

export const canPlace = (rect: Rect, cols: number, rows: number, others: Rect[]): boolean =>
  rectOnGrid(rect, cols, rows) && rectUnobstructed(rect, others)

export const clampSpan = (value: string | number, max: number): number =>
  Math.min(max, Math.max(1, Math.floor(Number(value)) || 1))
