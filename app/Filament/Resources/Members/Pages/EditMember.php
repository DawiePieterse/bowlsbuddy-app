<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        $data['firstname'] = $user->firstName();
        $data['lastname'] = $user->lastName();
        $data['privileges'] = array_values(array_filter(
            array_keys(User::PRIVILEGES),
            fn (string $privilege) => $user->meta('allow.'.$privilege) === 'true',
        ));

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return MemberResource::mapFormData($data);
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();

        MemberResource::saveMeta($user, $this->form->getRawState());
    }
}
