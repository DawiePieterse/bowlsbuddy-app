<?php

namespace App\Services;

use App\Models\MemberPayment;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Membership types and fees, the membership year and who has paid for it. Types and fees are lines in
 * Settings ("Full: 1200"); the year starts in a month the club chooses (January by default) and is known by
 * the calendar year it starts in, labelled "2026" or, when it runs over New Year, "2026/27".
 */
class Membership
{
    public const TYPES_OPTION = 'service.membership.types';

    public const YEAR_START_OPTION = 'service.membership.year-start';

    public const DEFAULT_TYPES = "Full\nSocial\nJunior\nLife";

    public const GENDERS = ['female' => 'Female', 'male' => 'Male', 'other' => 'Other'];

    /** User meta keys for the member's details. */
    public const TYPE = 'membership';

    public const JOINED = 'joined';

    public const GENDER = 'gender';

    public const BIRTHDAY = 'birthday';

    public function __construct(private readonly Settings $settings) {}

    /**
     * The club's membership types with their annual fee in rand, null where none is set.
     *
     * @return array<string, float|null>
     */
    public function types(): array
    {
        $types = [];

        foreach (preg_split('/\R/', (string) $this->settings->get(self::TYPES_OPTION, self::DEFAULT_TYPES)) ?: [] as $line) {
            [$name, $fee] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');

            if ($name !== '') {
                $fee = (string) preg_replace('/[^\d.]/', '', str_replace(',', '.', $fee));
                $types[$name] = $fee !== '' && is_numeric($fee) ? (float) $fee : null;
            }
        }

        return $types;
    }

    /**
     * For select fields: "Full (R1 200.00)".
     *
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        $options = [];

        foreach ($this->types() as $type => $fee) {
            $options[$type] = $fee !== null ? $type.' ('.self::rand($fee).')' : $type;
        }

        return $options;
    }

    public function feeFor(?string $type): ?float
    {
        return $type !== null ? ($this->types()[$type] ?? null) : null;
    }

    /** The month (1 to 12) the membership year starts in. */
    public function yearStartMonth(): int
    {
        $month = (int) $this->settings->get(self::YEAR_START_OPTION, '1');

        return $month >= 1 && $month <= 12 ? $month : 1;
    }

    /** The membership year running on the day, by the calendar year it started in. */
    public function currentYear(?CarbonInterface $at = null): int
    {
        $at ??= CarbonImmutable::now();

        return $at->month >= $this->yearStartMonth() ? $at->year : $at->year - 1;
    }

    public function yearLabel(int $year): string
    {
        return $this->yearStartMonth() === 1 ? (string) $year : $year.'/'.substr((string) ($year + 1), -2);
    }

    /**
     * Last year, this year and next year, for recording a payment.
     *
     * @return array<int, string>
     */
    public function yearOptions(): array
    {
        $current = $this->currentYear();

        return collect([$current + 1, $current, $current - 1])
            ->mapWithKeys(fn (int $year) => [$year => $this->yearLabel($year)])
            ->all();
    }

    public function hasPaid(User $user, ?int $year = null): bool
    {
        return MemberPayment::query()
            ->where('uid', $user->uid)
            ->where('year', $year ?? $this->currentYear())
            ->exists();
    }

    /**
     * Fills in a message for one member, or for a group chat when $user is null: {name} is the first name
     * ("everyone" in a group), {fee} the fee of their membership type and {club} the club's short name.
     */
    public function personalise(string $message, ?User $user): string
    {
        $fee = $user !== null ? $this->feeFor($user->meta(self::TYPE)) : null;

        return strtr($message, [
            '{name}' => $user !== null ? self::firstName($user) : 'everyone',
            '{fee}' => $fee !== null ? self::rand($fee) : 'the membership fee',
            '{club}' => (string) $this->settings->get('client.name.short', $this->settings->get('client.name.full', 'the club')),
        ]);
    }

    public static function firstName(User $user): string
    {
        return trim($user->firstName()) ?: (string) strtok($user->alias, ' ');
    }

    /** "R1 200.00", as South Africans write it. */
    public static function rand(float|string $amount): string
    {
        return 'R'.number_format((float) $amount, 2, '.', ' ');
    }
}
