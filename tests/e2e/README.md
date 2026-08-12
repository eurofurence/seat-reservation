# E2E tests (Playwright)

GUI tests for the booking flow, admin panel, and booking-window/validation
fixes. Run against the Sail dev container — nothing here manages the app
server itself.

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

Seed deterministic fixtures: `docker compose exec laravel.test php artisan e2e:seed`
(also runs automatically via the `pretest:e2e` npm script, so running `npm run
test:e2e` directly is enough in practice). This also creates the `e2e-admin`/
`e2e-user` accounts that the committed `/e2e-login` route (routes/web.php,
local-only, independent from the separate manual `/dev-login` local-dev
shortcut) logs into — the route itself only looks them up, no manual
paste-in step needed.

## Running

```bash
docker compose exec laravel.test npm run test:e2e        # headless, all specs
docker compose exec laravel.test npm run test:e2e:ui     # interactive UI mode

# A single file or directory:
docker compose exec laravel.test npx playwright test tests/e2e/user/booking-window.spec.ts

# Only one project (guest / user / admin):
docker compose exec laravel.test npx playwright test --project=admin
```

`workers: 1` is hardcoded in `playwright.config.ts` (even locally) - every
"user" spec shares ONE `e2e-user` session/cookie (same for "admin" and
`e2e-admin`), and Laravel's session-flash data is read-and-cleared per
request, so concurrent requests under the same session ID can silently drop
each other's flash message. Don't remove/raise it without re-verifying that
isn't still true.

On a slow machine, running the whole suite can take a minute or two - prefer
scoping to a single file/project while iterating on one spec (see above).

## What's covered

- `guest/login.spec.ts` - unauthenticated smoke checks
- `admin/smoke.spec.ts` - dashboard/events/rooms render for an admin session
- `admin/events-crud.spec.ts`, `admin/rooms-crud.spec.ts` - admin CRUD dialogs
- `admin/event-form-validation.spec.ts` - starts_at/reservation_ends_at/booking_starts_at
  ordering validation on the event form, and that its errors render inline
- `admin/booking-parity.spec.ts` - admins get no special treatment in the
  customer-facing booking flow (blocked before the window opens, after the
  event ends, and past the reservation deadline, same as a regular user)
- `admin/manual-booking.spec.ts` - the admin panel's separate manual-booking
  flow still bypasses those guards on purpose, its success toast shows the
  real guest name, and reverting a picked-up ticket asks for confirmation
- `user/booking-flow.spec.ts` - happy-path seat booking to confirmation
- `user/seat-selection.spec.ts` - seat-picker edge cases (deselect, max-seats
  cap, blank-name client-side validation, 24-char name limit server-side)
- `user/booking-window.spec.ts` - browsing before the booking window opens
  (banner, hidden booked seats, disabled continue), and redirects for
  sold-out/ended/closed-reservation events
- `user/seat-conflict-redirect.spec.ts` - submitting after someone else took
  the seat mid-checkout redirects cleanly instead of looping
- `user/ui-polish.spec.ts` - error toast persistence/styling, seat-block centering

## Fixtures

`php artisan e2e:seed` (`app/Console/Commands/SeedE2EData.php`) creates one
"E2E Test Room" (10 seats: Block A, Row 1-2, 5 seats each) and five events on
it, each isolated from the others since booking limits are per-event:

- **E2E Test Event** — booking window open, event/deadline in the future.
  Real POSTed bookings/selections against this event are spread across a few
  specs, each pinned to its own seat (see comments in `SeedE2EData.php` and
  each spec) to avoid colliding with the shared `e2e-user` account's 2-seat cap.
- **E2E Sold Out Event** — `max_tickets` already met by a filler booking.
- **E2E Not Open Event** — `booking_starts_at` in the future. Has a filler
  booking, which must stay hidden while the window isn't open yet.
- **E2E Ended Event** — `starts_at` in the past (`Event::hasEnded()`).
- **E2E Closed Reservation Event** — `reservation_ends_at` in the past but
  `starts_at` still in the future (`Event::isReservationClosed()`, distinct
  guard from `hasEnded()`).

`EventController::index()` only lists events with `reservation_ends_at` in
the future, so "E2E Ended Event" and "E2E Closed Reservation Event" never
show up on `/events` — reach their seat picker by navigating directly to
`/events/{id}/bookings/create` (look the id up via `/admin/events` first,
using an admin-authenticated context).

