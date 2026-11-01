// Runnable regression check for toast replacement (no test framework in this repo — plain asserts).
// Run: node --experimental-strip-types resources/js/Components/ui/toast/use-toast.check.ts
import assert from "node:assert/strict"
import { useToast } from "./use-toast.ts"

const { toasts, toast, success, info, error, warning, removeToast } = useToast()
const titles = () => toasts.value.map(t => t.title)

// Persistent and attention-grabbing toasts survive a new info/success toast.
error("Error")
warning("Warning")
toast({ title: "Sticky", duration: 0 })

// A positive-duration default toast replaces the previous one instead of stacking.
success("First")
info("Second")
assert.deepEqual(titles(), ["Error", "Warning", "Sticky", "Second"])

success("Third")
assert.deepEqual(titles(), ["Error", "Warning", "Sticky", "Third"])

// Adding an error/warning never clears an info/success toast.
error("Error 2")
assert.deepEqual(titles(), ["Error", "Warning", "Sticky", "Third", "Error 2"])

// removeToast empties the shared store.
for (const t of [...toasts.value]) removeToast(t.id)
assert.equal(toasts.value.length, 0)

console.log("use-toast checks passed")
