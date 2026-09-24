<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use App\Models\Meta\RinkMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rink (bs_squares). The part of the name before the dash is its green: "A-1" is on green "A".
 * Times are stored as seconds.
 *
 * @property int $sid
 * @property string $name
 * @property string $status
 * @property float $priority
 * @property int $capacity
 * @property bool $capacity_heterogenic
 * @property string $time_start
 * @property string $time_end
 * @property int $time_block
 * @property int $time_block_bookable
 * @property int|null $time_block_bookable_max
 * @property int|null $min_range_book
 * @property int|null $range_book
 * @property int|null $max_active_bookings
 * @property int|null $range_cancel
 */
class Rink extends Model
{
    use HasMeta;

    public const STATUSES = ['enabled', 'readonly', 'disabled'];

    public $timestamps = false;

    protected $table = 'bs_squares';

    protected $primaryKey = 'sid';

    protected $guarded = ['sid'];

    public static function metaModel(): string
    {
        return RinkMeta::class;
    }

    protected function casts(): array
    {
        return [
            'priority' => 'float',
            'capacity' => 'integer',
            'capacity_heterogenic' => 'boolean',
            'allow_notes' => 'boolean',
            'time_block' => 'integer',
            'time_block_bookable' => 'integer',
            'time_block_bookable_max' => 'integer',
            'min_range_book' => 'integer',
            'range_book' => 'integer',
            'max_active_bookings' => 'integer',
            'range_cancel' => 'integer',
        ];
    }

    public function green(): string
    {
        return trim(explode('-', $this->name, 2)[0]);
    }

    /**
     * Rinks members can see (not disabled).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', '!=', 'disabled');
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'sid', 'sid');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'sid', 'sid');
    }
}
