import { test, expect, type Page } from '@playwright/test'

// Room floor-plan editor (RoomLayoutController::update/createBlock/deleteBlock).
// Uses its own freshly-created, booking-free room so "Save Entire Layout"
// never hits the destructive "this will delete every booking in the room"
// confirmation that guards rooms with existing bookings (see "E2E Test Room",
// which always has some by this point in the suite).

function uniqueName() {
  return `E2E Layout Room ${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

async function createRoom(page: Page, name: string) {
  await page.goto('/admin/rooms')
  await page.getByRole('button', { name: 'Create Room' }).click()
  const modal = page.locator('div.fixed').filter({ has: page.getByRole('heading', { name: 'Create New Room' }) })
  await modal.getByPlaceholder('Enter room name').fill(name)
  await modal.getByRole('button', { name: 'Create Room' }).click()
}

test('adding a seating block places it, and saving the layout persists it', async ({ page }) => {
  const roomName = uniqueName()
  await createRoom(page, roomName)

  await page
    .getByRole('row', { name: new RegExp(roomName) })
    .getByRole('link', { name: /Floor Plan/i })
    .click()
  await expect(page).toHaveURL(/\/rooms\/\d+\/layout/)

  await page.getByRole('button', { name: '+ Add Block' }).click()
  await page.getByPlaceholder('Enter block name...').fill('New Seating Block')
  await page.getByRole('button', { name: 'Create Block' }).click()

  await expect(page.getByText('New Seating Block')).toBeVisible()

  await page.getByRole('button', { name: 'Save Entire Layout' }).click()
  await expect(page.getByText('Room layout updated successfully!')).toBeVisible()

  // Reload to confirm the block is really persisted server-side, not just local form state.
  await page.reload()
  await expect(page.getByText('New Seating Block')).toBeVisible()

  // Cleanup: delete the room (cascades the block) so it doesn't linger across runs.
  await page.goto('/admin/rooms')
  await page.getByRole('row', { name: new RegExp(roomName) }).getByRole('button').last().click()
  await page.getByRole('dialog', { name: 'Delete Room' }).getByRole('button', { name: 'Delete Room' }).click()
  await expect(page.getByRole('table').getByText(roomName)).toBeHidden()
})

test('deleting a seating block removes it immediately, without needing a layout save', async ({ page }) => {
  const roomName = uniqueName()
  await createRoom(page, roomName)

  await page
    .getByRole('row', { name: new RegExp(roomName) })
    .getByRole('link', { name: /Floor Plan/i })
    .click()
  await expect(page).toHaveURL(/\/rooms\/\d+\/layout/)

  await page.getByRole('button', { name: '+ Add Block' }).click()
  await page.getByPlaceholder('Enter block name...').fill('Block To Delete')
  await page.getByRole('button', { name: 'Create Block' }).click()
  await page.getByRole('button', { name: 'Save Entire Layout' }).click()
  await expect(page.getByText('Room layout updated successfully!')).toBeVisible()

  page.once('dialog', (dialog) => dialog.accept())
  await page.getByTitle('Delete block').click()

  await expect(page.getByText('Block deleted successfully!')).toBeVisible()
  await expect(page.getByText('Block To Delete')).toBeHidden()

  // Cleanup.
  await page.goto('/admin/rooms')
  await page.getByRole('row', { name: new RegExp(roomName) }).getByRole('button').last().click()
  await page.getByRole('dialog', { name: 'Delete Room' }).getByRole('button', { name: 'Delete Room' }).click()
  await expect(page.getByRole('table').getByText(roomName)).toBeHidden()
})
