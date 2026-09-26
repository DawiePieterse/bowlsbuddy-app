<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        $reservation = $booking->reservations()->first();

        $data['date'] = $reservation?->date->format('Y-m-d');
        $data['time_start'] = $reservation ? substr($reservation->time_start, 0, 5) : null;
        $data['time_end'] = $reservation ? substr($reservation->time_end, 0, 5) : null;
        $data['player_names'] = $booking->playerNames();
        $data['notes'] = $booking->meta('notes');

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BookingResource::bookingColumns($data);
    }

    protected function afterSave(): void
    {
        /** @var Booking $booking */
        $booking = $this->getRecord();

        BookingResource::saveReservationAndMeta($booking, $this->form->getRawState());
    }
}
