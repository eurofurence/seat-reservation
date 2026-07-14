import { test as setup } from '@playwright/test'

// Logs in via the local-only /dev-login bypass route (see routes/web.php) and
// persists the session so the "user" and "admin" projects can reuse it without
// re-authenticating in every test. Never runs against non-local environments —
// the route itself 404s outside app()->environment('local').
//
// NOTE: /dev-login is NOT committed to routes/web.php on this branch (the repo
// owner pastes it in locally before running e2e specs and reverts it before
// committing — see README below). Add it back before running this suite.

const adminFile = 'tests/e2e/.auth/admin.json'
const userFile = 'tests/e2e/.auth/user.json'

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/dev-login')
  await page.waitForURL('**/admin')
  await page.context().storageState({ path: adminFile })
})

setup('authenticate as regular user', async ({ page }) => {
  await page.goto('/dev-login?as=user')
  // DashboardController redirects /dashboard -> /bookings (Inertia::location),
  // so the final URL is /bookings, not /dashboard.
  await page.waitForURL('**/bookings')
  await page.context().storageState({ path: userFile })
})
