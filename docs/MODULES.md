# Bowls Buddy modules and pricing plan

**Goal:** grow Bowls Buddy from a booking system into the club's everyday tool. Each club pays a base fee for
bookings and adds the modules it wants. The code and deploy stay the same for every club; a signed licence
decides what each install switches on.

**Starting point:** bookings are complete (`docs/PLAN.md` section 7). There is one install per club (its own
subdomain, folder and database on Afrihost), all running the same code. Billing is not built yet.

**Status:** proposal. Nothing in this plan is built yet.

---

## 1. Goals and non-goals

**Goals**
1. **One codebase.** Every club runs the same release, and a module is switched on or off, never forked.
2. **Clubs can't unlock modules themselves.** A Secretary with `admin.config` can't switch on a paid module.
   Only a licence signed by Bowls Buddy can.
3. **Works on shared hosting.** The licence is checked offline, with no licence server in the request path and
   no SSH needed to install it.
4. **Never lose club data.** A lapsed or removed module hides or locks its screens, but its tables and rows
   stay untouched, so switching it back on restores everything.
5. **Members can always book.** Non-payment never stops members booking rinks. It only locks admin
   extras, which keeps the club on side while payment is chased.
6. **Simple pricing.** A treasurer can read it in a minute and budget for it annually.
7. **Every module ships gated and tested** from its first commit.

**Non-goals (for now)**
1. Many clubs in one install (multi-tenant). One install per club stays (`docs/PLAN.md` section 9).
2. Automatic self-service sign-up. Clubs are onboarded by hand until there are about 10 of them.
3. Usage metering beyond greens and payment transactions.

---

## 2. How it fits together

```
 Bowls Buddy (you)                               Club install (club.bowlsbuddy.co.za)
 ─────────────────                               ─────────────────────────────────────
 Invoice paid ──► licence:issue ──► key ──────►  Settings ▸ Licence page (paste key)
 (Zoho Invoice)     (private key,     (text,        or php artisan licence:install
                     your machine)     WhatsApp)          │
                                                           ▼
                                                  bs_options  service.licence
                                                           │  (via Settings)
                                                           ▼
                                                  App\Support\Modules
                                                  verify signature, club, expiry
                                                  cache result
                                                           │
                        ┌──────────────┬───────────────┬───┴──────────┬──────────────┐
                        ▼              ▼               ▼              ▼              ▼
                  Filament pages   Member routes   Blade menus    Scheduled jobs  Green limit
                  canAccess()      module:x        @module('x')   skip if off     GreenManager,
                  navigation       middleware                                     club:create
```

Later (section 7, phase 2) a small licence service replaces the manual steps: it reads paid invoices from the
Zoho Invoice API, issues the licence, and each install fetches it daily. The pasted key stays the fallback.

---

## 3. Module catalogue

| Key | Module | Depends on | Reuses | Price (per club, per month) |
|---|---|---|---|---|
| `bookings` | Bookings (what exists today) | none | everything | base plan, section 4 |
| `rollups` | Roll-ups and social bowls: sign up for a session, teams drawn at random, rinks assigned | `bookings` | `Rink`, `Event`, `BookingService` | R50–80 |
| `competitions` | Club championships: entries, draws, knock-out and round-robin, results on a phone, honours board | `bookings` | `Event` for rink blocks | R100–150 |
| `leagues` | League and inter-club fixtures, team selection, player confirmations | `competitions` | fixtures as `Event`s | R100–150 |
| `comms` | Noticeboard, email and WhatsApp broadcasts, automatic notices when a green closes | none | `GreenService` closed days | R50–80 |
| `membership` | Membership categories, annual fees, reminders, lapsed members can't book | none | `User` meta, `BookingRules` | R100–150 |
| `payments` | Online payment (PayFast or Yoco) for subscriptions and visitor green fees | `membership` or `visitors` | | transaction fee, section 4 |
| `visitors` | Public booking requests, which the Secretary approves; corporate days | `bookings` | `BookingService` | R50–80 |
| `greenkeeping` | Maintenance log, planned direction-of-play rotation, rink wear | `bookings` | `RinkUtilisation`, direction of play | R50–80 |
| `coaching` | Coach slots, lesson bookings, newcomer progress | `bookings` | booking engine | R50 |
| `equipment` | Locker allocation and rental, loan register | none | | R30–50 |
| `functions` | Social events with RSVP; clubhouse and bar hire | none | | R50 |
| `stats` | Playing history, win/loss, rankings | `competitions` | results | R50 |
| `governance` | Document store, AGM nominations and voting | none | | R50 |

