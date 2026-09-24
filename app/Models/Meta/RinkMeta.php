<?php

namespace App\Models\Meta;

class RinkMeta extends Meta
{
    public const HAS_LOCALE = true;

    protected $table = 'bs_squares_meta';

    protected $primaryKey = 'smid';
}
