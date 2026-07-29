// Runnable regression check for layoutGrid (no test framework in this repo — plain asserts).
// Run: node --experimental-strip-types resources/js/lib/layoutGrid.check.ts
import assert from "node:assert/strict"
import { rectsOverlap, blockRect, rectOnGrid, canPlace, rectUnobstructed, clampSpan } from "./layoutGrid.ts"

const COLS = 12
const ROWS = 8

// rectsOverlap: overlapping rects.
assert.equal(rectsOverlap({ id: 1, x: 0, y: 0, w: 2, h: 2 }, { id: 2, x: 1, y: 1, w: 2, h: 2 }), true)

// rectsOverlap: adjacent (touching edge) rects do not overlap.
assert.equal(rectsOverlap({ id: 1, x: 0, y: 0, w: 2, h: 2 }, { id: 2, x: 2, y: 0, w: 2, h: 2 }), false)

// blockRect: colspan/rowspan below 1 (null/undefined/0) clamp to a 1x1 footprint.
assert.deepEqual(blockRect({ id: 1, colspan: null, rowspan: 0 }, 3, 4), { id: 1, x: 3, y: 4, w: 1, h: 1 })
assert.deepEqual(blockRect({ id: 1, colspan: 3, rowspan: 2 }, 0, 0), { id: 1, x: 0, y: 0, w: 3, h: 2 })

// rectOnGrid: within bounds.
assert.equal(rectOnGrid({ id: 1, x: 0, y: 0, w: COLS, h: ROWS }, COLS, ROWS), true)

// rectOnGrid: a span pushes the rect past the right/bottom edge.
assert.equal(rectOnGrid({ id: 1, x: COLS - 1, y: 0, w: 2, h: 1 }, COLS, ROWS), false)
assert.equal(rectOnGrid({ id: 1, x: 0, y: ROWS - 1, w: 1, h: 2 }, COLS, ROWS), false)

// rectOnGrid: negative origin is off-grid.
assert.equal(rectOnGrid({ id: 1, x: -1, y: 0, w: 1, h: 1 }, COLS, ROWS), false)

// rectUnobstructed: ignores a rect with the same id (self), rejects a colliding neighbor.
const others = [{ id: 1, x: 0, y: 0, w: 2, h: 2 }, { id: 2, x: 5, y: 5, w: 1, h: 1 }]
assert.equal(rectUnobstructed({ id: 1, x: 0, y: 0, w: 2, h: 2 }, others), true)
assert.equal(rectUnobstructed({ id: 3, x: 1, y: 1, w: 1, h: 1 }, others), false)

// canPlace: off-grid rect is rejected even with no obstacles.
assert.equal(canPlace({ id: 1, x: COLS, y: 0, w: 1, h: 1 }, COLS, ROWS, []), false)

// canPlace: on-grid and unobstructed.
assert.equal(canPlace({ id: 1, x: 0, y: 0, w: 1, h: 1 }, COLS, ROWS, []), true)

// canPlace: on-grid but colliding with another block.
assert.equal(canPlace({ id: 1, x: 0, y: 0, w: 1, h: 1 }, COLS, ROWS, [{ id: 2, x: 0, y: 0, w: 1, h: 1 }]), false)

// clampSpan: clamps to [1, max] and falls back to 1 for non-numeric input.
assert.equal(clampSpan(0, 12), 1)
assert.equal(clampSpan(99, 12), 12)
assert.equal(clampSpan("abc", 12), 1)
assert.equal(clampSpan(3.9, 12), 3)

console.log("layoutGrid: all checks passed")
