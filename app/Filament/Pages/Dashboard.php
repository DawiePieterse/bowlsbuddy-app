<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Birthdays;
use Filament\Pages\Dashboard as BaseDashboard;

/** The panel's home page; its menu badge counts today's birthdays still to be wished. */
class Dashboard extends BaseDashboard
{
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->hasPrivilege('admin.user')) {
            return null;
        }

        $toWish = app(Birthdays::class)->toWishToday();

        return $toWish > 0 ? (string) $toWish : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Birthdays to wish today';
    }
}
