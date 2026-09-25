<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The direction of play on a green from a date on (bs_green_directions). Read and set it through
 * App\Support\GreenDirections.
 *
 * @property int $gdid
 * @property string $green
 * @property Carbon $date
 * @property string $direction
 */
class GreenDirection extends Model
{
    /** Direction => label. */
    public const DIRECTIONS = [
        'north-south' => 'North/South',
        'east-west' => 'East/West',
    ];

    public $timestamps = false;

    protected $table = 'bs_green_directions';

    protected $primaryKey = 'gdid';

    protected $guarded = ['gdid'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
