import { test, expect } from '@playwright/test'

// Admin event CRUD (Phase 2). Each test creates its own uniquely-named event
// against the seeded "E2E Test Room" rather than touching the shared "E2E
// Test Event" fixture, so tests can run in parallel without colliding.

function uniqueName(label: string) {
  return `E2E CRUD ${label} ${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

test('creating an event with a name and room shows it in the list', async ({ page }) => {
  const name = uniqueName('Create')

  await page.goto('/admin/events')
  await page.getByRole('button', { name: 'Create Event' }).click()

  const dialog = page.getByRole('dialog', { name: 'Create New Event' })
  await dialog.getByPlaceholder('Enter event name').fill(name)
  await dialog.getByRole('combobox').click()
  await page.getByRole('option', { name: 'E2E Test Room' }).click()
  await dialog.getByRole('button', { name: 'Create Event' }).click()

  await expect(dialog).toBeHidden()
  await expect(page.getByRole('table').getByText(name)).toBeVisible()
})

test('submitting the create form without a name or room is rejected server-side', async ({ page }) => {
  await page.goto('/admin/events')
  await page.getByRole('button', { name: 'Create Event' }).click()
  const dialog = page.getByRole('dialog', { name: 'Create New Event' })
  await dialog.getByRole('button', { name: 'Create Event' }).click()

  // EventForm's own `form.errors` never populates here (EventIndex.vue submits
  // via a plain `router.post`, not the form's own `.post()`, so Inertia's
  // client-side error sync never reaches this component) — the server does
  // reject the request (verified via network response: "The name field is
  // required." / "The room id field is required."), it's just not rendered.
  // What's reliably assertable from the DOM: the dialog stays open, because
  // Inertia's onSuccess callback (which would close it) never fires when the
  // response carries validation errors.
  await expect(dialog).toBeVisible()
})

test('editing an event updates its name in the list', async ({ page }) => {
  const originalName = uniqueName('Edit-Before')
  const updatedName = uniqueName('Edit-After')

  await page.goto('/admin/events')
  await page.getByRole('button', { name: 'Create Event' }).click()
  const createDialog = page.getByRole('dialog', { name: 'Create New Event' })
  await createDialog.getByPlaceholder('Enter event name').fill(originalName)
  await createDialog.getByRole('combobox').click()
  await page.getByRole('option', { name: 'E2E Test Room' }).click()
  await createDialog.getByRole('button', { name: 'Create Event' }).click()
  await expect(createDialog).toBeHidden()

  const row = page.getByRole('row', { name: new RegExp(originalName) })
  await row.getByRole('button', { name: 'Edit' }).click()

  const editDialog = page.getByRole('dialog', { name: 'Edit Event' })
  await editDialog.getByPlaceholder('Enter event name').fill(updatedName)
  await editDialog.getByRole('button', { name: 'Update Event' }).click()

  await expect(editDialog).toBeHidden()
  await expect(page.getByRole('table').getByText(updatedName)).toBeVisible()
  await expect(page.getByRole('table').getByText(originalName)).toBeHidden()
})

test('deleting an event removes it from the list', async ({ page }) => {
  const name = uniqueName('Delete')

  await page.goto('/admin/events')
  await page.getByRole('button', { name: 'Create Event' }).click()
  const createDialog = page.getByRole('dialog', { name: 'Create New Event' })
  await createDialog.getByPlaceholder('Enter event name').fill(name)
  await createDialog.getByRole('combobox').click()
  await page.getByRole('option', { name: 'E2E Test Room' }).click()
  await createDialog.getByRole('button', { name: 'Create Event' }).click()
  await expect(createDialog).toBeHidden()

  // Delete button has no accessible name (icon-only); it's the 3rd button
  // in the row after "Edit" and "View".
  const row = page.getByRole('row', { name: new RegExp(name) })
  page.once('dialog', (dialog) => dialog.accept())
  await row.getByRole('button').nth(2).click()

  await expect(page.getByRole('table').getByText(name)).toBeHidden()
})
