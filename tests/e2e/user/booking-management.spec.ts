import { test, expect } from '@playwright/test'

// User editing/canceling their own booking from the Manage Reservation page
// (BookingController::update/destroy) - booking-flow.spec.ts only creates and
// views a booking, never edits or cancels one.
//
// Reuses the "Playwright Tester" booking (Row 1-1 on "E2E Test Event") created
// by user/booking-flow.spec.ts: it's never picked up (picked-up bookings can no
// longer be modified/cancelled, see ShowBooking.vue's canModify), and nothing
// else in the suite depends on it still existing afterwards.

test('user edits their booking name, then cancels the reservation', async ({ page }) => {
  await page.goto('/bookings')
  await page.getByText('Playwright Tester').click()
  await expect(page).toHaveURL(/\/bookings\/\d+/)

  await page.locator('#name').fill('Playwright Tester Renamed')
  await page.getByRole('button', { name: 'Save Changes' }).click()

  await expect(page).toHaveURL(/\/bookings$/)
  await expect(page.getByText('Booking updated!')).toBeVisible()
  await expect(page.getByText('Playwright Tester Renamed')).toBeVisible()

  await page.getByText('Playwright Tester Renamed').click()
  await expect(page).toHaveURL(/\/bookings\/\d+/)

  page.once('dialog', (dialog) => dialog.accept())
  await page.getByRole('button', { name: 'Cancel Reservation' }).click()

  await expect(page).toHaveURL(/\/bookings$/)
  await expect(page.getByText('Booking cancelled!')).toBeVisible()
  await expect(page.getByText('Playwright Tester Renamed')).toBeHidden()
})
