<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BookingResource::bookingColumns($data);
    }

    protected function afterCreate(): void
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        BookingResource::saveReservationAndMeta($booking, $this->form->getRawState());
    }
}
