<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DisplacedBookings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Every upcoming booking a closure cancelled, whatever closed it (a green closed for the day, an event, a
 * hidden green or rink, a day the club is closed), with a WhatsApp message per member for the Secretary to
 * send. The menu badge counts the members not told yet, so a closure made anywhere still shows up here.
 */
class AffectedBookings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'affected-bookings';

    protected static ?string $title = 'Affected bookings';

    protected string $view = 'filament.pages.affected-bookings';

    /** Closing greens and adding events go with privilege admin.event, so letting members know does too. */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.event');
    }

    public static function getNavigationBadge(): ?string
    {
        $members = app(DisplacedBookings::class)->memberCount();

        return $members > 0 ? (string) $members : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return 'Members to let know';
    }

    /**
     * After a change that can close rinks (an event saved, a green hidden, a rink taken out of use, days
     * hidden): cancels the bookings it displaces and nudges the Secretary to let the members know.
     */
    public static function cancelDisplaced(): void
    {
        $cancelled = app(DisplacedBookings::class)->cancelDisplaced();

        if ($cancelled === []) {
            return;
        }

        $bookings = count($cancelled);
        $members = count(array_unique(array_map(fn (array $item) => $item['booking']->uid, $cancelled)));

        Notification::make()
            ->title($bookings.' '.Str::plural('booking', $bookings).' cancelled')
            ->body(($members === 1 ? '1 member had' : $members.' members had').' booked a time that is now closed. '
                .(static::canAccess() ? 'Send them a WhatsApp message to let them know.' : 'The Secretary can let them know under Affected bookings.'))
            ->warning()
            ->persistent()
            ->actions(static::canAccess()
                ? [Action::make('letThemKnow')->label('Let them know')->button()->url(static::getUrl())]
                : [])
            ->send();
    }

    /** Send on WhatsApp (in the background) or Mark as told; $bookings holds the booking ids, comma-separated. */
    public function markTold(string $bookings): void
    {
        abort_unless(static::canAccess(), 403);

        app(DisplacedBookings::class)->markTold(array_map('intval', array_filter(explode(',', $bookings))));
    }

    /** @return list<array<string, mixed>> see DisplacedBookings::messages() */
    public function messages(): array
    {
        return app(DisplacedBookings::class)->messages();
    }
}
