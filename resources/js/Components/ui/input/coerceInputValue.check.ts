// Runnable regression check for coerceInputValue (no test framework in this repo — plain asserts).
// Run: node --experimental-strip-types resources/js/Components/ui/input/coerceInputValue.check.ts
import assert from "node:assert/strict"
import { coerceInputValue } from "./coerceInputValue.ts"

// Plain text: passed through untouched.
assert.equal(coerceInputValue("hello", {}), "hello")

// trim modifier.
assert.equal(coerceInputValue("  hi  ", { trim: true }), "hi")

// number modifier: valid numeric string is coerced to a number.
assert.equal(coerceInputValue("42", { number: true }), 42)

// number modifier: non-numeric input is left as the original string (not NaN/dropped).
assert.equal(coerceInputValue("abc", { number: true }), "abc")

// number modifier: empty string is left as "" rather than becoming NaN.
assert.equal(coerceInputValue("", { number: true }), "")

// trim + number combined.
assert.equal(coerceInputValue("  7  ", { trim: true, number: true }), 7)

console.log("coerceInputValue: all checks passed")
