<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Support\Phone;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * The admin panel's login takes a cellphone number or an email address, like the members' login:
 * members register by cellphone, so a member made an assistant or admin may have no email.
 *
 * Members land here too, sent the /admin link by mistake. Filament answers "These credentials do
 * not match our records" to anyone without panel access, which reads as a wrong password, so a
 * member whose details are right is logged in to the member site instead.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            $member = $this->memberWithoutPanelAccess();

            if ($member === null) {
                throw $exception;
            }

            auth()->login($member, (bool) ($this->data['remember'] ?? false));
            session()->regenerate();
            $member->forceFill(['last_activity' => now(), 'last_ip' => request()->ip()])->save();

            session()->flash('status', 'You are logged in. The admin panel is for club staff, so here is the member site.');
            $this->redirect(route('home'));

            return null;
        }
    }

    /**
     * The active member these details belong to, when they are right but the member may not use
     * the admin panel.
     */
    private function memberWithoutPanelAccess(): ?User
    {
        $data = $this->data ?? [];

        if (blank($data['email'] ?? null) || blank($data['password'] ?? null)) {
            return null;
        }

        $credentials = $this->getCredentialsFromFormData($data);

        /** @var EloquentUserProvider $provider */
        $provider = Filament::auth()->getProvider(); // @phpstan-ignore method.notFound
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user instanceof User || ! $user->canLogIn() || $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())
            || ! $provider->validateCredentials($user, $credentials)) {
            return null;
        }

        $provider->rehashPasswordIfRequired($user, $credentials);

        return $user;
    }

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
