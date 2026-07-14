import { test, expect } from '@playwright/test'

// Admin smoke checks — confirms the core admin surfaces render for an
// authenticated admin session (via the dev-login bypass + storageState).

test('admin dashboard loads', async ({ page }) => {
  await page.goto('/admin')
  await expect(page.getByRole('heading', { name: /dashboard/i })).toBeVisible()
})

test('events index lists the seeded E2E event', async ({ page }) => {
  await page.goto('/admin/events')
  // Both a mobile-card and a desktop-table rendering exist in the DOM at
  // once (responsive lg:hidden/lg:block), so scope to the table to avoid a
  // strict-mode ambiguity error.
  await expect(page.getByRole('table').getByText('E2E Test Event')).toBeVisible()
})

test('rooms index lists the seeded E2E room and opens its floor plan editor', async ({ page }) => {
  await page.goto('/admin/rooms')
  await expect(page.getByText('E2E Test Room')).toBeVisible()

  // The room name itself isn't a link; the "Floor Plan" action opens the editor.
  await page
    .getByRole('row', { name: /E2E Test Room/ })
    .getByRole('link', { name: /Floor Plan/i })
    .click()
  await expect(page).toHaveURL(/\/rooms\/\d+\/layout/)
})
