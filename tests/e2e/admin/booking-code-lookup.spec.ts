import { test, expect } from '@playwright/test'

// Full cross-role pickup flow: a regular user completes an online booking and
// receives a 3-character pickup code, then an admin pastes that code into the
// dashboard "Quick Booking Lookup" field, gets taken to the event with the
// table filtered to that booking, and marks it as picked up. Exercises
// AdminController::lookupBookingCode -> EventAdminController::show (bookingcode
// filter) -> TogglePickupController end to end - the one chain the isolated
// booking-flow / manual-booking specs never join, since manual admin bookings
// have no code and are found by name instead.
//
// The default `page` is the ADMIN session (admin project storageState). The
// user booking runs in a separate context loaded from the user session file so
// the two roles don't share one cookie jar.

const userFile = 'tests/e2e/.auth/user.json'

test('admin looks up a user booking by its code and marks it picked up', async ({ page, browser }) => {
  // --- User side: complete a real online booking and grab the pickup code. ---
  // "Row 1 - 3" on the shared open event is untouched by any other spec (the
  // user's 2-seat cap here is only otherwise spent by user/booking-flow's Row 1-1).
  const userContext = await browser.newContext({ storageState: userFile })
  const userPage = await userContext.newPage()

  await userPage.goto('/events')
  await userPage.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(userPage).toHaveURL(/\/bookings\/create/)

  await userPage.locator('button[title="Block A - Row 1 - 3"]').click()
  await userPage.getByRole('button', { name: 'Continue to Details' }).first().click()
  await expect(userPage.locator('#name-0')).toBeVisible()
  await userPage.locator('#name-0').fill('Pickup Lookup Guest')
  await userPage.getByRole('button', { name: 'Confirm Booking' }).click()

  await expect(userPage).toHaveURL(/\/bookings\/confirmed\//)
  const code = (await userPage.locator('.font-mono').first().innerText()).trim()
  expect(code).toMatch(/^[A-Z0-9]{3}$/)

  await userContext.close()

  // --- Admin side: look the code up from the dashboard and mark picked up. ---
  await page.goto('/admin')

  await page.locator('#booking_code').fill(code)
  await page.getByRole('button', { name: 'Lookup' }).click()

  // Redirects to the event page with the table filtered to just this code.
  await expect(page).toHaveURL(new RegExp(`/admin/events/\\d+\\?bookingcode=${code}`))

  const row = page.getByRole('row', { name: /Pickup Lookup Guest/ })
  await expect(row).toBeVisible()

  // Going unpicked -> picked up needs no confirmation (only reverting does), so
  // checking the box should persist and re-render as checked after the reload.
  const checkbox = row.locator('input[type="checkbox"]')
  await expect(checkbox).not.toBeChecked()
  await checkbox.check()
  await expect(checkbox).toBeChecked()
})

test('looking up a non-existent booking code shows an inline error', async ({ page }) => {
  await page.goto('/admin')

  // "ZZZ" is a valid 3-char shape but seeded fixtures never generate it.
  await page.locator('#booking_code').fill('ZZZ')
  await page.getByRole('button', { name: 'Lookup' }).click()

  await expect(page.getByText('No booking found with this code.')).toBeVisible()
  await expect(page).toHaveURL(/\/admin$/)
})
