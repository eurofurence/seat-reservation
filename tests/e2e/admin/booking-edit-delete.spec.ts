import { test, expect } from '@playwright/test'

// Admin editing/deleting an existing booking row on the Event Show page
// (UpdateBookingController / DeleteBookingController), via EditBookingDialog
// and DeleteBookingDialog - distinct from manual-booking.spec.ts, which only
// creates bookings and toggles pickup, never edits or deletes a row.
//
// "Row 1 - 4" on "E2E Test Event" is otherwise only ever click-selected (never
// submitted) by user/seat-selection.spec.ts, so it's free for a real booking.

test('admin edits a booking name and the change is reflected in the table', async ({ page }) => {
  await page.goto('/admin/events')
  await page
    .getByRole('row', { name: /E2E Test Event/ })
    .getByRole('button', { name: 'View' })
    .click()
  await page.waitForURL(/\/admin\/events\/\d+/)

  await page.locator('button[title="Block A - Row 1 - 4"]').click()
  await page.locator('#guestName').fill('Edit Delete Test')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()
  await expect(page.getByText('Successfully booked 1 seat for Edit Delete Test')).toBeVisible()

  const row = page.getByRole('row', { name: /Edit Delete Test/ })
  await row.getByRole('button').first().click()

  const dialog = page.getByRole('dialog', { name: 'Edit Booking' })
  await dialog.getByLabel('Name').fill('Edit Delete Test Renamed')
  await dialog.getByRole('button', { name: 'Save Changes' }).click()

  await expect(page.getByText('Booking updated successfully')).toBeVisible()
  await expect(page.getByRole('row', { name: /Edit Delete Test Renamed/ })).toBeVisible()
})

test('admin deletes a booking and it disappears from the table', async ({ page }) => {
  await page.goto('/admin/events')
  await page
    .getByRole('row', { name: /E2E Test Event/ })
    .getByRole('button', { name: 'View' })
    .click()
  await page.waitForURL(/\/admin\/events\/\d+/)

  await page.locator('button[title="Block A - Row 2 - 3"]').click()
  await page.locator('#guestName').fill('Delete Only Test')
  await page.getByRole('button', { name: /Book 1 Seat/ }).click()
  await expect(page.getByText('Successfully booked 1 seat for Delete Only Test')).toBeVisible()

  const row = page.getByRole('row', { name: /Delete Only Test/ })
  await row.getByRole('button').last().click()

  const dialog = page.getByRole('dialog', { name: 'Delete Booking' })
  await dialog.getByRole('button', { name: 'Delete Booking' }).click()

  await expect(page.getByText('Booking deleted successfully')).toBeVisible()
  await expect(page.getByRole('row', { name: /Delete Only Test/ })).toBeHidden()
})
