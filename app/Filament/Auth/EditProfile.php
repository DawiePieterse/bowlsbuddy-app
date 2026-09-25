<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Lets staff change their own name, email and password (bs_users.alias, email and pw).
 * Changing the email or password asks for the current password first.
 */
class EditProfile extends BaseEditProfile
{
    protected function getNameFormComponent(): Component
    {
        return TextInput::make('alias')
            ->label('Name')
            ->required()
            ->maxLength(128)
            ->autofocus();
    }

    protected function getEmailFormComponent(): Component
    {
        /** @var TextInput $email */
        $email = parent::getEmailFormComponent();

        return $email->maxLength(128);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        // The form's "password" (already hashed) is stored in the pw column.
        if (array_key_exists('password', $data)) {
            $data['pw'] = $data['password'];
            unset($data['password']);
        }

        return parent::handleRecordUpdate($record, $data);
    }
}