**Bundles**
- **Club:** bookings, roll-ups, competitions and comms, about 20% below the separate prices. Most clubs should
  land here.
- **Club Plus:** Club, plus leagues, membership and payments.

Build order, by value to members each week: `rollups`, `competitions`, `comms`, `membership` with `payments`,
`leagues`, then the rest as clubs ask for them.

---

## 4. Pricing

The base plan is already published in the club brochure ("Bowls Buddy for clubs"), and the licence follows it.
Module prices are starting points, to be tested with LCE and two or three other clubs before they are
published.

1. **Base plan (bookings), as published:** **R2 000** once-off setup and activation per club, then **R135 per
   green per month**, billed monthly in advance. A club with 2 greens pays R2 000, then R270 a month. There is
   no limit on members.
2. **Add-on modules:** the flat monthly prices in section 3, **per club, not per green**. Most modules (for
   example competitions and comms) don't grow with the number of greens, and a flat price keeps the invoice
   easy to read.
3. **Bundles:** about 20% off the separate prices. For example, Club = bookings plus roll-ups, competitions and
   comms (R65 + R125 + R65, about R255) for about R200 a month on top of the green fee.
4. **Payments:** R3–5 or 1% per transaction, on top of the payment provider's own fee.
5. **Pay yearly:** two months free (for example 2 greens: R2 700 a year instead of R3 240). Committees approve
   spending once a year. The brochure doesn't offer this yet; add it when the first club asks.
6. **Setup:** the published R2 000 is once per club, whatever the number of greens, and covers bookings. A module added later has no setup fee unless it needs data
   imported (for example membership fees from a spreadsheet).
7. **Trial:** the brochure suggests running the app next to the paper sheet for a month. Offer that month with
   every module switched on, after which the club keeps what it pays for.

Cost side: one Afrihost Bronze Pro account (R135 a month) hosts every club, so the first green of the first
club covers hosting. Add payment provider fees. Review when about 10 clubs are live or the hosting plan has to
grow.

---

## 5. Licence design

**Payload** (JSON):

| Field | Example | Meaning |
|---|---|---|
| `v` | `1` | Format version |
| `kid` | `2026-1` | Key id, so the signing key can be rotated |
| `club` | `lce.bowlsbuddy.co.za` | Host the licence is bound to, compared with the host of `APP_URL` |
| `plan` | `club` | Plan or bundle name, for display only |
| `modules` | `["bookings","rollups","competitions","comms"]` | Switched-on modules; dependencies are checked too |
| `greens` | `2` | Greens paid for, per the published price of R135 per green |
| `issued` | `2026-10-01` | Issue date |
| `expires` | `2027-09-30` | Last paid day, local time (`Africa/Johannesburg`) |
| `trial` | `false` | Shows "trial" wording in the banner |

**Key format:** `base64url(payload) . "." . base64url(signature)`, one line that is easy to paste or send on
WhatsApp.

**Signing:** Ed25519 with PHP's built-in sodium functions (`sodium_crypto_sign_detached`). No new
dependency.
- The public keys, keyed by `kid`, ship in `config/modules.php`.
- The private key never leaves your machine. `licence:issue` only runs when `BB_LICENCE_PRIVATE_KEY` is set,
  which it never is on a club server.
- Tests use their own key pair from `phpunit.xml`.

