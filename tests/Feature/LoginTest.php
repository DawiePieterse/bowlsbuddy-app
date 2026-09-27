<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Log in')->assertSee('Cellphone number or email');
});

it('logs in a member by cellphone number, typed any which way', function (string $typed) {
    $user = User::factory()->create(['phone' => '+27821234567']);

    $this->post('/login', ['login' => $typed, 'password' => 'secret123'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_activity)->not->toBeNull();
})->with([
    'spaced' => '082 123 4567',
    'plain' => '0821234567',
    'international' => '+27 82 123 4567',
]);

it('logs in staff by email address', function () {
    $user = User::factory()->admin()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['login' => 'anna@example.com', 'password' => 'secret123'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('refuses a wrong password', function () {
    User::factory()->create(['phone' => '+27821234567']);

    $this->post('/login', ['login' => '0821234567', 'password' => 'wrong'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
});

it('refuses accounts that are not active', function (string $status) {
    User::factory()->withStatus($status)->create(['email' => 'anna@example.com']);

    $this->post('/login', ['login' => 'anna@example.com', 'password' => 'secret123'])
        ->assertSessionHasErrors('login');

    $this->assertGuest();
})->with(['disabled', 'blocked', 'deleted', 'placeholder']);

it('limits login attempts', function () {
    User::factory()->create(['phone' => '+27821234567']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['login' => '0821234567', 'password' => 'wrong']);
    }

    $this->post('/login', ['login' => '0821234567', 'password' => 'secret123'])->assertStatus(429);
    $this->assertGuest();
});

it('upgrades a weak password hash on login', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);
    DB::table('bs_users')->where('uid', $user->uid)->update(['pw' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 6])]);

    $this->post('/login', ['login' => 'anna@example.com', 'password' => 'secret123']);

    expect(password_get_info($user->fresh()->pw)['options']['cost'])->toBe((int) config('hashing.bcrypt.rounds'));
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
});

it('puts a CSRF token in the login form', function () {
    $this->get('/login')->assertSee('name="_token"', false);
});
