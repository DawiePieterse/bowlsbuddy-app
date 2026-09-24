<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One site setting (bs_options). Read settings through App\Support\Settings, which caches them.
 *
 * @property int $oid
 * @property string $key
 * @property string $value
 * @property string|null $locale
 */
class Option extends Model
{
    public $timestamps = false;

    protected $table = 'bs_options';

    protected $primaryKey = 'oid';

    protected $guarded = ['oid'];
}
