<?php

use App\Filament\Auth\Login;
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

it('lets staff log in to the admin panel with a cellphone number', function () {
    $admin = User::factory()->admin()->create(['phone' => '+27836554092', 'pw' => 'secret123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => '083 655 4092', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin);
});

it('still lets staff log in to the admin panel with an email address', function () {
    $admin = User::factory()->admin()->create(['email' => 'secretary@example.com', 'pw' => 'secret123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => 'secretary@example.com', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($admin);
});

it('refuses a wrong password or a member without admin access at the admin login', function () {
    User::factory()->admin()->create(['phone' => '+27836554092', 'pw' => 'secret123']);
    User::factory()->create(['phone' => '+27821234567', 'pw' => 'secret123']);

    Livewire::test(Login::class)
        ->fillForm(['email' => '0836554092', 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    Livewire::test(Login::class)
        ->fillForm(['email' => '0821234567', 'password' => 'secret123'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('links back to the member site and says "Log out", as the member pages do', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Member site')
        ->assertSee('Log out')
        ->assertDontSee('Sign out');
});
