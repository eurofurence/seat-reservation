import { test, expect } from '@playwright/test'

// Confirms admins get NO special treatment in the customer-facing booking flow
// (BookingController) anymore - they must go through create()'s guards exactly
// like a regular user. The admin panel's separate manual-booking flow still
// bypasses everything on purpose (see admin/manual-booking.spec.ts).

async function lookupEventId(page: import('@playwright/test').Page, eventName: string): Promise<string> {
  await page.goto('/admin/events')
  await page
    .getByRole('row', { name: new RegExp(eventName) })
    .getByRole('button', { name: 'View' })
    .click()
  await page.waitForURL(/\/admin\/events\/\d+/)
  const match = page.url().match(/\/admin\/events\/(\d+)/)
  if (!match) throw new Error(`could not resolve id for ${eventName}`)
  return match[1]
}

test('admin is redirected away from the seat picker for an event that has already started, same as a regular user', async ({ page }) => {
  const eventId = await lookupEventId(page, 'E2E Ended Event')

  await page.goto(`/events/${eventId}/bookings/create`)
  await expect(page).toHaveURL(/\/events$/)
  await expect(page.getByText('This event has already taken place.')).toBeVisible()
})

test('admin is redirected away from the seat picker past the reservation deadline, same as a regular user', async ({ page }) => {
  const eventId = await lookupEventId(page, 'E2E Closed Reservation Event')

  await page.goto(`/events/${eventId}/bookings/create`)
  await expect(page).toHaveURL(/\/events$/)
  await expect(page.getByText('The reservation period for this event has ended.')).toBeVisible()
})

test('admin sees the same "not open yet" banner and hidden booked seats as a regular user before the booking window opens', async ({ page }) => {
  const eventId = await lookupEventId(page, 'E2E Not Open Event')

  await page.goto(`/events/${eventId}/bookings/create`)
  await expect(page).toHaveURL(/\/bookings\/create/)
  await expect(page.getByText(/Booking is not open yet/i)).toBeVisible()

  // Admins get no special treatment: the filler booking must stay hidden for them too.
  await expect(page.locator('button[title="Block A - Row 1 - 1"]')).toBeEnabled()
})
