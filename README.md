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
- Booking management: manual/bulk booking, mark as picked up, edit or delete a booking
- Booking-code lookup for quick guest check-in
- CSV export of bookings
- Seating-card PDF generation, with an option to include or exclude unpicked-up bookings
- OAuth login (Laravel Socialite) with group-based admin role mapping

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
docker compose up -d --build
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

Tests cover the public booking flow, booking authorization/security, and booking-code generation/lookup (`tests/Feature`), plus model and service unit tests (`tests/Unit`).

## License

Licensed under the [MIT License](LICENSE).
