// Runnable regression check for validation (no test framework in this repo — plain asserts).
// Run: node --experimental-strip-types resources/js/lib/validation.check.ts
import assert from "node:assert/strict"
import { isValidMaxTickets } from "./validation.ts"

// Empty stays valid (unlimited tickets).
assert.equal(isValidMaxTickets(''), true)

// Zero is below the minimum.
assert.equal(isValidMaxTickets(0), false)

// One is the minimum and is valid.
assert.equal(isValidMaxTickets(1), true)

// Fractional values are rejected even though they pass the minimum check.
assert.equal(isValidMaxTickets(1.5), false)

console.log("validation.check.ts: all assertions passed")
