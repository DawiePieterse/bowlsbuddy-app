<?php

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

function registrationInput(array $overrides = []): array
{
    return array_merge([
        'firstname' => 'Jane',
        'lastname' => 'Bowler',
        'email' => 'jane@example.com',
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
        'accept_terms' => '1',
        'opened_at' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
    ], $overrides);
}

it('shows the registration form', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('First name')
        ->assertSee('Surname')
        ->assertSee('Business Terms')
        ->assertSee('Privacy Policy');
});

it('registers a member and logs them in', function () {
    $this->post('/register', registrationInput())
        ->assertRedirect(route('home'));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->status)->toBe('enabled')
        ->and($user->alias)->toBe('Jane Bowler')
        ->and($user->firstName())->toBe('Jane')
        ->and($user->lastName())->toBe('Bowler')
        ->and($user->meta('terms-accepted'))->not->toBeNull()
        ->and(auth()->check())->toBeTrue();
});

it('waits for the Secretary when activation is not immediate', function () {
    app(Settings::class)->set('service.user.activation', 'manual');

    $this->post('/register', registrationInput())
        ->assertRedirect(route('login'));

    expect(User::query()->where('email', 'jane@example.com')->firstOrFail()->status)->toBe('disabled')
        ->and(auth()->check())->toBeFalse();
});

it('requires accepting the terms', function () {
    $this->post('/register', registrationInput(['accept_terms' => null]))
        ->assertSessionHasErrors('accept_terms');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

it('refuses a submission faster than the anti-bot delay', function () {
    $this->post('/register', registrationInput([
        'opened_at' => Crypt::encryptString((string) now()->getTimestamp()),
    ]))->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

it('refuses a tampered timer and a filled honeypot', function () {
    $this->post('/register', registrationInput(['opened_at' => 'not-encrypted']))
        ->assertSessionHasErrors('email');

    $this->post('/register', registrationInput(['website' => 'https://spam.example']))
        ->assertSessionHasErrors('website');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

it('refuses a duplicate email address', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->post('/register', registrationInput())
        ->assertSessionHasErrors('email');
});
