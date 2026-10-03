<?php

namespace App\Support;

/** Record ids passed around as "1,2,3" (in links and Livewire calls). */
class Ids
{
    /** @return list<int> the positive ids, in order; anything else is dropped */
    public static function parse(string $ids): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $ids)), fn (int $id) => $id > 0));
    }
}
