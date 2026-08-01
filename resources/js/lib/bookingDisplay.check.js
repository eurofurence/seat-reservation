// Runnable regression check for bookingDisplay (no test framework in this repo — plain asserts).
// Run: node resources/js/lib/bookingDisplay.check.js
import assert from "node:assert/strict"
import { getGuestName, getBookerType, getBookerName, getSeatInfo } from "./bookingDisplay.js"

// getGuestName: booking.name wins when present.
assert.equal(getGuestName({ name: "Alice" }), "Alice")

// getGuestName: falls back to booking.user.name when no name.
assert.equal(getGuestName({ name: null, user: { name: "Bob" } }), "Bob")

// getGuestName: falls back to 'Unknown' when neither is present.
assert.equal(getGuestName({}), "Unknown")

// getBookerType: a user booking is 'User', regardless of type.
assert.equal(getBookerType({ user: { name: "Bob" }, type: "admin" }), "User")

// getBookerType: no user + type 'admin' is 'Admin'.
assert.equal(getBookerType({ user: null, type: "admin" }), "Admin")

// getBookerType: no user + other type returns the raw type.
assert.equal(getBookerType({ user: null, type: "online" }), "online")

// getBookerName: booking.user.name wins when present.
assert.equal(getBookerName({ user: { name: "Bob" }, created_by_name: "Staff" }), "Bob")

// getBookerName: falls back to created_by_name when no user.
assert.equal(getBookerName({ user: null, created_by_name: "Staff" }), "Staff")

// getBookerName: null when neither is present.
assert.equal(getBookerName({ user: null }), null)

// getSeatInfo: 'N/A' when no seat.
assert.equal(getSeatInfo({ seat: null }), "N/A")

// getSeatInfo: formats block/row/seat label when seat is present.
assert.equal(
  getSeatInfo({ seat: { label: "12", row: { name: "Row A", block: { name: "Block 1" } } } }),
  "Block 1 - Row A - Seat 12"
)

console.log("bookingDisplay: all checks passed")
