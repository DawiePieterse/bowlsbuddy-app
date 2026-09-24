<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * One key/value row of a bs_*_meta table.
 *
 * @property string $key
 * @property string $value
 * @property string|null $locale
 */
abstract class Meta extends Model
{
    /** Whether the table has a locale column. */
    public const HAS_LOCALE = false;

    public $timestamps = false;

    protected $guarded = [];
}
