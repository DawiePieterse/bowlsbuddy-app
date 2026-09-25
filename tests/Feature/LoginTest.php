<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Log in')->assertSee('Mobile number');
});

it('logs in an active member with their mobile number', function () {
    $user = User::factory()->create(['phone' => '082 123 4567']);

    $this->post('/login', ['phone' => '082 123 4567', 'password' => 'secret123'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_activity)->not->toBeNull();
});

it('accepts the mobile number written in any usual way', function (string $typed) {
    $user = User::factory()->create(['phone' => '0821234567']);

    $this->post('/login', ['phone' => $typed, 'password' => 'secret123'])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
})->with(['082 123 4567', '082-123-4567', '+27 82 123 4567', '0027821234567', '27821234567']);

it('does not log in with an email address', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['phone' => 'anna@example.com', 'password' => 'secret123'])
        ->assertSessionHasErrors(['phone' => 'Please enter your mobile number, e.g. 082 123 4567.']);

    $this->assertGuest();
});

it('refuses a wrong password', function () {
    User::factory()->create(['phone' => '0821234567']);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'wrong'])
        ->assertSessionHasErrors('phone');

    $this->assertGuest();
});

it('refuses accounts that are not active', function (string $status) {
    User::factory()->withStatus($status)->create(['phone' => '0821234567']);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'secret123'])
        ->assertSessionHasErrors(['phone' => 'These details are not correct, or the account is not active.']);

    $this->assertGuest();
})->with(['blocked', 'deleted', 'placeholder']);

it('tells a new member the Secretary still has to activate the account', function () {
    User::factory()->awaitingActivation()->create(['phone' => '0821234567']);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'secret123'])
        ->assertSessionHasErrors(['phone' => 'Your account is waiting for the Club Secretary to activate it.']);

    $this->assertGuest();
});

it('does not say an account is waiting for activation without the right password', function () {
    User::factory()->awaitingActivation()->create(['phone' => '0821234567']);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'wrong'])
        ->assertSessionHasErrors(['phone' => 'These details are not correct, or the account is not active.']);
});

it('lets a member log in once the Secretary has activated the account', function () {
    $user = User::factory()->awaitingActivation()->create(['phone' => '0821234567']);
    $user->update(['status' => 'enabled']);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'secret123'])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('limits login attempts, however the number is written', function () {
    User::factory()->create(['phone' => '0821234567']);

    foreach (['082 123 4567', '0821234567', '+27821234567', '082-123-4567', '0027821234567'] as $typed) {
        $this->post('/login', ['phone' => $typed, 'password' => 'wrong']);
    }

    $this->post('/login', ['phone' => '0821234567', 'password' => 'secret123'])->assertStatus(429);
    $this->assertGuest();
});

it('upgrades a weak password hash on login', function () {
    $user = User::factory()->create(['phone' => '0821234567']);
    DB::table('bs_users')->where('uid', $user->uid)->update(['pw' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 6])]);

    $this->post('/login', ['phone' => '0821234567', 'password' => 'secret123']);

    expect(password_get_info($user->fresh()->pw)['options']['cost'])->toBe((int) config('hashing.bcrypt.rounds'));
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
});

it('puts a CSRF token in the login form', function () {
    $this->get('/login')->assertSee('name="_token"', false);
});
