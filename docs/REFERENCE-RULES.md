# Booking rules: reference values from the current app

Phase 2 step one (PLAN.md 5.3): the current app's behaviour is the specification. The old repository
(`DawiePieterse/bowlsbuddy`) vendors **ep3-bs unmodified** ("No code has been forked/modified yet",
its README) and was never run live, so the reference is:

1. the ep3-bs code for everything it implements (slot windows, capacity, events, hidden days,
   cancel cut-off), read from the files below, and
2. PLAN.md section 5.3 plus the old README's club rules for what ep3-bs does **not** implement
   (one rink per member per day, closed greens, the greens overview), which the old app left to
   "light admin monitoring".

Old files read (all under `module/` in the old repository):

| Rule area | File |
|---|---|
| Slot validity, booking windows, hidden days, cancel cut-off | `Square/src/Square/Service/SquareValidator.php` |
| Occupancy, capacity, events, max active bookings | `SquareValidator::isBookable()` |
| Quantity check, player names, booking creation flow | `Square/src/Square/Controller/BookingController.php` |
| Transaction around booking + reservations | `Booking/src/Booking/Service/BookingService.php` |

## Semantics carried over exactly

Rink columns are seconds (`time_block`, `time_block_bookable`, `min_range_book`, `range_book`,
`range_cancel`); times are local wall-clock (`Africa/Johannesburg` in the rebuild; the old app's
`Europe/Berlin` was a bug, PLAN.md section 2.7).

1. **Slot window** (`isValid`): start must be before end, both within the rink's `time_start` and
   `time_end` on that date. A requested range shorter than `time_block_bookable` is stretched to it
   (old: `$timeEnd->modify(...)`). Longer than `time_block_bookable_max` is refused for members.
2. **Past check**: with `min_range_book = 0` the cut-off is *now minus half a bookable block*
   (`time_block_bookable / 2` seconds), so a slot stays bookable through its first half hour at LCE.
   Members with `calendar.see-past` are exempt.
3. **Lead time**: with `min_range_book > 0` the cut-off is now + `min_range_book`. Staff with
   `calendar.create-single-bookings` are exempt. (LCE: 0.)
4. **Booking window**: start after now + `range_book` is refused; same staff exemption. (LCE: 14 days.)
5. **Occupancy** (`isBookable`): bookings on the same rink count when they are `visibility = public`,
   `status != cancelled` and their reservation overlaps (old: `date = ?, time_start < range end,
   time_end > range start`). Sum of their `quantity` is the occupancy. Bookable only when
   `capacity > occupancy` **and** (`occupancy = 0` or `capacity_heterogenic`). LCE: capacity 2, not
   heterogenic, so **one booking per slot**; the booker records the partner as a player name.
6. **Quantity** (`BookingController::confirmationAction`): must be numeric and > 0, and refused when
   `capacity - occupancy < quantity`.
7. **Player names**: each extra name needs at least 5 characters and a space (full first + last name).
8. **Events**: an event overlapping the range blocks the rink when its `sid` matches or its `sid` is
   null. Rebuild refinement (PLAN.md 5.1): a null-`sid` event with meta `green` blocks only that
   green's rinks; without `green` it blocks all rinks.
9. **Hidden days** (`service.calendar.day-exceptions`, split on newline or comma): a date (`Y-m-d`)
   or a weekday name (`Monday`...) hides the day; a `+`-prefixed date re-allows a hidden weekday.
10. **Disabled rink**: refused for members (staff with `calendar.create-single-bookings` may book it).
11. **Cancel cut-off** (`isCancellable`): staff with `calendar.cancel-single-bookings` always may.
    The owner may only when the rink has a `range_cancel` and the reservation starts after
    now + `range_cancel`. No `range_cancel` means members cannot cancel online at all.
12. **Transaction**: the old app wrapped booking + reservations in a transaction but checked
    availability *before* it (PLAN.md 2.8, the double-booking race). The rebuild locks the rink's
    reservations for the date (`SELECT ... FOR UPDATE`) inside the transaction and re-checks rules
    before inserting.

## Club rules new in the rebuild (PLAN.md 5.3, old README)

13. **One rink per member per day**: a member with a non-cancelled booking on any rink that day is
    refused a second one. Staff with `calendar.create-single-bookings` are exempt.
14. **Closed greens** (`service.greens.closed`, lines of `YYYY-MM-DD:G`): a closed green blocks all
    its rinks (a rink's green is its name up to the dash: `A-1` is on green `A`).
15. **Greens overview**: the next 14 playing days (hidden days are skipped, today counts while slots
    remain), per green: free and total slots, event names, closed flag.

## Reference values (LCE seed: greens A and B, 6 rinks each, 12:00-17:00, 60 min, 2 players, 14 days, cancel 24 h)

| Scenario | Expected |
|---|---|
| Slots per rink per day | 5 (12-13 ... 16-17) |
| Slots per green per day | 30 (6 rinks x 5) |
| Empty day, green open | 30 free of 30 |
| One booking 14:00 on A-1 | green A: 29 free of 30 |
| Green A closed that day | green A: 0 free of 30, closed; green B untouched |
| Event 12:00-17:00 on rink A-1 | green A: 25 free of 30, event name shown |
| Event on green A (sid null, meta green=A) | green A: 0 free of 30, event name; green B untouched |
| Event on all rinks (sid null, no green) | both greens 0 free |
| Book 14:00-15:00 on empty A-1 | accepted |
| Book 14:00-15:00 on A-1 already booked 14:00-15:00 (1 player) | refused (capacity not heterogenic) |
| Book 14:00-15:00 on A-1 booked 13:00-14:00 | accepted (no overlap) |
| Second rink same member same day | refused; staff exempt; other day accepted |
| Book 15 days ahead | refused; 14 days ahead accepted; staff exempt |
| Book yesterday / earlier today | refused; slot started < 30 min ago accepted |
| Book on hidden weekday (e.g. Tuesday in day-exceptions) | refused; `+`-date re-allows |
| Book 11:00 or 17:00 start | refused (outside 12:00-17:00) |
| Quantity 3 on a 2-player rink | refused |
| Cancel > 24 h before start | owner allowed |
| Cancel < 24 h before start | owner refused; staff allowed |
| Two concurrent bookings, same slot | exactly one succeeds |
