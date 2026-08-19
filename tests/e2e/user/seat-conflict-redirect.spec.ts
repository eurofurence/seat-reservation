import { test, expect } from '@playwright/test'

// Regression test for the "redirect loop when seats get taken by another user
// mid-booking" fix. BookingController::store() used to throw a
// ValidationException inside the DB transaction on a seat conflict (invisible
// to the flash-based ToastProvider) and/or redirect via back()/referer, which
// could loop back onto a URL whose own query string still referenced the
// now-taken seat. It now sets a plain `error` flash and redirects to the bare
// bookings.create URL.

test('submitting a booking after the seat was taken by someone else redirects cleanly with an error, not a loop', async ({ page, browser }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  const seat = page.locator('button[title="Block A - Row 1 - 5"]')
  await seat.click()
  await page.getByRole('button', { name: 'Continue to Details' }).first().click()
  await expect(page.locator('#name-0')).toBeVisible()
  await page.locator('#name-0').fill('Conflict Test')

  // Someone else (an admin, via the manual-booking panel) grabs the same seat
  // on the same event before this user submits.
  const adminContext = await browser.newContext({ storageState: 'tests/e2e/.auth/admin.json' })
  const adminPage = await adminContext.newPage()
  await adminPage.goto('/admin/events')
  await adminPage
    .getByRole('row', { name: /E2E Test Event/ })
    .getByRole('button', { name: 'View' })
    .click()
  await adminPage.waitForURL(/\/admin\/events\/\d+/)
  await adminPage.locator('button[title="Block A - Row 1 - 5"]').click()
  await adminPage.locator('#guestName').fill('Conflict Filler')
  await adminPage.getByRole('button', { name: /Book 1 Seat/ }).click()
  await expect(adminPage.getByText('Successfully booked 1 seat for Conflict Filler')).toBeVisible()
  await adminContext.close()

  await page.getByRole('button', { name: 'Confirm Booking' }).click()

  // A single clean redirect back to the (query-string-free) seat picker, not a
  // navigation loop and not stuck on the details step or an error page.
  await expect(page).toHaveURL(/\/events\/\d+\/bookings\/create$/)
  await expect(page.getByText(/just booked by someone else/i)).toBeVisible()

  // Page is still usable afterwards - the now-taken seat shows as booked/disabled.
  await expect(seat).toBeDisabled()
})
