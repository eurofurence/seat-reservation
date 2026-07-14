import { test, expect } from '@playwright/test'

// Happy-path seat booking flow for a regular (non-admin) user, against the
// deterministic "E2E Test Event" fixture created by `php artisan e2e:seed`.
// Run the seed command before this spec (see package.json "pretest:e2e").
//
// This is the only spec in the suite that completes a real booking on "E2E
// Test Event" — the per-user cap there is 2 seats, and other tests reuse
// this same dev-login user, so keep it that way rather than adding more
// full bookings against this event elsewhere.

test('user can browse events, pick a seat and complete a booking', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()

  await expect(page).toHaveURL(/\/bookings\/create/)

  // Seat button title is "<Block> - <Row> - <Seat label>", from SeatBlock.vue
  const seat = page.locator('button[title="Block A - Row 1 - 1"]')
  await seat.click()
  await expect(page.getByText('1 / ')).toBeVisible()

  await page.getByRole('button', { name: 'Continue to Details' }).first().click()
  await expect(page.locator('#name-0')).toBeVisible()

  await page.locator('#name-0').fill('Playwright Tester')
  await page.getByRole('button', { name: 'Confirm Booking' }).click()

  await expect(page).toHaveURL(/\/bookings\/confirmed\//)
  const code = await page.locator('.font-mono').first().innerText()
  expect(code.trim()).toMatch(/^[A-Z0-9]{3}$/)

  // Reloading the seat picker for the same event shows the booked seat as disabled.
  await page.goto(page.url().replace(/\/bookings\/confirmed\/.*/, '/bookings/create'))
  await expect(seat).toBeDisabled()

  // Booking should now show up in the user's booking list, with its code.
  await page.goto('/bookings')
  await expect(page.getByText('E2E Test Event')).toBeVisible()
  await expect(page.getByText('Playwright Tester')).toBeVisible()
  await expect(page.getByText(code.trim())).toBeVisible()
})

