<?php

use App\Filament\Auth\EditProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('shows staff their profile page', function () {
    $this->actingAs(User::factory()->admin()->create(['email' => 'secretary@example.com']))
        ->get('/admin/profile')
        ->assertOk()
        ->assertSee('secretary@example.com');
});

it('keeps members out of the profile page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/profile')->assertForbidden();
});

it('changes the email and password after checking the current password', function () {
    $user = User::factory()->admin()->create(['email' => 'secretary@example.com', 'pw' => 'old-password']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'alias' => 'Club Secretary',
            'email' => 'secretary@lce.co.za',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'currentPassword' => 'old-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->alias)->toBe('Club Secretary')
        ->and($user->email)->toBe('secretary@lce.co.za')
        ->and(Hash::check('new-password', $user->pw))->toBeTrue();
});

it('refuses a change without the right current password', function () {
    $user = User::factory()->admin()->create(['email' => 'secretary@example.com', 'pw' => 'old-password']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'email' => 'someone@else.co.za',
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'currentPassword' => 'wrong-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    $user->refresh();
    expect($user->email)->toBe('secretary@example.com')
        ->and(Hash::check('old-password', $user->pw))->toBeTrue();
});

it('refuses an email that another account uses', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $this->actingAs(User::factory()->admin()->create(['pw' => 'old-password']));

    Livewire::test(EditProfile::class)
        ->fillForm(['email' => 'taken@example.com', 'currentPassword' => 'old-password'])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('lets staff log in with the new password', function () {
    $user = User::factory()->admin()->create(['email' => 'secretary@example.com', 'pw' => 'old-password']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'password' => 'new-password',
            'passwordConfirmation' => 'new-password',
            'currentPassword' => 'old-password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    auth()->logout();

    expect(auth()->attempt(['email' => 'secretary@example.com', 'password' => 'new-password']))->toBeTrue();
});
