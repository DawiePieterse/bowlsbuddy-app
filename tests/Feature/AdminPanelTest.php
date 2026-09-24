<?php

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
