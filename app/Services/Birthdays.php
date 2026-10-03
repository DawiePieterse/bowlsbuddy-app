<?php

namespace App\Services;

use App\Models\User;
use App\Support\Settings;
use App\Support\WhatsApp;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Members' birthdays for the admin dashboard: today's and the coming week's, each with a WhatsApp wish ready
 * for the Secretary to send with one tap (the app sends nothing itself). Sending notes the member as wished
 * for that year, so the dashboard badge only counts the ones still to do.
 *
 * @phpstan-type Birthday array{day: CarbonImmutable, user: User, name: string, age: int|null, text: string, url: string|null, wished: bool}
 */
class Birthdays
{
    public const MESSAGE_OPTION = 'service.membership.birthday-message';

    public const DEFAULT_MESSAGE = 'Happy birthday, {name}! Best wishes from everyone at {club}.';

    /** User meta: the year the member was last wished a happy birthday. */
    public const WISHED = 'birthday_wished';

    public function __construct(
        private readonly Membership $membership,
        private readonly Settings $settings,
    ) {}

    /**
     * Birthdays from today to $days ahead, soonest first.
     *
     * @return list<Birthday>
     */
    public function upcoming(int $days = 7): array
    {
        $today = CarbonImmutable::today();
        $birthdays = [];

        for ($ahead = 0; $ahead <= $days; $ahead++) {
            $day = $today->addDays($ahead);

            foreach ($this->on($day) as $user) {
                $text = $this->membership->personalise($this->message(), $user);
                $born = $user->meta(Membership::BIRTHDAY);

                $birthdays[] = [
                    'day' => $day,
                    'user' => $user,
                    'name' => trim($user->firstName().' '.$user->lastName()) ?: $user->alias,
                    'age' => $born !== null ? $day->year - (int) substr($born, 0, 4) : null,
                    'text' => $text,
                    'url' => WhatsApp::to($user->phone, $text),
                    'wished' => $user->meta(self::WISHED) === (string) $day->year,
                ];
            }
        }

        return $birthdays;
    }

    /**
     * Active members whose birthday falls on the day; a 29 February birthday is kept on 28 February in other
     * years.
     *
     * @return list<User>
     */
    public function on(CarbonInterface $day): array
    {
        $dates = [$day->format('-m-d')];

        if ($day->format('m-d') === '02-28' && ! $day->isLeapYear()) {
            $dates[] = '-02-29';
        }

        return User::query()
            ->whereIn('status', User::LOGIN_STATUSES)
            ->whereHas('metaEntries', fn (Builder $query) => $query
                ->where('key', Membership::BIRTHDAY)
                ->where(fn (Builder $query) => collect($dates)->each(fn (string $date) => $query->orWhere('value', 'like', '%'.$date))))
            ->with('metaEntries')
            ->orderBy('alias')
            ->get()
            ->all();
    }

    /** How many of today's birthdays still have to be wished (the dashboard badge). */
    public function toWishToday(): int
    {
        return count(array_filter(
            $this->on(CarbonImmutable::today()),
            fn (User $user) => $user->meta(self::WISHED) !== (string) CarbonImmutable::today()->year,
        ));
    }

    public function markWished(User $user): void
    {
        $user->setMeta(self::WISHED, (string) CarbonImmutable::today()->year);
    }

    /** The club's birthday message, with {name} and {club} to fill in. */
    public function message(): string
    {
        return (string) $this->settings->get(self::MESSAGE_OPTION, self::DEFAULT_MESSAGE) ?: self::DEFAULT_MESSAGE;
    }
}
