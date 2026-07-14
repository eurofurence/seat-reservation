import { defineConfig, devices } from '@playwright/test'

/**
 * Playwright config for the seat-reservation admin panel + booking flow.
 *
 * Assumes the app is already running (docker compose / Sail) at baseURL —
 * we don't manage the webServer lifecycle here since Sail owns it.
 * Run `php artisan e2e:seed` before the suite to get deterministic fixtures.
 *
 * workers: 1 always, even locally. Every "user" spec shares ONE dev-login
 * session/cookie (same for "admin"), and Laravel's session flash data is
 * read-and-cleared per request - concurrent requests under the same session
 * ID race on save and can silently drop each other's flash message. Verified
 * this causes real, reproducible flakiness (redirect-with-error assertions
 * failing intermittently under `workers: undefined`); running serially avoids
 * it entirely. This suite is small enough that the speed cost is negligible.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'html',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'guest',
      testMatch: /guest\/.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'user',
      testMatch: /user\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/.auth/user.json' },
    },
    {
      name: 'admin',
      testMatch: /admin\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: { ...devices['Desktop Chrome'], storageState: 'tests/e2e/.auth/admin.json' },
    },
  ],
})
