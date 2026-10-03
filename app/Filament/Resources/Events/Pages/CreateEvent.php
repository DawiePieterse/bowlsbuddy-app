<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Pages\AffectedBookings;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Services\DisplacedBookings;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return EventResource::eventColumns($data);
    }

    protected function afterCreate(): void
    {
        /** @var Event $event */
        $event = $this->getRecord();

        EventResource::saveMeta($event, $this->form->getRawState());

        // Members who had booked the event's time get a WhatsApp message from the Secretary.
        AffectedBookings::notifyAbout(app(DisplacedBookings::class)->forEvent($event));
    }
}
