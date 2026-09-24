<?php

use App\Models\Rink;

it('takes the green from the part of the rink name before the dash', function (string $name, string $green) {
    expect((new Rink(['name' => $name]))->green())->toBe($green);
})->with([
    ['A-1', 'A'],
    ['B-6', 'B'],
    ['North-2', 'North'],
    [' C - 3', 'C'],
    ['Practice', 'Practice'],
]);
