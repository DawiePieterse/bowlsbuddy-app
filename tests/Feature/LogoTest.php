<?php

use App\Filament\Pages\SiteSettings;
use App\Models\User;
use App\Support\ClubLogo;
use Database\Seeders\ClubSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    ClubLogo::keepOnly(null);
});

afterEach(fn () => ClubLogo::keepOnly(null));

it('shows the bundled mark until a club logo is uploaded, then that one everywhere', function () {
    // Before an upload: the bundled Bowls Buddy mark, and no uploaded file to serve
    $this->get('/logo')->assertNotFound();
    $this->get('/')->assertOk()
        ->assertSee('class="logo"', false)
        ->assertSee('img/logo.png', false);

    @mkdir(storage_path('app/documents'), 0775, true);
    file_put_contents(storage_path('app/documents/logo.png'), UploadedFile::fake()->image('logo.png', 64, 64)->getContent());

    $this->get('/logo')->assertOk()->assertHeader('Content-Type', 'image/png');

    // Member pages: header image and favicon
    $this->get('/')->assertOk()
        ->assertSee('class="logo"', false)
        ->assertSee('rel="icon"', false);

    // Day sheet
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail())
        ->get('/greens/A/'.now()->addDay()->format('Y-m-d').'/sheet')
        ->assertOk()
        ->assertSee('/logo?v=', false);

    // Admin panel brand logo
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail())
        ->get('/admin')
        ->assertOk()
        ->assertSee('/logo?v=', false);
});

it('uploads a logo from the settings page', function () {
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail());

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'immediate',
            'logo' => UploadedFile::fake()->image('club-badge.png', 128, 128),
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(ClubLogo::path())->toEndWith('documents/logo.png');

    $this->get('/logo')->assertOk();
});

it('removes the stored logo when the upload is cleared', function () {
    @mkdir(storage_path('app/documents'), 0775, true);
    file_put_contents(storage_path('app/documents/logo.png'), UploadedFile::fake()->image('logo.png')->getContent());

    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail());

    Livewire::test(SiteSettings::class)
        ->fillForm([
            'client_name_full' => 'LCE Bowls Club',
            'client_name_short' => 'LCE',
            'activation' => 'immediate',
            'logo' => null,
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(ClubLogo::exists())->toBeFalse();

    $this->get('/logo')->assertNotFound();
});
