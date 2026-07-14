# E2E tests (Playwright)

GUI tests for the booking window/validation, notification, and admin-UI fixes
on the `fix/event-booking-validation` branch. Run against the Sail dev
container — nothing here manages the app server itself.

## One-time setup per environment

Browser binaries + OS deps live in the container's filesystem, not the repo.
A named Docker volume (`sail-playwright-browsers`, see `docker-compose.yml`)
caches them across container restarts, but a fresh container/image (or CI)
needs this once:

```bash
docker compose exec laravel.test npm install
docker compose exec laravel.test npx playwright install --with-deps chromium
```

## Before every run

1. Paste the local-only `/dev-login` bypass route into `routes/web.php` (see
   `auth.setup.ts` for the exact snippet/behavior). It's intentionally never
   committed — remove it again before committing app changes.
2. Seed deterministic fixtures: `docker compose exec laravel.test php artisan e2e:seed`
   (also runs automatically via the `pretest:e2e` npm script).

## Running

```bash
docker compose exec laravel.test npm run test:e2e        # headless
docker compose exec laravel.test npm run test:e2e:ui     # interactive UI mode
```

## Fixtures

`php artisan e2e:seed` (`app/Console/Commands/SeedE2EData.php`) creates one
"E2E Test Room" (10 seats: Block A, Row 1-2, 5 seats each) and four events on
it, each isolated from the others since booking limits are per-event:

- **E2E Test Event** — booking window open, event/deadline in the future.
  Has one filler booking (seat "Row 1-1") to prove booked seats are visible
  once the window is open. Real POSTed bookings against this event are kept
  to a single spec file (see `user/booking-window.spec.ts`) to avoid other
  parallel specs' bookings exhausting the 2-seat-per-user cap.
- **E2E Not Open Event** — `booking_starts_at` in the future. Also has a
  filler booking, which must stay hidden while the window isn't open yet.
- **E2E Ended Event** — `starts_at` in the past (`Event::hasEnded()`).
- **E2E Closed Reservation Event** — `reservation_ends_at` in the past but
  `starts_at` still in the future (`Event::isReservationClosed()`, distinct
  guard from `hasEnded()`).

`EventController::index()` only lists events with `reservation_ends_at` in
the future, so "E2E Ended Event" and "E2E Closed Reservation Event" never
show up on `/events` — reach their seat picker by navigating directly to
`/events/{id}/bookings/create` (look the id up via `/admin/events` first,
using an admin-authenticated context).
