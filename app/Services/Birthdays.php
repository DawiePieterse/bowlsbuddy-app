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
 * @phpstan-type Birthday array{day: CarbonImmutable, user: User, name: string, age: int, text: string, url: string|null, wished: bool}
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
     * Birthdays from today to $days ahead, soonest first: one query for the whole week.
     *
     * @return list<Birthday>
     */
    public function upcoming(int $days = 7): array
    {
        $today = CarbonImmutable::today();
        $dayOf = [];

        for ($ahead = $days; $ahead >= 0; $ahead--) {
            $day = $today->addDays($ahead);

            foreach (self::dates($day) as $date) {
                $dayOf[$date] = $day;
            }
        }

        $message = $this->message();
        $birthdays = [];

        foreach ($this->query(array_keys($dayOf))->with('metaEntries')->orderBy('alias')->get() as $user) {
            $born = (string) $user->meta(Membership::BIRTHDAY);
            $day = $dayOf[substr($born, 4)] ?? null;

            if ($day === null) {
                continue;
            }

            $text = $this->membership->personalise($message, $user);

            $birthdays[] = [
                'day' => $day,
                'user' => $user,
                'name' => $user->fullName(),
                'age' => $day->year - (int) substr($born, 0, 4),
                'text' => $text,
                'url' => WhatsApp::to($user->phone, $text),
                'wished' => $user->meta(self::WISHED) === (string) $day->year,
            ];
        }

        usort($birthdays, fn (array $a, array $b) => $a['day'] <=> $b['day']);

        return $birthdays;
    }

    /**
     * Active members whose birthday falls on the day.
     *
     * @return list<User>
     */
    public function on(CarbonInterface $day): array
    {
        return $this->query(self::dates($day))->with('metaEntries')->orderBy('alias')->get()->all();
    }

    /** How many of today's birthdays still have to be wished (the dashboard badge): one query. */
    public function toWishToday(): int
    {
        $today = CarbonImmutable::today();

        return $this->query(self::dates($today))
            ->whereDoesntHave('metaEntries', fn (Builder $query) => $query->where('key', self::WISHED)->where('value', (string) $today->year))
            ->count();
    }

    public function markWished(User $user): void
    {
        $user->setMeta(self::WISHED, (string) CarbonImmutable::today()->year);
    }

    /**
     * Active members with a birthday on one of these dates ("-10-03").
     *
     * @param  list<string>  $dates
     * @return Builder<User>
     */
    private function query(array $dates): Builder
    {
        return User::query()
            ->whereIn('status', User::LOGIN_STATUSES)
            ->whereHas('metaEntries', function (Builder $query) use ($dates) {
                $query->where('key', Membership::BIRTHDAY)->where(function (Builder $query) use ($dates) {
                    foreach ($dates as $date) {
                        $query->orWhere('value', 'like', '%'.$date);
                    }
                });
            });
    }

    /**
     * The birthdays that fall on a day: its own date, and 29 February on 28 February in other years.
     *
     * @return list<string>
     */
    private static function dates(CarbonInterface $day): array
    {
        return $day->format('m-d') === '02-28' && ! $day->isLeapYear() ? ['-02-28', '-02-29'] : [$day->format('-m-d')];
    }

    /** The club's birthday message, with {name} and {club} to fill in. */
    public function message(): string
    {
        return (string) $this->settings->get(self::MESSAGE_OPTION, self::DEFAULT_MESSAGE) ?: self::DEFAULT_MESSAGE;
    }
}