**Storage:** the key is stored in `bs_options` as `service.licence`, always read and written through `Settings`.
No new table is needed.

**States (checked per module):**

| State | When | Effect |
|---|---|---|
| Active | Valid licence includes the module and is not expired | Fully on |
| Grace | Expired less than 14 days ago | Fully on, plus a banner for the Secretary in the panel |
| Read-only | Expired more than 14 days ago | Screens show but can't be changed; jobs and notices stop |
| Locked | Not in the licence | Hidden from members; greyed out in the admin menu with a "Contact us to add this" page |
| Invalid | Bad signature or wrong host | Treated as no licence, and the Licence page says why |

**No licence or invalid licence:** members can still book, and the admin panel keeps bookings, members and
settings. Every other module is locked. This is also how an install behaves before it is set up.

**Green limit:** `GreenManager` refuses to add a green beyond the licence's `greens` with a plain message ("Your
plan covers 2 greens. Contact Bowls Buddy to add another."). Existing greens and their bookings are never
switched off. If a licence covers fewer greens than the club has, for example after a downgrade, all greens
keep working and the Licence page shows the difference to invoice. Members are never limited.

---

## 6. In the app

| Piece | Where | Does |
|---|---|---|
| Module registry | `config/modules.php` | Key, name, description, dependencies, public keys, grace days. The only place a new module is added. |
| Licence value object | `app/Support/Licence.php` | Parses and verifies a key and returns the payload or the reason it is invalid. Pure, easy to unit test. |
| Modules service | `app/Support/Modules.php` | `enabled('x')`, `state('x')`, `readOnly('x')`, `greens()`, `expiresAt()`. Reads `service.licence` through `Settings`, caches the result (cleared whenever the licence is saved), checks dependencies. |
| Filament gate | each module's pages and resources | `canAccess()` also checks `Modules::enabled(...)`; `shouldRegisterNavigation()` shows locked modules greyed out; forms and actions are disabled when read-only. A small shared trait (`Concerns/BelongsToModule`) keeps this to one line per class. |
| Route middleware | `module:competitions` | Returns 404 for members when a module is locked, and blocks writes when it is read-only. |
| Blade directive | `@module('comms') ... @endmodule` | Menu items and links on member pages. |
| Jobs and notices | scheduler | Each job checks `Modules::enabled()` before running. |
| Licence page | `app/Filament/Pages/Licence.php`, needs `admin.config` | Paste a key; shows plan, modules, expiry, greens in use against greens paid for, and locked modules with what they do. |
| Commands | `licence:install <key>`, `licence:show`, `licence:issue` | Install and inspect on a club server; issue only where the private key is set. |
| Expiry banner | panel render hook | Shown to `admin.see-menu` users during grace and read-only. |

**Database for new modules:** each module brings its own migrations. New tables use the `bs_` prefix to match
the existing ones (for example `bs_competitions`, `bs_competition_entries`). Migrations always run, whether or
not the module is licensed, so switching a module on never needs a deploy.

**Conventions carried over:** privileges through `User::hasPrivilege()` (new ones such as `admin.competition`),
British English, local wall-clock times, and docblocks that explain *why*.

---

## 7. Billing

Invoicing runs in **Zoho Invoice** (the free version), which is already in use. It stays the single record of
what each club pays for, and the licence follows it.

**The free version is enough.** Zoho Invoice has no paid tiers; its limits are on volume, and Bowls Buddy
stays far below them (10 clubs billed monthly are about 130 invoices a year).

| Needed | Free version |
|---|---|
| Recurring invoices, payment reminders | Included |
| Customers | Up to 1 000 |
| Invoices | Up to 1 000 a year |
| Custom fields | Only a few; the plan needs one (`Club host`), or the customer's website field instead |
| API | Included, with a daily call limit; the licence service needs a few calls a day |
| Workflow webhooks | Not certain on the free version, so phase 2 polls the API instead and doesn't depend on them |
| Online card payments | PayFast is not built in (Paygate, now the same company, may work); EFT with the invoice number as reference works today |

