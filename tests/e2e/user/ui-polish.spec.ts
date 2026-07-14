import { test, expect } from '@playwright/test'

// UI polish regressions: error/warning toasts are now persistent (duration: 0,
// see use-toast.ts) and on-brand (destructive/warning variants), and narrower
// seat blocks are centered within their layout column (SeatLayout.vue's
// .block-cell: justify-content changed from flex-start to center).

test('an error toast stays visible and uses the destructive (red) style, not the default gray', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Sold Out Event/i }).click()
  await expect(page).toHaveURL(/\/events$/)

  // Description text sits two levels below the toast root: root > .grid.gap-1 > description.
  const toast = page.getByText('This event is sold out.').locator('../..')
  await expect(toast).toBeVisible()
  await expect(toast).toHaveClass(/bg-red-100/)

  // Success toasts auto-dismiss after 5s (see use-toast.ts); error toasts pass
  // duration: 0 and must still be visible well past that.
  await page.waitForTimeout(6000)
  await expect(toast).toBeVisible()
})

test('narrower seat blocks are centered within their layout column', async ({ page }) => {
  await page.goto('/events')
  await page.getByRole('link', { name: /E2E Test Event/i }).click()
  await expect(page).toHaveURL(/\/bookings\/create/)

  const blockCell = page.locator('.block-cell').first()
  await expect(blockCell).toBeVisible()
  await expect(blockCell).toHaveCSS('justify-content', 'center')
})
