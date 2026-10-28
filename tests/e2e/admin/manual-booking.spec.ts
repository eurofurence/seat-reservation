import { test, expect } from '@playwright/test'

// Admin panel manual booking (EventAdminController::manualBooking / togglePickup) -
// a deliberately separate code path from customer-facing BookingController, which
// keeps bypassing booking-window/date guards on purpose (see the "no special
// treatment" comments in BookingController.php and admin/booking-parity.spec.ts
// for the customer-facing side, which no longer bypasses anything).

async function openEventShow(page: import('@playwright/test').Page, eventName: string) {
  await page.goto('/admin/events')
  await page
    .getByRole('row', { name: new RegExp(eventName) })
    .getByRole('button', { name: 'View' })
    .click()
  await page.waitForURL(/\/admin\/events\/\d+/)
}

test('manual booking still works on an event that has already started, and its success toast shows the actual guest name', async ({ page }) => {
  await openEventShow(page, 'E2E Ended Event')

  await page.locator('button[title="Block A - Row 1 - 2"]').click()
  await page.locator('#guestName').fill('Playwright Admin Guest')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()

  // Regression check: this toast used to interpolate $request->name (validated as
  // guest_name), so it always rendered "for " with no name at all.
  await expect(page.getByText('Successfully booked 1 seat for Playwright Admin Guest')).toBeVisible()
})

test('manual booking still works past the reservation deadline', async ({ page }) => {
  await openEventShow(page, 'E2E Closed Reservation Event')

  await page.locator('button[title="Block A - Row 1 - 2"]').click()
  await page.locator('#guestName').fill('Playwright Admin Guest 2')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()

  await expect(page.getByText('Successfully booked 1 seat for Playwright Admin Guest 2')).toBeVisible()
})

test('canceling the revert confirmation leaves a picked-up ticket picked up', async ({ page }) => {
  await openEventShow(page, 'E2E Test Event')

  // Book a fresh seat so this spec has its own booking to toggle, independent of
  // other specs (admin manual bookings don't count against the 2-seat user cap).
  // "Row 2-1" is otherwise untouched by any user/*.spec.ts on this shared event.
  await page.locator('button[title="Block A - Row 2 - 1"]').click()
  await page.locator('#guestName').fill('Pickup Cancel Test')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()
  await expect(page.getByText('Successfully booked 1 seat for Pickup Cancel Test')).toBeVisible()

  const checkbox = page.getByRole('row', { name: /Pickup Cancel Test/ }).locator('input[type="checkbox"]')

  // Mark as picked up - no confirmation needed going from unpicked -> picked up.
  await checkbox.check()
  await expect(checkbox).toBeChecked()

  // Reverting (picked up -> unpicked) asks for confirmation. Declining it must
  // leave the checkbox checked (EventShow.vue's togglePickup manually undoes the
  // native checkbox toggle when the user declines).
  page.once('dialog', (dialog) => dialog.dismiss())
  await checkbox.click()
  await expect(checkbox).toBeChecked()
})

test('confirming the revert marks a picked-up ticket as unpicked', async ({ page }) => {
  await openEventShow(page, 'E2E Test Event')

  // "Row 2-2" is otherwise untouched by any user/*.spec.ts on this shared event.
  await page.locator('button[title="Block A - Row 2 - 2"]').click()
  await page.locator('#guestName').fill('Pickup Confirm Test')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()
  await expect(page.getByText('Successfully booked 1 seat for Pickup Confirm Test')).toBeVisible()

  const checkbox = page.getByRole('row', { name: /Pickup Confirm Test/ }).locator('input[type="checkbox"]')

  await checkbox.check()
  await expect(checkbox).toBeChecked()

  page.once('dialog', (dialog) => dialog.accept())
  await checkbox.click()
  await expect(checkbox).not.toBeChecked()
})

