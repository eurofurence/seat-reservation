import { test, expect } from '@playwright/test'

// Guest (unauthenticated) smoke checks — no storageState/dependency on the
// auth setup project, run against a fresh browser context.

test('login page renders for guests', async ({ page }) => {
  await page.goto('/auth/login')
  await expect(page.getByRole('heading', { name: /Seat Reservation/i })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible()
})

test('protected routes redirect unauthenticated visitors to login', async ({ page }) => {
  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/auth\/login/)
})

test('admin routes redirect unauthenticated visitors to login', async ({ page }) => {
  await page.goto('/admin')
  await expect(page).toHaveURL(/\/auth\/login/)
})
