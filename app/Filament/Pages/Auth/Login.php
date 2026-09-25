<?php

namespace App\Filament\Pages\Auth;

use App\Support\PhoneNumber;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * The admin panel's login, with the mobile number instead of the email address (as on the member site).
 */
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPhoneFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Mobile number')
            ->tel()
            ->placeholder('082 123 4567')
            ->required()
            ->autocomplete('tel')
            ->autofocus();
    }

    /** @return array<string, mixed> */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'phone' => PhoneNumber::normalize($data['phone']) ?? '',
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
