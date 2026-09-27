<?php

namespace App\Support\Licensing;

use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * Which modules this install may use, from the licence in bs_options (service.licence). The one place
 * pages, routes, Blade views and jobs ask. Without a valid licence only bookings are on: members can
 * always book, whatever the state of the club's account (docs/MODULES.md section 1).
 *
 * A module's Filament pages and resources add `app(Modules::class)->enabled('x')` to canAccess(), and
 * its actions that change data `->disabled(fn () => ! app(Modules::class)->writable('x'))`. Routes use
 * the `module:x` middleware, views `@module('x') ... @endmodule`, and jobs check writable('x').
 */
class Modules
{
    public const SETTING = 'service.licence';

    public const BASE = 'bookings';

    /** @var array{licence: ?Licence, problem: ?string}|null read once per request */
    private ?array $read = null;

    public function __construct(private readonly Settings $settings) {}

    /** The installed licence when it is valid for this install, expired or not. */
    public function licence(): ?Licence
    {
        return $this->read()['licence'];
    }

    /** Why the installed licence can't be used, or null when it can (or none is installed). */
    public function problem(): ?string
    {
        return $this->read()['problem'];
    }

    public function state(string $module): ModuleState
    {
        if ($module === self::BASE) {
            return ModuleState::Active;
        }

        $licence = $this->licence();

        if ($licence === null || ! $this->licensed($module, $licence)) {
            return ModuleState::Locked;
        }

        $today = Carbon::today();

        return match (true) {
            $today->lte($licence->expires) => ModuleState::Active,
            $today->lte($this->graceEnds($licence)) => ModuleState::Grace,
            default => ModuleState::ReadOnly,
        };
    }

    /** The module's pages and menus show (it may still be read-only). */
    public function enabled(string $module): bool
    {
        return $this->state($module)->visible();
    }

    /** The module may change data, run jobs and send notices. */
    public function writable(string $module): bool
    {
        return $this->state($module)->writable();
    }

    /** Greens paid for, or null for no limit (no licence, or a licence without one). */
    public function greens(): ?int
    {
        return $this->licence()?->greens;
    }

    /** The last paid day, or null without a licence. */
    public function expiresAt(): ?Carbon
    {
        return $this->licence()?->expires;
    }

    /** The last day of the grace period, after which modules turn read-only. */
    public function graceEnds(Licence $licence): Carbon
    {
        return $licence->expires->copy()->addDays((int) config('modules.grace_days', 14));
    }

    /**
     * Checks and stores a new licence key. Refuses a key for another club or one that doesn't verify,
     * so the licence in use is never replaced by a bad one.
     *
     * @throws InvalidLicence
     */
    public function install(string $key): Licence
    {
        $licence = Licence::parse($key, $this->publicKeys());
        $this->checkHost($licence);

        $this->settings->set(self::SETTING, trim($key));
        $this->read = null;

        return $licence;
    }

    /** Removes the licence (only bookings stay on). */
    public function uninstall(): void
    {
        $this->settings->set(self::SETTING, null);
        $this->read = null;
    }

    /**
     * Every module from config/modules.php.
     *
     * @return array<string, array{name: string, description: string, requires: list<string>}>
     */
    public function all(): array
    {
        /** @var array<string, array{name: string, description: string, requires: list<string>}> */
        return config('modules.modules', []);
    }

    /** The host this install runs on, which a licence must name. */
    public static function host(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /** A module counts only when it and everything it needs are in the licence. */
    private function licensed(string $module, Licence $licence, int $depth = 0): bool
    {
        if ($module === self::BASE) {
            return true;
        }

        if ($depth > 10 || ! in_array($module, $licence->modules, true) || ! isset($this->all()[$module])) {
            return false;
        }

        foreach ($this->all()[$module]['requires'] as $required) {
            if (! $this->licensed($required, $licence, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /** @throws InvalidLicence */
    private function checkHost(Licence $licence): void
    {
        if ($licence->club !== self::host()) {
            throw new InvalidLicence("This licence is for {$licence->club}, not ".self::host().'.');
        }
    }

    /** @return array<string, string> */
    private function publicKeys(): array
    {
        /** @var array<string, string> */
        return config('modules.public_keys', []);
    }

    /** @return array{licence: ?Licence, problem: ?string} */
    private function read(): array
    {
        if ($this->read !== null) {
            return $this->read;
        }

        $key = $this->settings->get(self::SETTING);

        if ($key === null || trim($key) === '') {
            return $this->read = ['licence' => null, 'problem' => null];
        }

        try {
            $licence = Licence::parse($key, $this->publicKeys());
            $this->checkHost($licence);
        } catch (InvalidLicence $invalid) {
            return $this->read = ['licence' => null, 'problem' => $invalid->getMessage()];
        }

        return $this->read = ['licence' => $licence, 'problem' => null];
    }
}
