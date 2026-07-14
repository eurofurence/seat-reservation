import { test, expect, type Page } from '@playwright/test'

// Admin room CRUD (Phase 2). Each test creates its own uniquely-named room
// so it can run in parallel without colliding with the seeded "E2E Test
// Room" or other specs.

function uniqueName(label: string) {
  return `E2E CRUD Room ${label} ${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

// The create-room modal is a plain overlay div (no dialog role), and its
// "Create Room" button shares an accessible name with the page header
// button that opens it — scope to the overlay containing the modal's
// heading to disambiguate.
async function createRoom(page: Page, name: string) {
  await page.goto('/admin/rooms')
  await page.getByRole('button', { name: 'Create Room' }).click()
  const modal = page.locator('div.fixed').filter({ has: page.getByRole('heading', { name: 'Create New Room' }) })
  await modal.getByPlaceholder('Enter room name').fill(name)
  await modal.getByRole('button', { name: 'Create Room' }).click()
}

test('creating a room shows it in the list with 0 blocks/seats', async ({ page }) => {
  const name = uniqueName('Create')
  await createRoom(page, name)

  const row = page.getByRole('row', { name: new RegExp(name) })
  await expect(row).toBeVisible()
  await expect(row.getByRole('cell').nth(1)).toHaveText('0')
  await expect(row.getByRole('cell').nth(2)).toHaveText('0')
})

test('editing a room updates its name in the list', async ({ page }) => {
  const originalName = uniqueName('Edit-Before')
  const updatedName = uniqueName('Edit-After')
  await createRoom(page, originalName)

  const row = page.getByRole('row', { name: new RegExp(originalName) })
  await row.getByRole('link').nth(1).click()

  await expect(page).toHaveURL(/\/rooms\/\d+\/edit/)
  await page.getByPlaceholder('Enter room name').fill(updatedName)
  await page.getByRole('button', { name: 'Save Changes' }).click()

  await expect(page).toHaveURL(/\/admin\/rooms$/)
  await expect(page.getByRole('table').getByText(updatedName)).toBeVisible()
})

test('deleting a room removes it from the list', async ({ page }) => {
  const name = uniqueName('Delete')
  await createRoom(page, name)

  const row = page.getByRole('row', { name: new RegExp(name) })
  await row.getByRole('button').last().click()

  const confirmDialog = page.getByRole('dialog', { name: 'Delete Room' })
  await confirmDialog.getByRole('button', { name: 'Delete Room' }).click()

  await expect(page.getByRole('table').getByText(name)).toBeHidden()
})
