import { test as setup } from '@playwright/test'

// Logs in via the /e2e-login bypass route (see routes/web.php) and persists
// the session so the "user" and "admin" projects can reuse it without
// re-authenticating in every test. Never runs against non-local environments —
// the route itself 404s outside app()->environment('local'). The route looks up
// (not creates) the 'e2e-admin'/'e2e-user' accounts seeded by `php artisan e2e:seed`
// (see SeedE2EData.php) — run that at least once before this suite.
//
// /e2e-login is committed and independent from the separate, never-committed
// manual /dev-login local-dev shortcut — no manual paste-in step needed here.

const adminFile = 'tests/e2e/.auth/admin.json'
const userFile = 'tests/e2e/.auth/user.json'

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/e2e-login')
  await page.waitForURL('**/admin')
  await page.context().storageState({ path: adminFile })
})

setup('authenticate as regular user', async ({ page }) => {
  await page.goto('/e2e-login?as=user')
  // DashboardController redirects /dashboard -> /bookings (Inertia::location),
  // so the final URL is /bookings, not /dashboard.
  await page.waitForURL('**/bookings')
  await page.context().storageState({ path: userFile })
})