**Set up once in Zoho Invoice:**
1. **One customer per club**, with a custom field `Club host` (for example `lce.bowlsbuddy.co.za`).
2. **Items**, with the module key as the SKU, so an invoice can be read as a licence:

   | Item | SKU | Rate | Quantity |
   |---|---|---|---|
   | Setup and activation | `setup` | R2 000, once per club | 1 |
   | Green subscription | `bookings` | R135 per green per month | number of greens |
   | Roll-ups, Competitions, ... | `rollups`, `competitions`, ... | section 3 | 1 |
   | Club bundle | `bundle-club` | section 4 | 1 |

3. **A recurring invoice per club**, monthly in advance (or yearly), with the green subscription and the
   club's modules. The setup item goes on the first, one-off invoice only.
4. Zoho Invoice's own payment reminders chase late payers. Bowls Buddy sends no billing email itself.

**Phase 1 (now, up to about 10 clubs), manual:**
1. When a club signs up or changes its modules or greens, update its recurring invoice in Zoho Invoice.
2. Run `php artisan licence:issue --club=lce.bowlsbuddy.co.za --plan=club --modules=... --greens=2
   --expires=...` on your machine, matching the invoice.
3. Send the key to the Secretary, who pastes it into Settings, Licence. Or install it yourself with
   `licence:install`.
4. **Expiry:** set the licence to run to the end of the club's paid period plus one month, and reissue it at
   each yearly renewal. Clubs on monthly billing get a licence to the end of their financial year, so there is
   no monthly re-keying. If invoices go unpaid for two months, issue a short licence (or none) and the grace
   and read-only states (section 5) take over.
5. Zoho Invoice is the register of clubs, plans and payments. Note the licence expiry in the customer's notes
   or in a custom field, so nothing else needs to be kept.

**Phase 2 (later, about 10 clubs or more), automatic:**
1. A small licence service (its own tiny Laravel app, the only place with the private key) checks the Zoho
   Invoice API a few times a day for newly paid invoices. It works on the free version with no webhooks; if
   workflow webhooks turn out to be available, one can trigger the same check straight away.
2. It reads each paid invoice: `Club host` from the customer, modules from the
   item SKUs, greens from the green subscription quantity, and the expiry from the invoice period. It then
   signs and stores a new licence.
3. Each install fetches its licence daily with a scheduled job, as a signed request by host, and stores it the
   same way. If the service is down, the stored licence keeps working until it expires, and a pasted key still
   works.
4. Clubs pay by EFT, which is recorded against the invoice in Zoho Invoice, or online if a South African
   gateway is connected to Zoho Invoice later. No Bowls Buddy payment screens are needed for its own fees.

**Club payments** (`payments` module, for subscriptions and visitor fees) are separate: that money goes to
the club's own PayFast or Yoco account. The Bowls Buddy fee is charged on top, or invoiced monthly from the
transaction log.

**Note:** `docs/PLAN.md` decision 4 left payments and pricing out of the rebuild. Those were the original app's
per-booking pricing features. The `payments` module brings back online payment as an optional, licensed
module and does not bring back the old tables.

---

## 8. Phases

**Phase A: module framework** (about 2 days). Build this before any module.
- [ ] `config/modules.php` registry, with `bookings` as the always-on base
- [ ] `Licence` value object: parse, verify signature, check host and expiry, rotate keys by `kid`
- [ ] `Modules` service with caching and dependency checks
- [ ] Filament trait, `module:` middleware, `@module` directive
- [ ] Licence page in Settings, the expiry banner, and the "Contact us to add this" page for locked modules
- [ ] `licence:install`, `licence:show`, `licence:issue` commands
- [ ] Green limit in `GreenManager` and `club:create`
- [ ] Tests (section 9), `docs/PLAN.md` section 7 updated, README section on licences
- [ ] LCE issued a licence for the Club bundle as the first real user

