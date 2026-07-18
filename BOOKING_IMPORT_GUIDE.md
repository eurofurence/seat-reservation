# Bulk Booking Import — Usage Guide

This feature lets an admin bulk-book many seats for many guests at once via a CSV upload,
instead of clicking seats one-by-one in the manual booking form. It supports both **exact
seat picks** and **"just give me N seats, anywhere"** auto-assignment, with a review screen
before anything is actually booked. Unlike a regular admin manual booking, imported bookings
are automatically marked **picked up** the moment they're confirmed — the assumption is that
these guests already registered externally (e.g. via a Nextcloud Forms sign-up) rather than
needing to collect a ticket in person.

## Where to find it

Admin panel → **Events → (pick an event) → Import Bookings** button (next to "Export
Bookings" and "Print Seat Cards" on the event show page).

## Collecting submissions via Nextcloud Forms

For public sign-up (letting people request seats themselves instead of an admin typing
each one in), a Nextcloud Forms form's raw CSV export can be uploaded directly with no
editing, as long as its questions are titled to match the columns above:

1. Create a form with 3 questions: **Guest Name** (short answer, required), **Number of
   Seats** (dropdown with numeric options, e.g. 1–20, required), **Comment** (long text,
   optional). Don't add Block/Row/Seat questions — public guests generally don't know the
   room's seat map, so every submission auto-assigns.
2. The question titles must match exactly (case-insensitive) for their answers to be
   picked up: `Guest Name` is mandatory, `Comment` and `Number of Seats` (or `Quantity`/
   `Seats`) are optional but their data is silently dropped if named differently — no
   error is raised, so double-check by test-uploading one real submission (see below).
   Any other question (a Telegram handle, etc.) is fine to include and is ignored.
3. **Using one shared form for several events?** Add a 4th question, **Event** (dropdown
   listing the events by name, required) — its answers must match those events' names
   exactly (case-insensitive). Upload the export via **Import Bookings (All Events)** on
   the events index page instead of a single event's dialog — see "Global import" below.
4. When ready, export the form's responses to CSV (Results tab) and upload that file
   directly via **Import Bookings** — no reformatting needed.
5. To confirm the column names actually matched: submit one test entry (with a seat
   count > 1 and some comment text), export, and upload it. If the review screen shows
   that guest with the right seat count and comment, everything lined up correctly.

## CSV format

Only one column is actually mandatory: `Guest Name` (case-insensitive). Everything else
is optional and can be omitted from the header entirely — useful if you're collecting
submissions via a form (e.g. Nextcloud Forms) that never asks people to pick an exact seat:

```
Guest Name,Comment,Block,Row,Seat,Number of Seats
```

| Column | Required | Notes |
|---|---|---|
| `Guest Name` | **yes** | Shown as the booking's guest name, exactly like a manual booking. The only column that must be present — if it's missing (or misspelled), the whole file is rejected. |
| `Comment` | no | Optional note stored on the booking. If the column is missing, every booking's comment is just blank. |
| `Block` | see below | Must match a block's name in the room's seat map exactly (case-insensitive). |
| `Row` | see below | Must match a row's name within that block. |
| `Seat` | see below | Must match a seat's label within that row. |
| `Number of Seats` | no | Auto-assign rows only (ignored for an exact seat pick) — request several seats for one guest from a single row instead of repeating the row. Aliases `Quantity` and `Seats` are also recognized. Blank defaults to 1; an explicit zero, negative, or non-numeric value rejects the whole import (see below). There is no arbitrary cap — the room's actual seat count is the only real limit; a request the room can't physically fit rejects the whole import (see below). |
| `Timestamp` | no | Submission date/time. Used to serve competing auto-assign guests **oldest first** (see "First-come-first-served priority" below). Aliases `Submitted At`, `Submission Time`, `Date`, and `Created At` are also recognized. Any format `strtotime` accepts works; blank/unparseable rows drop to the end of priority order. |
| `User Display Name` | no | Shown on the guest's card on the review screen only (e.g. who actually submitted the form) — never stored or exported. |

Any other columns in the file (a Telegram handle question, Nextcloud's own submission-ID
column, etc.) are simply ignored — they don't need to be removed before uploading. Nextcloud
Forms' `Timestamp` column, on the other hand, **is** picked up and used for FCFS priority
(see "First-come-first-served priority" below).

### `Guest Name` vs `Name`

There is **no `Name` column on import** — the parser only looks for `Guest Name`. A column
headed plainly `Name` is treated as an unknown column and ignored, so a file whose only name
column is `Name` will be rejected for missing `Guest Name`. Always use the exact header
`Guest Name`.

The `Guest Name` value is stored on the booking's `name` field — the same field an admin
manual booking fills in. Imported bookings have **no linked user account** (`type: admin`, no
user, no booking code), so in the **CSV export** they show `N/A` under the `Name` column (which
reflects the linked user's account name) while your imported value appears under the export's
separate `Guest Name` column. In short: `Name` (export) = user-account name; `Guest Name`
(import and export) = the free-text name you supplied.

Each data row must be **one of four shapes**:

1. **Exact seat** — `Block`, `Row`, and `Seat` are all filled in. That specific seat is
   booked for that guest. If the seat doesn't exist in the room (typo/mismatch), or is already
   booked, or is referenced twice in the file, that guest is **not** rejected outright —
   they land on the review screen with zero seats and a red `0/1 seats` badge, so you can just
   click them and pick a real seat on the map (same mechanic as an auto-assign guest). You
   can't click Confirm Import until every guest has at least one seat.
2. **Auto-assign, preferred row** — `Block` and `Row` are filled in, `Seat` is blank. The
   system tries to seat the guest inside that exact row first; if the row can't fit them, it
   falls back to elsewhere in the same block, then to the whole room. Use this for guests
   who requested a specific row (e.g. "Row 3") but not a specific seat within it.
3. **Auto-assign, preferred block** — only `Block` is filled in (`Row`/`Seat` blank). The
   system auto-assigns seats for this guest from within that block specifically. As with
   plain auto-assign, repeat the row (same `Guest Name` + `Comment`) or fill in `Number of
   Seats` for more than one seat.
4. **Auto-assign, no preference** — `Block`, `Row`, and `Seat` are all left **blank**. The
   system will pick a seat for this guest automatically. To request more than one seat for
   the same guest, either repeat the row as many times as seats needed — e.g. 3 blank rows
   for "Jane Smith" = 3 auto-assigned seats for Jane — or put the count directly in
   `Number of Seats` on a single row. With no block specified, the preference **defaults to
   a block named "center"** if the room has one — see below.

Any other partial combination (only `Row`, only `Seat`, or `Row`+`Seat` without a `Block`)
doesn't give the system enough information to identify an exact seat or a preferred row, so
the Row/Seat hint is simply discarded and the row is treated as **auto-assign, no
preference** — same as shape 4 above (defaults to "center" if the room has one). Nothing
about a partial Row/Seat value ever rejects the import or leaves a guest needing a manual pick.

You can mix all four shapes freely in the same file — exact seats are reserved first, then
auto-assign groups (row-, block-, or no-preference) are filled in around them in priority
order (see "First-come-first-served priority" below).

### How auto-assignment picks seats

Available seats are scanned in physical order (block order → row order → seat number).
Each auto-assign guest group is first offered a single whole row that can fit them; only if
no one row has enough room does a group get split across a row boundary. This keeps groups
sitting together instead of scattering them around the room.

Each auto-assign guest has a **preference tier** and falls back to the next tier if their
preferred area can't fit the group:

- **Row-preferred** (`Block` + `Row` filled): exact row → same block → whole room.
- **Block-preferred** (`Block` only): that block → whole room.
- **No preference** (everything blank): defaults to a block named **"center"** if the room
  has one → whole room.

A preferred area is always a soft suggestion, never a hard requirement — an import never
fails just because a specific section is full or misspelled. The preview screen shows each
guest's preference and, if fallback happened, a small hint explaining it, e.g. "Requested row was full - seated elsewhere in the block".

### First-come-first-served priority

If your CSV includes a submission-timestamp column, competing auto-assign guests are served
in **oldest-first order** (true first-come-first-served): the earliest sign-up gets first
pick of their preferred row/block, and later sign-ups fall back to the next tier when
theirs is taken.

Any one of these header names is recognized (case-insensitive): `Timestamp`, `Submitted At`,
`Submission Time`, `Date`, `Created At`. Common date formats are all understood (ISO 8601,
`Y-m-d H:i:s`, `d/m/Y H:i`, etc. — anything `strtotime` accepts). Nextcloud Forms' default
`Timestamp` column is picked up automatically.

Groups without a valid timestamp (blank, unparseable, or column absent) go after every
timestamped group, still in CSV order. Ties on timestamp break by CSV row order.

### Download a template

The dialog has a "Download CSV Template" link that gives you a correctly-headered CSV with
one example row of each shape (exact seat, row-preferred auto-assign, block-preferred
auto-assign using `Number of Seats`, no-preference auto-assign), plus an example
`Timestamp` column showing two row-preferred guests competing for the same row so you can
see the oldest-first FCFS ordering in action.

## Step-by-step usage

1. Click **Import Bookings** on the event page.
2. (Optional) Click **Download CSV Template** to get a starting point.
3. Fill in your CSV — one row per seat, per the rules above.
4. Choose the file and click **Upload & Preview**.
5. You land on the **Import Preview** page — nothing is booked yet. It shows one entry per
   guest with how many seats they got and which ones (each labelled e.g. `A 1 3`, multiple
   seats separated by ` | `). A guest's name and comment are both editable here, so
   last-minute fixes don't require re-uploading the CSV.
6. Click a guest in the list to make them "active" — the seat map on the right highlights
   their proposed seats. Click seats on the map to add/remove seats for that guest. The map
   uses color to show status: **green** = available, **blue** = part of this import (this
   guest's own picks, or reserved by another guest in the same batch — those are unclickable
   so you can't accidentally double-book them), **red** = genuinely already booked for this
   event. Hovering a seat while short of the requested count previews (light blue) what a
   click there would fill.
   - **If the guest is short of their requested count** (e.g. they were originally auto-
     assigned 9 seats and you deselected 3, or they started unresolved), clicking just **one**
     available seat auto-fills the rest of the shortfall for you — it fills forward from the
     seat you clicked in physical room order, skipping booked/reserved seats, until they're
     back up to their full requested count. Deselect everything and click once to re-fill all
     of them the same way; if only one seat is missing, that one click alone fills it.
7. Each guest's badge shows their status: a plain count (e.g. `2 seats`) when it matches what
   the CSV asked for, a red `1/2 seats` badge (with a "Needs N more seats picked" hint) if
   they're under their requested count, or an amber/outline `3/2 seats` badge if you've added
   more than requested (allowed, just flagged for visibility). A guest with a mismatched CSV
   seat reference starts at `0/1 seats` and the page automatically opens on the first one of
   these so it's hard to miss.
8. When everything looks right, click **Confirm Import** — disabled while ANY guest is still
   under their requested count. **The import can never book fewer seats than a guest's CSV
   quantity requested**, though booking more is allowed. This creates the bookings (same as
   an admin manual booking: no user account, no booking code, type `admin`).

### If someone else books a seat while you're reviewing

On Confirm, seats are re-checked (and locked) against the database. If a seat proposed for a
guest got booked by someone else in the meantime, **only that guest** is automatically
reassigned to a different free seat, and you're sent back to the same preview page with a
warning banner — everyone else's assignment is left untouched. Review the update and click
Confirm Import again.

### What counts as a hard failure (whole import rejected, no preview)

- A row is missing `Guest Name` entirely - there's no guest to show on the review screen to
  fix it for.
- An explicit zero, negative, or non-numeric `Number of Seats` value - only a truly blank
  value defaults to 1.
- **Room out of physical seats for an auto-assign group**: if a guest's requested quantity
  can't be satisfied by any available run of seats in the room (including the case where the
  *total* free seats aren't physically contiguous for that guest), the **entire import is
  rejected up front** — no preview, no partial booking. This is meant to be rare (e.g. a typo
  in the seat count).
- **The event's ticket cap (`max_tickets`) is intentionally NOT enforced.** Admins can always
  import past the event's advertised ticket limit, same as the existing manual booking form —
  only real seat availability matters.

A mismatched/already-booked/duplicated **exact** seat reference is NOT a hard failure — see
above, it becomes a "Needs seat" guest you resolve on the review screen instead.

### The minimum-seats guarantee

An import can never book fewer seats than a guest's CSV-requested quantity — Confirm Import
stays disabled (and is rejected if forced) until every guest has at least that many seats.
Booking *more* seats than requested is always allowed.

### Re-importing a CSV

Re-uploading a CSV (e.g. a growing form export) is safe: a row whose `Guest Name` already has
a booking on this event is recognized instead of booked again, and shows an **"Already
booked"** badge on the preview instead of a seat count. If this upload now asks for a
different number of seats for that guest, add or remove seats on the map to match before
confirming — removed seats are freed, added ones are booked. Editing an already-booked
guest's name/comment here renames their existing booking; if you change their name, a
*future* re-import using the old name will no longer recognize them.

## Limits

- File must be `.csv` or `.txt`, max 2 MB.
- Max 2000 data rows per file.

## Ready-made test files

Two example CSVs are included at the repo root for trying this out locally. They assume a
room with a block `A` containing rows `A`/`B`/`C` (seats `1`–`5`) — create one, or edit the
`Block`/`Row`/`Seat` values to match an existing room's seat map (visible on the event page,
or in an "Export Bookings" CSV, which uses the same column names):

- **`booking-import-example.csv`**:
  - Alice Anderson → exact seats A-A-1 and A-A-2
  - Bob Baker → exact seat A-B-5
  - Carol Carter → 2 auto-assigned seats
  - Dave Davis → 3 auto-assigned seats (enough to span a row boundary)
- **`booking-import-example-invalid.csv`** — two rows demonstrating the two remaining ways
  a row can behave badly:
  - a blank `Guest Name` — this is the **one** row-level problem that still rejects the whole
    import (there's no guest to fix it for on the review screen). Remove this row to try the
    next one on its own.
  - "Frank Ghost" references a seat label that doesn't exist ("Seat 99") — this reaches the
    preview as its own `0/1 seats` guest needing a manual pick, without affecting anyone else
    in the file.

## Global import (all events at once)

If you're collecting bookings for **multiple events through one shared form** (e.g. a
single Nextcloud Forms sign-up that isn't per-event), use **Events → Import Bookings (All
Events)** on the events index page instead of a single event's Import Bookings button.

The CSV format is the same as above with one extra mandatory column, `Event`, identifying
which event each row belongs to:

```
Event,Guest Name,Comment,Block,Row,Seat,Number of Seats
```

Matching against an event's name is **soft**: case doesn't matter, extra/repeated
whitespace is collapsed, and punctuation/symbols (`:`, `-`, `()`, etc.) are ignored. So
`Opening Ceremony: Hall 3`, `opening ceremony hall 3`, and `Opening Ceremony (Hall 3)` all
match the same event. Only the letters/numbers and their order have to match.

If any row's `Event` value doesn't match a real event's name this way, the **entire file
is rejected upfront** — same as a missing `Guest Name` — naming the bad value and row so
you can fix the CSV and re-upload; nothing is processed until every event name resolves.
The file is also rejected if two different events become indistinguishable once matched
this softly (e.g. two events named `Opening Ceremony (Hall 3)` and
`Opening Ceremony - Hall 3`) — rename one of them so the CSV can tell them apart.

Rows are grouped by `Event` (in the order each event first appears in the file) and
processed **one event at a time**, reusing the exact same review screen as a single-event
import:

1. Upload the file. You land on the first event's Import Preview page, exactly as if
   you'd uploaded just that event's rows directly.
2. Review and click **Confirm Import** as normal.
3. Instead of returning to that event's page, you're automatically taken to the **next**
   event's Import Preview — a message confirms what was just staged and which event is
   next (e.g. "Staged 4 booking(s) for 2 guest(s). Continuing with 'Winter Ball' (2 of 3)").
4. Repeat until every event in the file has been reviewed. **Nothing is booked until the
   final event is confirmed** — each Confirm only *stages* that event's bookings; the last
   Confirm writes every event's bookings in one all-or-nothing transaction and redirects
   to that event's page with a summary of the whole run.

The whole run is atomic: abandoning it midway (the preview page warns you before you
navigate away) books nothing at all. Likewise, if building the next event's proposal fails
(e.g. that room runs out of available seats) or a queued event was deleted meanwhile, the
entire import is cancelled with a warning and nothing is booked — fix the issue and
re-upload the full CSV. If a staged seat gets booked by someone else before the final
write, that guest is auto-reassigned to a free seat (called out in the summary); if the
room can no longer fit them, the whole import rolls back so you can re-run it.

## Troubleshooting

| Symptom | Cause |
|---|---|
| "CSV header must contain a \"Guest Name\" column." | Header row is missing (or has misspelled) the one mandatory column. |
| A guest's `Number of Seats` request was silently ignored (they only got 1 seat) | The column wasn't named `Number of Seats`/`Quantity`/`Seats` (case-insensitive), so it was treated as an unrelated extra column and defaulted to 1. |
| "Row N: Number of Seats must be a positive whole number, got '...'" | A row had an explicit zero, negative, or non-numeric `Number of Seats` value - fix or blank out the value (blank defaults to 1) and re-upload; this rejects the whole import. |
| "Not enough available seats for ..." (including an implausibly large `Number of Seats` typo) | Only happens when the room genuinely doesn't have that many free seats anywhere at all — even scattered/non-contiguous — for that guest. If enough free seats exist but just can't be seated together, that guest is instead flagged unresolved on the review screen (see below) and the rest of the import still proceeds. |
| Guest shows a red `0/1 seats` (or similar) badge on the preview page | Their `Block Row Seat` combo didn't match any seat in this event's room, or that seat was already booked/used twice in the file — click the guest and pick a real seat on the map. |
| "Every guest needs at least one seat picked before you can confirm" | You tried to confirm while a guest still has zero seats assigned — pick a seat for them first. |
| "\{name\} needs at least N seat(s) but only M selected" | You removed seats from a guest on the map, dropping them below their CSV-requested quantity — add seats back until the badge is no longer red. |
| "Row {name}: Guest Name is required" | A CSV row has no name in the `Guest Name` column — this is the one row-level problem that still rejects the whole import, since there's no guest to fix it for on the review screen. |
| An auto-assigned guest landed in an unexpected section | With no `Block` specified, the default preference is a block named "center" (if the room has one) — specify a `Block` (or `Block` + `Row` for a specific row) to steer them elsewhere, or note that a full/nonexistent preferred area silently falls back through row → block → room. |
| Guest shows unresolved with "Couldn't seat this guest together in one place ..." | Their preferred area (or, if none was given, the whole room) has enough free seats in total but not as one contiguous group — click the guest and use "Auto-place seats" once other guests free up room, or pick seats manually (they don't need to be adjacent). |
| Guest shows unresolved with "Block '...' not found" / "Row '...' in block '...' not found" | The CSV named an explicit `Block` (optionally + `Row`) that doesn't exist in this room at all — fix the typo and re-upload, or click the guest and use "Auto-place seats"/pick seats manually to place them elsewhere. |
| A row-preferred guest didn't get their exact row | Their preferred row was already full or didn't have enough contiguous free seats — they fell back to elsewhere in the same block (or the whole room). The preview shows a hint explaining this on that guest's card. |
| Two guests wanted the same row and only one got it | Auto-assign groups are served **oldest submission first** (FCFS) when a `Timestamp` column is present. Check the timestamps: the older sign-up keeps their preferred row and the newer one falls back. Groups with no/unparseable timestamp are served last, in CSV order. |
| A `Timestamp` value was ignored (guest ended up served last) | The value couldn't be parsed. Use a standard format like `2025-06-15 09:30:00` or an ISO 8601 datetime, or rename the column to one of the recognized aliases (`Timestamp`, `Submitted At`, `Submission Time`, `Date`, `Created At`). |
| "No pending import found" on the preview page | Your session's pending import expired or was never created — go back and upload the CSV again. |
| A guest shows an "Already booked" badge instead of a seat count | Their `Guest Name` already has a booking on this event (from a prior import, manual booking, or self-service booking) — see "Re-importing a CSV" above. |
| "Row N: no event found named '...'" (global import) | An `Event` value in the CSV didn't match any existing event's name, even ignoring case/whitespace/symbols — fix the typo/rename and re-upload; the whole file is rejected until every row resolves. |
| "Events '...' and '...' are too similar to tell apart for import ..." (global import) | Two different events normalize to the same name once case/whitespace/symbols are ignored (e.g. `Opening Ceremony (Hall 3)` vs `Opening Ceremony - Hall 3`) — rename one of them so the `Event` column can distinguish them, then re-upload. |
