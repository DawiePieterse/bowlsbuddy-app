# Phase 5 security check

PLAN.md section 6, point by point, on 26 Sep 2026. Verification: the Pest suite (147+ tests) and
the files named below.

| Point | Status | Where |
|---|---|---|
| CSRF token on every form; changes only via POST/PUT/DELETE | Done | Laravel's web middleware; every Blade form posts with `@csrf`; deletes are `DELETE`/`POST` routes (routes/web.php) |
| Policies on every admin action, mapped to the privileges | Done | app/Policies (admin.user / admin.booking / admin.event / admin.config), AdminResourcesTest |
| Login rate limiting per email and IP | Done | `RateLimiter::for('login')` in AppServiceProvider: 5/minute by email+IP |
| Bcrypt cost 12 | Done | Laravel default (`config('hashing.bcrypt.rounds')` = 12); tests use 4 for speed only |
| New session ID at login | Done | `$request->session()->regenerate()` in LoginController and RegistrationController |
| Secure, HttpOnly, SameSite=Lax cookies; HTTPS only | Done | .env.afrihost.example (`SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`); session.php defaults; `URL::forceScheme('https')` under an https APP_URL; Force HTTPS Redirect on Afrihost |
| Security headers (CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy) | Done | app/Http/Middleware/SecurityHeaders.php on the web group, SecurityHeadersTest. CSP keeps inline scripts/eval for Livewire/Alpine/Filament |
| Output escaped by default; rich text cleaned on save | Done | Blade `{{ }}`; info/help sanitized with symfony/html-sanitizer in SiteSettings, tested in SecretaryToolsTest |
| APP_DEBUG=false in production, errors logged | Done | .env.afrihost.example (`APP_DEBUG=false`, `LOG_LEVEL=error`) |
| composer audit and Dependabot | Done | `composer audit` clean (26 Sep 2026, in CI on every push); .github/dependabot.yml weekly |
| POPIA: minimal data, download and delete my data, resets via the Secretary | Done | Registration stores name+email only; /account/data download and account deletion (AccountPagesTest); no email anywhere |

Extras noticed and covered while checking:

1. The old app's predictable booking confirmation token (PLAN.md 2.6) has no equivalent: bookings
   are plain authenticated POSTs behind CSRF.
2. `unserialize()` (PLAN.md 2.5) survives only as a read-only fallback for old rows with
   `allowed_classes: false` (Booking::playerNames); new rows are JSON.
3. Registration is protected by an encrypted-timer anti-bot delay plus a honeypot field
   (RegistrationTest).
