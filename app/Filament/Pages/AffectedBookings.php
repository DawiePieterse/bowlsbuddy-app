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
 * Every upcoming booking that can't go ahead, whatever closed it (a green closed for the day, an event, a
 * hidden green or a day the club is closed), with a WhatsApp message per member for the Secretary to send.
 * The menu badge counts the members, so a closure made anywhere still shows up here.
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
     * After a change that displaces bookings (an event saved, a green hidden): a nudge with a link to this
     * page when members had already booked.
     *
     * @param  list<array<string, mixed>>  $displaced  from DisplacedBookings
     */
    public static function notifyAbout(array $displaced): void
    {
        $members = count(array_unique(array_map(fn (array $item) => $item['booking']->uid, $displaced)));

        if ($members === 0 || ! static::canAccess()) {
            return;
        }

        Notification::make()
            ->title($members.' '.Str::plural('member', $members).' had already booked')
            ->body('Their bookings can\'t go ahead. Send them a WhatsApp message to let them know.')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('letThemKnow')->label('Let them know')->button()->url(static::getUrl()),
            ])
            ->send();
    }

    /** @return list<array<string, mixed>> see DisplacedBookings::messages() */
    public function messages(): array
    {
        return app(DisplacedBookings::class)->messages();
    }
}
