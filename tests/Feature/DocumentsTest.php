<?php

use App\Filament\Pages\SiteSettings;
use App\Models\User;
use App\Support\Settings;
use App\Support\StandardTexts;
use Database\Seeders\ClubSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

it('shows every document as a page with its example text', function (string $url, string $title, string $example) {
    $this->get($url)->assertOk()->assertSee($title)->assertSee($example);
})->with([
    'info' => ['/info', 'Info', 'Club rules for bookings'],
    'help' => ['/help', 'Help', 'How to book'],
    'terms' => ['/terms', 'Business Terms', 'One rink per member per day. The member who books'],
    'privacy' => ['/privacy', 'Privacy Policy', 'Protection of Personal Information Act'],
]);

it('shows the club text once saved, and the example again when cleared', function () {
    app(Settings::class)->set('service.terms', '<p>Our own terms.</p>');

    $this->get('/terms')->assertOk()->assertSee('Our own terms.')->assertDontSee('The member who books');

    app(Settings::class)->set('service.terms', '<p></p>');

    $this->get('/terms')->assertOk()->assertSee('The member who books');
});

it('links the terms and privacy pages from registration and the Info page', function () {
    $this->get('/register')->assertOk()
        ->assertSee(route('terms'), false)
        ->assertSee(route('privacy'), false);

    $this->get('/info')->assertOk()
        ->assertSee(route('terms'), false)
        ->assertSee(route('privacy'), false);
});

it('edits all four documents on the settings page, starting from the example text', function () {
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail());

    $this->get('/admin/site-settings')->assertOk()
        ->assertSee('Club rules for bookings')
        ->assertSee('How to book')
        ->assertSee('The member who books')
        ->assertSee('Protection of Personal Information Act');

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'immediate',
            'privacy' => '<p>Our privacy promise.</p><script>alert(1)</script>',
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(StandardTexts::for('privacy'))->toContain('Our privacy promise.')->not->toContain('<script');

    $this->get('/privacy')->assertOk()->assertSee('Our privacy promise.');
});

it('no longer serves PDF documents', function () {
    $this->get('/documents/terms')->assertNotFound();
});