**Phase B: first modules** (estimates are part-time with AI assistance)
- [ ] `rollups`: 1 week
- [ ] `competitions`: 2–3 weeks
- [ ] `comms`: 1 week (email first, then WhatsApp links; WhatsApp's paid API only if clubs ask)

**Phase C: revenue**
- [ ] `membership`: 1–2 weeks
- [ ] `payments`: 1–2 weeks (PayFast first, sandbox tests, webhook signature checks)
- [ ] Pricing published, on a simple page on `bowlsbuddy.co.za`

**Phase D: the rest, as clubs ask for them**
- [ ] `leagues`, `visitors`, `greenkeeping`, `coaching`, `equipment`, `functions`, `stats`, `governance`
- [ ] Billing phase 2 (licence service reading the Zoho Invoice API) once there are about 10 clubs

**Definition of done for every module**
1. Registered in `config/modules.php`, with its dependencies.
2. Every page, resource, route and job is gated. There is a test that each one is forbidden or hidden without
   the module, and read-only after the grace period.
3. Migrations use the `bs_` prefix and run whether or not the module is licensed.
4. The data stays intact when the module is locked and comes back when it is switched on again.
5. Checked visually at 390 px and desktop width, light and dark.
6. `composer check` and `scripts/e2e.sh` pass.
7. Ticked off in `docs/PLAN.md` section 7.

---

## 9. Testing

1. **Unit (`Licence`):** a valid key; a tampered payload; a tampered signature; the wrong host; an unknown
   `kid`; expired within grace and beyond it; a malformed key.
2. **Feature (`Modules`):** dependencies (leagues without competitions is off); cache cleared when a licence
   is saved; no licence means bookings only.
3. **Pest helper:** `withModules(['competitions'], expires: ..., greens: ...)` signs a licence with the test
   key and stores it, so each module's tests switch it on explicitly.
4. **Gates:** a dataset test over every gated Filament page and route: 403 or 404 without the module,
   read-only after grace. Keep the "one panel request after a 403 per test" rule from `CLAUDE.md`.
5. **Green limit:** adding a green beyond the licence refused, in `GreenManager` and `club:create`; existing
   greens unaffected by a lower limit.
6. **Browser:** the Licence page flow (paste a key, see the modules switch on) at phone and desktop widths.

---

## 10. Risks

| Risk | Likelihood | Mitigation |
|---|---|---|
| Private key leaks | Low | Kept only on your machine and in a password manager; rotate by issuing a new `kid` and re-issuing licences |
| Club edits the code to skip checks | Low | Clubs have no deploy access; files are uploaded by Bowls Buddy. The licence stops casual unlocking, not a determined developer, and that is fine for this market |
| Add-ons too dear next to R135 a green | Medium | Test with LCE and 2–3 clubs first; bundles and the yearly discount help; a one-green club pays the least |
| Lapsed clubs keep using bookings for free | Medium | Banner and read-only extras, a friendly follow-up; the base plan is cheap enough to keep |
| Module sprawl slows the core | Medium | Build only what paying clubs ask for (phase D is driven by requests) |
| Zoho Invoice and licences drift apart (invoice changed, licence not reissued) | Medium | Phase 1: reissue the licence whenever the recurring invoice changes; the Licence page shows modules and greens to compare. Phase 2 removes the manual step |
| PayFast or Yoco changes terms | Low | Keep the payment gateway behind one interface so a second provider can be added |

---

## 11. Decisions to make

1. **Base plan without a licence:** allow bookings for free forever (as proposed), or lock the admin panel
   after the trial?
2. **Module prices:** confirm the add-on and bundle figures after talking to LCE and two or three clubs. The base
   price (R2 000 setup per club, R135 per green per month) is already published.
3. **First paid module:** roll-ups (quickest win) or competitions (biggest value)?
4. **Payments provider:** PayFast (common with clubs, supports subscriptions) or Yoco (clubs may already have
   a card machine)?
5. **Table prefix for new modules:** `bs_` (proposed, matches existing tables) or a separate prefix to mark
   them as new?
