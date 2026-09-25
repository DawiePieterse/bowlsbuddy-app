<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Maintenance;
use App\Models\User;
use Livewire\Livewire;

it('lets admins into the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('keeps members out of the admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('lets an assist in only with the admin menu privilege', function () {
    $assist = User::factory()->withStatus('assist')->create();
    $this->actingAs($assist)->get('/admin')->assertForbidden();

    $assist->setMeta('allow.admin.see-menu', 'true');
    $this->actingAs($assist->fresh())->get('/admin')->assertOk();
});

it('keeps the maintenance page from staff without the configuration privilege', function () {
    $assist = User::factory()->withStatus('assist')->create();
    $assist->setMeta('allow.admin.see-menu', 'true');

    $this->actingAs($assist->fresh())->get('/admin/maintenance')->assertForbidden();
});

it('shows admins the maintenance page', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/admin/maintenance')
        ->assertOk()->assertSee('The database is up to date');
});

it('clears caches from the maintenance page', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Maintenance::class)->callAction('clearCaches')->assertNotified('Caches cleared');
});

it('draws avatars locally instead of loading them from another website', function () {
    $this->actingAs(User::factory()->admin()->create(['alias' => 'Club Secretary']))
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('ui-avatars.com')
        ->assertSee('data:image/svg+xml;base64,', false);
});

it('logs the Secretary into the admin panel with the mobile number', function () {
    $admin = User::factory()->admin()->create(['phone' => '0821234567']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => '082 123 4567', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin);
});

it('does not log into the admin panel with an email address', function () {
    User::factory()->admin()->create(['email' => 'secretary@example.com']);

    Livewire::test(Login::class)
        ->fillForm(['phone' => 'secretary@example.com', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasFormErrors(['phone']);

    $this->assertGuest();
});
