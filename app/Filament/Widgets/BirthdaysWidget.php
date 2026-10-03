<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Birthdays;
use Filament\Widgets\Widget;

/**
 * Today's and the coming week's birthdays on the dashboard, each with a WhatsApp wish to send with one tap
 * (the app sends nothing itself). Tapping Send notes the member as wished for the year.
 */
class BirthdaysWidget extends Widget
{
    protected string $view = 'filament.widgets.birthdays';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPrivilege('admin.user');
    }

    /** @return list<array<string, mixed>> see Birthdays::upcoming() */
    public function birthdays(): array
    {
        return app(Birthdays::class)->upcoming(7);
    }

    public function markWished(int $uid): void
    {
        abort_unless(static::canView(), 403);

        if (($user = User::query()->find($uid)) !== null) {
            app(Birthdays::class)->markWished($user);
        }
    }
}
