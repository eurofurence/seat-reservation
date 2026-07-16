import { test, expect } from '@playwright/test'

// Seat-picker interaction edge cases on the "E2E Test Event" fixture (10 seats,
// 2 rows x 5). These specs only select/deselect seats or submit invalid data —
// none of them complete a real booking, so they don't consume this user's
// per-event 2-seat cap (see booking-guards.spec.ts for specs that do).

test('deselecting a selected seat removes it from the selection count', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  const seat = page.locator('button[title="Block A - Row 2 - 3"]')

  await expect(page.getByText(/^0 \/ /)).toBeVisible()
  await seat.click()
  await expect(page.getByText(/^1 \/ /)).toBeVisible()
  await seat.click()
  await expect(page.getByText(/^0 \/ /)).toBeVisible()
})

test('selecting more seats than allowed disables the continue button', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  // maxSeatsPerUser is capped at 2, so 3 distinct seats always exceeds it,
  // regardless of how many this user has already booked on this event.
  await page.locator('button[title="Block A - Row 2 - 4"]').click()
  await page.locator('button[title="Block A - Row 2 - 5"]').click()
  await page.locator('button[title="Block A - Row 1 - 3"]').click()

  await expect(page.getByRole('button', { name: 'Continue to Details' }).first()).toBeDisabled()
})

test('submitting booking details with a blank name is blocked client-side', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  await page.locator('button[title="Block A - Row 1 - 4"]').click()
  await page.getByRole('button', { name: 'Continue to Details' }).first().click()
  await expect(page.locator('#name-0')).toBeVisible()

  // ValidateBooking.vue checks names client-side and shows a native alert()
  // instead of posting when one is blank. Register the dialog handler
  // up-front (rather than awaiting waitForEvent alongside the click) since
  // the alert() blocks the page's main thread as part of the click itself.
  let dialogMessage = ''
  page.once('dialog', async (dialog) => {
    dialogMessage = dialog.message()
    await dialog.accept()
  })
  await page.getByRole('button', { name: 'Confirm Booking' }).click()
  await expect.poll(() => dialogMessage).toMatch(/provide names/i)

  // Still on the details step, no booking was created.
  await expect(page).toHaveURL(/\/bookings\/create/)
  await expect(page.locator('#name-0')).toBeVisible()
})

test('submitting a name longer than 24 characters is rejected server-side', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  await page.locator('button[title="Block A - Row 2 - 1"]').click()
  await page.getByRole('button', { name: 'Continue to Details' }).first().click()
  await expect(page.locator('#name-0')).toBeVisible()

  // 25 chars — one over the `seats.*.name` max:24 rule added in v0.0.36. The
  // client-side check only guards against blank names, so this reaches the
  // server and comes back as a validation error rather than a booking.
  await page.locator('#name-0').fill('X'.repeat(25))
  await page.getByRole('button', { name: 'Confirm Booking' }).click()

  // Inline error from ValidateBooking.vue's form.errors[`seats.0.name`]
  // (the same message also renders in the summary alert, hence .first()).
  await expect(page.getByText(/24 characters/i).first()).toBeVisible()

  // Still on the details step, no booking was created.
  await expect(page).toHaveURL(/\/bookings\/create/)
})
