import { test, expect } from '@playwright/test'

// Booking-window behavior on the "E2E Not Open Event" / "E2E Ended Event" /
// "E2E Closed Reservation Event" / "E2E Sold Out Event" fixtures (see
// app/Console/Commands/SeedE2EData.php). These specs only browse or get
// redirected — none of them complete a real booking, so they don't touch
// the shared e2e-user account's 2-seat cap on "E2E Test Event"
// (see user/booking-flow.spec.ts for the one spec that does).

test('booking window not yet open: layout is browsable with a banner, booked seats hidden, and continue is blocked', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Not Open Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  // Banner + sidebar both show the booking-opens time (BookingController::create()
  // still renders the page instead of redirecting away).
  await expect(page.getByText(/Booking is not open yet/i)).toBeVisible()
  await expect(page.getByText('Booking Opens:')).toBeVisible()

  // The filler booking on "Row 1-1" must stay hidden while the window isn't open -
  // otherwise it'd render as a disabled/booked seat instead of a normal selectable one.
  const filledSeat = page.locator('button[title="Block A - Row 1 - 1"]')
  await expect(filledSeat).toBeEnabled()

  // Selecting seats still works (browsing is allowed) but proceeding is not.
  await filledSeat.click()
  await expect(page.getByText('1 / ')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Continue to Details' }).first()).toBeDisabled()
})

test('booking window not yet open: proceeding past seat selection is blocked client-side (server-side guard covered by PHPUnit)', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Not Open Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  // "Continue to Details" calls proceedToValidation() -> canProceed is gated on
  // event.is_booking_open, so it stays disabled no matter how many seats are picked.
  // The server-side "Booking for this event is not yet open." rejection on the
  // ?seats&validate=1 branch is exercised directly by
  // BookingControllerTest::user_cannot_proceed_past_seat_selection_before_booking_starts_at.
  await page.locator('button[title="Block A - Row 2 - 1"]').click()
  await expect(page.getByRole('button', { name: 'Continue to Details' }).first()).toBeDisabled()
})

test('sold-out event redirects away from the seat picker', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Sold Out Event/i }).click()

  await expect(page).toHaveURL(/\/events$/)
  await expect(page.getByText('This event is sold out.')).toBeVisible()
})

test('an event that has already started redirects away from the seat picker, for admins too', async ({ page, browser }) => {
  const eventId = await lookupEventId(browser, 'E2E Ended Event')

  await page.goto(`/events/${eventId}/bookings/create`)
  await expect(page).toHaveURL(/\/events$/)
  await expect(page.getByText('This event has already taken place.')).toBeVisible()
})

test('an event past its reservation deadline redirects away from the seat picker, for admins too', async ({ page, browser }) => {
  const eventId = await lookupEventId(browser, 'E2E Closed Reservation Event')

  await page.goto(`/events/${eventId}/bookings/create`)
  await expect(page).toHaveURL(/\/events$/)
  await expect(page.getByText('The reservation period for this event has ended.')).toBeVisible()
})

// "E2E Ended Event" / "E2E Closed Reservation Event" have reservation_ends_at in the
// past, so EventController::index() (the /events listing) never shows them - look
// their id up via the admin panel (which lists every event) instead.
async function lookupEventId(browser: import('@playwright/test').Browser, eventName: string): Promise<string> {
  const adminContext = await browser.newContext({ storageState: 'tests/e2e/.auth/admin.json' })
  const adminPage = await adminContext.newPage()
  await adminPage.goto('/admin/events')
  await adminPage
    .getByRole('row', { name: new RegExp(eventName) })
    .getByRole('button', { name: 'View' })
    .click()
  await adminPage.waitForURL(/\/admin\/events\/\d+/)
  const match = adminPage.url().match(/\/admin\/events\/(\d+)/)
  await adminContext.close()
  if (!match) throw new Error(`could not resolve id for ${eventName}`)
  return match[1]
}
