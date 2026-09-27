<?php

namespace App\Support\Licensing;

/** What a module may do on this install (docs/MODULES.md section 5). */
enum ModuleState: string
{
    /** In a valid licence that has not expired. */
    case Active = 'active';

    /** Expired less than the grace days ago: still fully on, with a banner for the Secretary. */
    case Grace = 'grace';

    /** Expired longer ago: its screens show but can't change anything; jobs and notices stop. */
    case ReadOnly = 'read-only';

    /** Not licensed: hidden. Its data stays, so switching it on again brings everything back. */
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Grace => 'Renewal due',
            self::ReadOnly => 'Read-only',
            self::Locked => 'Not included',
        };
    }

    /** Pages and menus show. */
    public function visible(): bool
    {
        return $this !== self::Locked;
    }

    /** Changes, jobs and notices are allowed. */
    public function writable(): bool
    {
        return $this === self::Active || $this === self::Grace;
    }
}
