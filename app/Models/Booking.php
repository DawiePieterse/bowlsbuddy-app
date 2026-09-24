<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use App\Models\Meta\BookingMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A booking of a rink by a member (bs_bookings). Its date and time are in its reservations.
 *
 * @property int $bid
 * @property int $uid
 * @property int $sid
 * @property string $status
 * @property string $visibility
 * @property int $quantity
 */
class Booking extends Model
{
    use HasMeta;

    public const CREATED_AT = 'created';

    public const UPDATED_AT = null;

    protected $table = 'bs_bookings';

    protected $primaryKey = 'bid';

    protected $guarded = ['bid'];

    public static function metaModel(): string
    {
        return BookingMeta::class;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'created' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid', 'uid');
    }

    /** @return BelongsTo<Rink, $this> */
    public function rink(): BelongsTo
    {
        return $this->belongsTo(Rink::class, 'sid', 'sid');
    }

    /** @return HasMany<Reservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'bid', 'bid');
    }

    /**
     * Names of the other players. Stored as JSON; PHP-serialized values from the original app are read
     * too, without allowing any objects.
     *
     * @return list<string>
     */
    public function playerNames(): array
    {
        $raw = $this->meta('player-names');

        if ($raw === null || $raw === '') {
            return [];
        }

        $players = str_starts_with($raw, 'a:')
            ? @unserialize($raw, ['allowed_classes' => false])
            : json_decode($raw, true);

        if (! is_array($players)) {
            return [];
        }

        $names = [];

        foreach ($players as $player) {
            $name = is_array($player) ? ($player['value'] ?? null) : $player;

            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return $names;
    }

    /** @param list<string> $names */
    public function setPlayerNames(array $names): void
    {
        $names = array_values(array_filter(array_map('trim', $names), fn ($name) => $name !== ''));

        $this->setMeta('player-names', $names ? json_encode($names, JSON_UNESCAPED_UNICODE) : null);
    }
}
