<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use App\Models\Meta\ReservationMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The date and time range of a booking (bs_reservations). Times are local (Africa/Johannesburg).
 *
 * @property int $rid
 * @property int $bid
 * @property Carbon $date
 * @property string $time_start
 * @property string $time_end
 */
class Reservation extends Model
{
    use HasMeta;

    public $timestamps = false;

    protected $table = 'bs_reservations';

    protected $primaryKey = 'rid';

    protected $guarded = ['rid'];

    public static function metaModel(): string
    {
        return ReservationMeta::class;
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'bid', 'bid');
    }
}
