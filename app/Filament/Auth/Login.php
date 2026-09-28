<?php

namespace App\Filament\Auth;

use App\Support\Phone;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

/**
 * The admin panel's login takes a cellphone number or an email address, like the members' login:
 * members register by cellphone, so a member made an assistant or admin may have no email.
 */
class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        // The field keeps the name "email" so Filament's failure message lands under it.
        return TextInput::make('email')
            ->label('Cellphone number or email')
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = trim((string) $data['email']);
        $phone = Phone::normalize($login);

        return [
            ...($phone !== null ? ['phone' => $phone] : ['email' => $login]),
            'password' => $data['password'],
        ];
    }
}
