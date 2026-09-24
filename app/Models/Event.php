<?php

namespace App\Models;

use App\Models\Concerns\HasMeta;
use App\Models\Meta\EventMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Time when rinks can't be booked (bs_events): one rink (sid), one green (no sid, meta "green") or all rinks.
 *
 * @property int $eid
 * @property int|null $sid
 * @property string $status
 * @property Carbon $datetime_start
 * @property Carbon $datetime_end
 */
class Event extends Model
{
    use HasMeta;

    public $timestamps = false;

    protected $table = 'bs_events';

    protected $primaryKey = 'eid';

    protected $guarded = ['eid'];

    public static function metaModel(): string
    {
        return EventMeta::class;
    }

    protected function casts(): array
    {
        return [
            'datetime_start' => 'datetime',
            'datetime_end' => 'datetime',
        ];
    }

    /** @return BelongsTo<Rink, $this> */
    public function rink(): BelongsTo
    {
        return $this->belongsTo(Rink::class, 'sid', 'sid');
    }

    public function covers(Rink $rink): bool
    {
        if ($this->sid !== null) {
            return (int) $this->sid === (int) $rink->sid;
        }

        $green = $this->meta('green');

        return $green === null || $green === '' || $green === $rink->green();
    }
}
