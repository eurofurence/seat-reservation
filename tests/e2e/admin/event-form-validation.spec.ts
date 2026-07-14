import { test, expect, type Page, type Locator } from '@playwright/test'

// Admin event form: starts_at/reservation_ends_at/booking_starts_at ordering
// validation (EventAdminController::validatedEventData) and that its errors
// now actually render inline. Regression coverage for the bug where
// EventIndex.vue's handleFormSubmit bypassed EventForm's own useForm() via a
// plain router.post()/put(), so server validation errors never reached the
// form - EventForm.vue now calls form.post()/put() itself instead.

function uniqueName(label: string) {
  return `E2E CRUD Validation ${label} ${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

// Each DateTimePicker.vue renders <div><label>{label}</label><div class="flex
// gap-2">[date button][time input]</div>...</div> - scope to that wrapper via
// its exact label text so fields stay addressable by name regardless of
// picking order (the date button's own accessible name/text changes from
// "Pick a date" to a formatted date once a value is picked, so filtering by
// that text - and later re-deriving nth() from the shrinking match set -
// silently shifts which field subsequent picks land on).
function dateTimeField(dialog: Locator, label: string) {
  const group = dialog.getByText(label, { exact: true }).locator('..')
  return { dateButton: group.getByRole('button').first(), timeInput: group.locator('input[type="time"]') }
}

// Selects a day in the shadcn/reka-ui Calendar popover opened by `trigger`,
// paging forward with "Next page" until the target month is in view.
async function pickDate(page: Page, trigger: Locator, daysFromNow: number) {
  const target = new Date()
  target.setDate(target.getDate() + daysFromNow)
  const dayLabel = target.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
  const monthYear = target.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })

  await trigger.click()
  const popover = page.getByRole('dialog', { name: 'Pick a date' })
  const heading = popover.locator('[data-slot="calendar-heading"]')
  while (!(await heading.getByText(monthYear, { exact: true }).isVisible())) {
    await popover.getByRole('button', { name: 'Next page' }).click()
  }
  await popover.getByRole('button', { name: dayLabel }).click()
}

async function setDateTime(page: Page, dialog: Locator, label: string, daysFromNow: number) {
  const { dateButton, timeInput } = dateTimeField(dialog, label)
  await pickDate(page, dateButton, daysFromNow)
  await timeInput.fill('10:00')
}

const cases: Array<{ label: string; setup: (dialog: Locator, page: Page) => Promise<void>; errorText: RegExp }> = [
  {
    label: 'reservation deadline after event start',
    setup: async (dialog, page) => {
      await setDateTime(page, dialog, 'Event Start Date & Time', 3)
      await setDateTime(page, dialog, 'Reservation Deadline', 10) // after event start
    },
    errorText: /reservation deadline must be before the event start date/i,
  },
  {
    label: 'booking start after reservation deadline',
    setup: async (dialog, page) => {
      await setDateTime(page, dialog, 'Event Start Date & Time', 10)
      await setDateTime(page, dialog, 'Reservation Deadline', 3)
      await setDateTime(page, dialog, 'Booking Start Date & Time', 5) // after deadline
    },
    errorText: /booking start date must be before the reservation deadline/i,
  },
  {
    label: 'booking start after event start',
    setup: async (dialog, page) => {
      await setDateTime(page, dialog, 'Event Start Date & Time', 3)
      await setDateTime(page, dialog, 'Booking Start Date & Time', 5) // after event start
    },
    errorText: /booking start date must be before the event start date/i,
  },
]

for (const { label, setup, errorText } of cases) {
  test(`create event rejects ${label} and renders the error inline`, async ({ page }) => {
    const name = uniqueName(label)

    await page.goto('/admin/events')
    await page.getByRole('button', { name: 'Create Event' }).click()

    const dialog = page.getByRole('dialog', { name: 'Create New Event' })
    await dialog.getByPlaceholder('Enter event name').fill(name)
    await dialog.getByRole('combobox').click()
    await page.getByRole('option', { name: 'E2E Test Room' }).click()

    await setup(dialog, page)

    await dialog.getByRole('button', { name: 'Create Event' }).click()

    // Dialog stays open (onSuccess never fires) and the inline error renders.
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText(errorText)).toBeVisible()
    await expect(page.getByRole('table').getByText(name)).toHaveCount(0)
  })
}
