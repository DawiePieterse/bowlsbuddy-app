<?php

namespace App\Filament\Resources\Rinks\Pages;

use App\Filament\Resources\Rinks\RinkResource;
use Filament\Resources\Pages\EditRecord;

class EditRink extends EditRecord
{
    protected static string $resource = RinkResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['slot_minutes'] = (int) (($data['time_block'] ?? 3600) / 60);
        $data['booking_range_days'] = (int) round(($data['range_book'] ?? 0) / 86400);
        $data['cancel_range_hours'] = (int) round(($data['range_cancel'] ?? 0) / 3600);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $slot = ((int) $data['slot_minutes']) * 60;

        $data['time_block'] = $slot;
        $data['time_block_bookable'] = $slot;
        $data['time_block_bookable_max'] = $slot;
        $data['range_book'] = ((int) $data['booking_range_days']) * 86400;
        $data['range_cancel'] = ((int) $data['cancel_range_hours']) * 3600;

        unset($data['slot_minutes'], $data['booking_range_days'], $data['cancel_range_hours']);

        return $data;
    }
}
