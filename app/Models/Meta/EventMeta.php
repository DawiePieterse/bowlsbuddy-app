<?php

namespace App\Models\Meta;

class EventMeta extends Meta
{
    public const HAS_LOCALE = true;

    protected $table = 'bs_events_meta';

    protected $primaryKey = 'emid';
}
