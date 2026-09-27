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
        'phone' => '082 123 4567',
        'password' => 'a-good-password',
        'password_confirmation' => 'a-good-password',
        'accept_terms' => '1',
        'opened_at' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp()),
    ], $overrides);
}

it('shows the registration form with the cellphone field and no email field', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('First name')
        ->assertSee('Surname')
        ->assertSee('Cellphone number (WhatsApp)')
        ->assertDontSee('Email address')
        ->assertSee('Business Terms')
        ->assertSee('Privacy Policy');
});

it('registers a member by cellphone number and logs them in when activation is immediate', function () {
    app(Settings::class)->set('service.user.activation', 'immediate');

    $this->post('/register', registrationInput())
        ->assertRedirect(route('home'));

    $user = User::query()->where('phone', '+27821234567')->firstOrFail();

    expect($user->status)->toBe('enabled')
        ->and($user->alias)->toBe('Jane Bowler')
        ->and($user->email)->toBeNull()
        ->and($user->firstName())->toBe('Jane')
        ->and($user->meta('terms-accepted'))->not->toBeNull()
        ->and(auth()->check())->toBeTrue();
});

it('normalises the number however it is typed', function (string $typed) {
    $this->post('/register', registrationInput(['phone' => $typed]));

    expect(User::query()->where('phone', '+27821234567')->exists())->toBeTrue();
})->with([
    'plain' => '0821234567',
    'international' => '+27 82 123 4567',
    'no plus' => '27821234567',
]);

it('refuses a number that is not a South African cellphone', function (string $typed) {
    $this->post('/register', registrationInput(['phone' => $typed]))
        ->assertSessionHasErrors('phone');

    expect(User::query()->where('alias', 'Jane Bowler')->exists())->toBeFalse();
})->with([
    'landline' => '011 612 7200',
    'too short' => '082 123',
    'words' => 'not a number',
]);

it('refuses a duplicate cellphone number', function () {
    User::factory()->create(['phone' => '+27821234567']);

    $this->post('/register', registrationInput())
        ->assertSessionHasErrors('phone');
});

it('waits for the Secretary to approve new members by default', function () {
    expect(app(Settings::class)->get('service.user.activation'))->toBe('manual');

    $this->post('/register', registrationInput())
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Thank you! The Club Secretary will activate your account.');

    expect(User::query()->where('phone', '+27821234567')->firstOrFail()->status)->toBe('disabled')
        ->and(auth()->check())->toBeFalse();
});

it('requires accepting the terms', function () {
    $this->post('/register', registrationInput(['accept_terms' => null]))
        ->assertSessionHasErrors('accept_terms');

    expect(User::query()->where('phone', '+27821234567')->exists())->toBeFalse();
});

it('refuses a submission faster than the anti-bot delay', function () {
    $this->post('/register', registrationInput([
        'opened_at' => Crypt::encryptString((string) now()->getTimestamp()),
    ]))->assertSessionHasErrors('phone');

    expect(User::query()->where('phone', '+27821234567')->exists())->toBeFalse();
});

it('refuses a tampered timer and a filled honeypot', function () {
    $this->post('/register', registrationInput(['opened_at' => 'not-encrypted']))
        ->assertSessionHasErrors('phone');

    $this->post('/register', registrationInput(['website' => 'https://spam.example']))
        ->assertSessionHasErrors('website');

    expect(User::query()->where('phone', '+27821234567')->exists())->toBeFalse();
});
