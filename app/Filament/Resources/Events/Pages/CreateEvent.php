<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
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
    }
}
