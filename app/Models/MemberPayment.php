<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A membership fee the Secretary recorded for a member (bb_member_payments).
 *
 * @property int $id
 * @property int $uid
 * @property Carbon $paid_on
 * @property int $year
 * @property string $amount
 * @property string $method
 * @property string|null $reference
 * @property int|null $recorded_by
 */
class MemberPayment extends Model
{
    /** Method => label. */
    public const METHODS = [
        'eft' => 'EFT',
        'cash' => 'Cash',
        'card' => 'Card',
        'other' => 'Other',
    ];

    protected $table = 'bb_member_payments';

    protected $fillable = ['uid', 'paid_on', 'year', 'amount', 'method', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'year' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public static function methodLabel(string $method): string
    {
        return self::METHODS[$method] ?? $method;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }
}
