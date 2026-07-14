# EF Seating Reservation

An admin panel and booking system for managing event seating layouts and ticket/seat reservations, built with **Laravel 12**, **Inertia.js**, and **Vue 3**.

## About

The application models venues as a hierarchy:

```
Room -> Block (seating section or stage, positioned/rotated) -> Row (aligned) -> Seat
```

Each **Event** belongs to a **Room** and accepts **Bookings**, where a booking links a user to a seat for that event. Bookings made through the public interface get a 3-character alphanumeric pickup code (used to look up and check in guests); bookings created manually by an admin (e.g. comped/reserved seats) don't get a code.

## Features

### Public

- Browse upcoming events with live ticket availability
- Interactive seat picker showing the room layout and already-booked seats
- Booking confirmation with a 3-character pickup code
- Personal booking history

### Admin

- Dashboard with event/booking overview stats
- Full CRUD for rooms and events
- Interactive floor-plan editor: drag-and-drop blocks, rotate (0/90/180/270), edit rows/seat counts
- Booking management: manual booking, mark as picked up, edit or delete a booking
- Bulk booking import via CSV: book many seats/guests at once, with exact seat picks or
  automatic contiguous-seat assignment, reviewed and adjustable on a seat map before anything
  is booked — see [BOOKING_IMPORT_GUIDE.md](BOOKING_IMPORT_GUIDE.md)
- Booking-code lookup for quick guest check-in
- CSV export of bookings
- Seating-card PDF generation, with an option to include or exclude unpicked-up bookings
- OAuth login (Laravel Socialite) with group-based admin role mapping

## Booking Behavior and Limits

The following rules are enforced in the current application behavior.

- Authentication required: booking routes require login; guests are redirected to the login route.
- Per-account limit: users can book at most 2 seats per event through the user booking flow (across all booking attempts, not per single request); this applies to admins too — only manual admin bookings (via the admin panel) bypass it.
- Event capacity limit: booking is blocked when the event has no tickets left (`max_tickets` reached).
- Seat conflict protection: already-booked seats cannot be booked again, including concurrent requests (seat rows are locked during booking transaction).
- Booking window start: non-admin users can browse the seat layout before `booking_starts_at`, but cannot proceed past seat selection or submit bookings until it has passed (if set).
- Booking window end: new bookings are blocked after `reservation_ends_at`.
- Booking window start applies to admins too: admins cannot create bookings through the user booking flow before `booking_starts_at` either; only manual admin bookings (via the admin panel) bypass this.
- Booking codes (user booking flow): every successful booking flow generates a 3-character alphanumeric code (`A-Z`, `0-9`), reused across all seats in that same submission.
- Booking code uniqueness: generated codes are checked against existing bookings and regenerated on collision.
- Manual admin bookings: bookings created from admin manual booking use type `admin`, have no `user_id`, and do not get a booking code.
- Bulk booking import: same `admin`/no-code/no-`user_id` shape as manual admin bookings, does not enforce the event's `max_tickets` cap (only real seat availability), and never books fewer seats than a guest's CSV-requested quantity — see [BOOKING_IMPORT_GUIDE.md](BOOKING_IMPORT_GUIDE.md).
- Booking ownership/permissions: regular users can view, update, or cancel only their own bookings; admins can manage any booking.
- Update/cancel restrictions: regular users cannot update or cancel once reservation has ended or ticket pickup has been marked.

See [CLAUDE.md](CLAUDE.md) for detailed architecture notes, coding conventions, and route/controller reference.

## Tech Stack

- **Backend:** Laravel 12, running on Octane + FrankenPHP
- **Frontend:** Inertia.js 2, Vue 3 (TypeScript), Tailwind CSS 4, shadcn/vue
- **Database:** MySQL
- **PDF generation:** mPDF
- **Auth:** Laravel Socialite (OpenID Connect)
- **Monitoring:** Sentry
- **Containerization:** Docker (FrankenPHP + MySQL via `docker-compose.yml`)

## Getting Started

### Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

php artisan serve      # Laravel dev server
npm run dev            # Vite dev server with HMR
```

### Docker

```bash
cp .env.example .env
# then set DB_HOST=mysql (not 127.0.0.1) so the app can reach the mysql service

docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test npm install
docker compose exec laravel.test npm run dev
```

This starts a FrankenPHP container (serving the app on port 80, Vite HMR on 5173) alongside a MySQL container. Run all `artisan`/`composer`/`npm` commands through `docker compose exec laravel.test ...` — there's no need for PHP or Node on the host.

> **SELinux hosts (Fedora/RHEL):** the bind mount uses the `:z` flag in `docker-compose.yml` so Docker relabels the project directory for container access. Without it you'll see `Permission denied` errors from inside the container even though Unix file permissions look fine (a host/container SELinux category mismatch, visible via `ls -Zd .` vs `docker inspect <container> --format '{{.HostConfig.SecurityOpt}}'`). The flag is a no-op on non-SELinux hosts.


### Useful commands

```bash
npm run build           # Production frontend build
vendor/bin/pint         # Format PHP code
php artisan route:list  # Inspect registered routes
```

## Testing

```bash
php artisan test                              # Run the full suite
php artisan test --filter="TestClass"         # Run a specific test class
```

### Running tests in Docker

```bash
docker compose exec laravel.test php artisan test
docker compose exec laravel.test php artisan test --filter="TestClass"
```


Tests cover the public booking flow, booking authorization/security, and booking-code generation/lookup (`tests/Feature`), plus model and service unit tests (`tests/Unit`).

### End-to-end tests (Playwright)

Browser-driven tests covering the booking flow and admin panel against a running dev server (Docker). See [tests/e2e/README.md](tests/e2e/README.md) for one-time setup, fixtures, and what's covered.

```bash
docker compose exec laravel.test npm run test:e2e
```

## License

Licensed under the [MIT License](LICENSE).
