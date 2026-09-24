<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows the login page', function () {
    $this->get('/login')->assertOk()->assertSee('Log in');
});

it('logs in an active member', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'secret123'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_activity)->not->toBeNull();
});

it('refuses a wrong password', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuses accounts that are not active', function (string $status) {
    User::factory()->withStatus($status)->create(['email' => 'anna@example.com']);

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'secret123'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with(['disabled', 'blocked', 'deleted', 'placeholder']);

it('limits login attempts', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => 'anna@example.com', 'password' => 'wrong']);
    }

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'secret123'])->assertStatus(429);
    $this->assertGuest();
});

it('upgrades a weak password hash on login', function () {
    $user = User::factory()->create(['email' => 'anna@example.com']);
    DB::table('bs_users')->where('uid', $user->uid)->update(['pw' => password_hash('secret123', PASSWORD_BCRYPT, ['cost' => 6])]);

    $this->post('/login', ['email' => 'anna@example.com', 'password' => 'secret123']);

    expect(password_get_info($user->fresh()->pw)['options']['cost'])->toBe((int) config('hashing.bcrypt.rounds'));
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

    $this->assertGuest();
});

it('puts a CSRF token in the login form', function () {
    $this->get('/login')->assertSee('name="_token"', false);
});
