<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Event $event */
        $event = $this->getRecord();

        $data['name'] = $event->meta('name');
        $data['description'] = $event->meta('description');
        $data['green'] = $event->meta('green');
        $data['scope'] = $event->sid !== null ? 'rink' : ($event->meta('green') ? 'green' : 'all');

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EventResource::eventColumns($data);
    }

    protected function afterSave(): void
    {
        /** @var Event $event */
        $event = $this->getRecord();

        EventResource::saveMeta($event, $this->form->getRawState());
    }
}
