<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return MemberResource::mapFormData($data);
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        MemberResource::saveMeta($user, $this->form->getRawState());
    }
}
