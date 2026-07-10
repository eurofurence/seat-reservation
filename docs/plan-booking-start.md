# Plan: Per-Event Booking Start Date/Time

## Decisions from user
- No global setting — per-event field only (`booking_starts_at`, nullable). Null = no restriction (current behavior preserved).
- Admins (`is_admin=true`) bypass the restriction, consistent with existing `reservation_ends_at` bypass pattern.
- Events are **fully hidden** from the User Portal list (`/events`) until `booking_starts_at` passes (mirrors existing `starts_at`/`reservation_ends_at` filtering in `EventController::index`).
- Field name: `booking_starts_at`.

## Steps

### Phase 1: Data layer
1. New migration `add_booking_starts_at_to_events_table`: nullable `dateTime('booking_starts_at')` on `events` table, after `starts_at`.
2. `app/Models/Event.php`: add `'booking_starts_at' => 'datetime'` to `$casts` (~line 20). Add helper method `isBookingOpen(): bool` → `is_null($this->booking_starts_at) || $this->booking_starts_at->isPast()`.

### Phase 2: Backend enforcement (*depends on Phase 1*)
NOTE: `BookingPolicy` is dead code — nothing in the app calls `authorize()`/`can()`/`Gate::allows()` for bookings. Enforcement lives inline in `BookingController`, so skip editing the policy (would be a no-op diff).
4. `app/Http/Controllers/BookingController.php`:
   - `create()` (~line 39): add early gate right after the sold-out check (before `validateBooking` dispatch and before seat-map render) — if `! Auth::user()->is_admin && ! $event->isBookingOpen()`, redirect to `events.index` with `['message' => 'Booking for this event has not started yet.']`.
   - `store()` (~line 176-181): add the same gate right next to the existing `$event->reservation_ends_at->isPast()` check.
5. `app/Http/Controllers/EventController.php::index()` (~line 9-17): add `->where(fn ($q) => $q->whereNull('booking_starts_at')->orWhere('booking_starts_at', '<=', now()))` to the query, and add `booking_starts_at` is NOT needed in the select for users (no frontend display planned), skip adding to select.

### Phase 3: Admin management (*depends on Phase 1, parallel with Phase 2*)
6. `app/Http/Controllers/Admin/EventAdminController.php`:
   - `store()` validation (~line 34-39): add `'booking_starts_at' => 'nullable|date'`; add `'booking_starts_at'` to the `$request->only([...])` array (~line 41-47).
   - `update()` validation (~line 48-54): same additions.
7. `resources/js/Components/EventForm.vue`: mirror the existing `starts_at` date/time picker pattern:
   - Add `booking_starts_at` to the `Event` interface (~line 14-20).
   - Add to `form` in `useForm()` (~line 63-68).
   - Add `bookingStartsAtDate`/`bookingStartsAtTime` refs + `parseDateTime`/`parseTime` calls (~line 71-74).
   - Add `watch([bookingStartsAtDate, bookingStartsAtTime], ...)` to update `form.booking_starts_at` (~line 92-98).
   - Add `booking_starts_at: form.booking_starts_at || null` to `submitForm()` (~line 100-107).
   - Add a new template block "Booking Start Date & Time" (copy the "Event Start Date & Time" block, ~line 137-160), with helper text e.g. "Leave empty to allow booking immediately".
8. `resources/js/Pages/Admin/EventIndex.vue::getEventStatus()` (~line 80-92): add a check before the `Active` fallback — if `event.booking_starts_at && now.isBefore(dayjs(event.booking_starts_at))` return `{ text: 'Not Yet Open', class: 'bg-blue-100 text-blue-800' }`. Also pass `booking_starts_at` through in the admin `index()` query if not already selected (currently no explicit `select()`, so it's included by default — verify).

### Phase 4: Tests (*depends on Phase 1-3*)
9. Extend `tests/Feature/BookingControllerTest.php` (or `BookingCoreTest.php`):
   - Non-admin user cannot view create page / cannot store a booking when `booking_starts_at` is in the future → redirected with message.
   - Non-admin user can book once `booking_starts_at` is in the past (or null).
   - Admin can book regardless of `booking_starts_at`.
   - `EventController::index()` excludes events whose `booking_starts_at` is in the future, includes ones with null or past `booking_starts_at`.

## Relevant files
- `database/migrations/` — new migration adding `booking_starts_at`
- `app/Models/Event.php` — cast + `isBookingOpen()` helper
- `app/Http/Controllers/BookingController.php` — `create()` and `store()` methods (real enforcement point)
- `app/Http/Controllers/EventController.php` — `index()` query filter
- `app/Http/Controllers/Admin/EventAdminController.php` — `store()`/`update()` validation + field lists
- `resources/js/Components/EventForm.vue` — new date/time picker field
- `resources/js/Pages/Admin/EventIndex.vue` — `getEventStatus()` badge
- `tests/Feature/BookingControllerTest.php` / `BookingCoreTest.php` — new coverage

## Verification
1. `php artisan migrate` runs clean; `booking_starts_at` column exists nullable.
2. `php artisan test --filter=BookingControllerTest` (and BookingCoreTest) pass, including new cases.
3. Manual: create an event in admin with `booking_starts_at` in the future → event does not appear on `/events` for a regular user; visiting `/events/{event}/bookings/create` directly redirects with message. Admin can still access it and book.
4. Manual: set `booking_starts_at` in the past or leave empty → event appears normally and booking succeeds as before (regression check).
5. `vendor/bin/pint --test` on changed PHP files.

## Confirmed: bulk/manual admin booking unaffected
- `EventAdminController::manualBooking()` (~L335-390, route `admin.events.manual-booking`) is the bulk booking feature — admins select multiple seats + guest name, inserts `Booking` rows directly (`type: admin`).
- Route is under `admin` middleware only (`is_admin` required) and does NOT go through `BookingController`/`BookingPolicy`, so it is untouched by Phase 2 changes. Admins can keep bulk-booking before `booking_starts_at` with zero extra work — no code change needed here.

## Cleanup: remove dead `BookingPolicy`
- Confirmed fully unused: no `authorize()`/`Gate::allows()`/`->can()`/`@can` call anywhere in app/routes/tests, no `AuthServiceProvider` registration, no test references it.
- All its logic (admin bypass, own-booking checks, deadline checks) is already duplicated inline in `BookingController`/`EventAdminController`, which are the code paths that actually run.
- Action: delete `app/Policies/BookingPolicy.php` entirely. Pure deletion, unrelated to the `booking_starts_at` feature but flagged as a free, safe cleanup while touching this area (no behavior change since nothing calls it). Optional — can be a separate tiny PR/commit if you prefer not to mix it with the feature diff.

## Out of scope (per user decision)
- No global/default booking-start setting (env or DB-backed) — only per-event field.
- No "visible but disabled" UI state for pre-open events — they are fully hidden.
