<?php

use App\Filament\Pages\SiteSettings;
use App\Models\User;
use App\Support\ClubDocuments;
use Database\Seeders\ClubSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    array_map([ClubDocuments::class, 'remove'], array_keys(ClubDocuments::ALL));
});

afterEach(fn () => array_map([ClubDocuments::class, 'remove'], array_keys(ClubDocuments::ALL)));

function putPdf(string $name): void
{
    @mkdir(storage_path('app/documents'), 0775, true);
    file_put_contents(ClubDocuments::path($name), '%PDF-1.4 '.$name);
}

it('serves every document as a PDF once uploaded', function (string $name) {
    $this->get('/documents/'.$name)->assertNotFound();

    putPdf($name);

    $this->get('/documents/'.$name)->assertOk()->assertHeader('Content-Type', 'application/pdf');
})->with(['info', 'help', 'terms', 'privacy']);

it('links the info and help PDFs from their pages only when uploaded', function () {
    $this->get('/info')->assertOk()->assertDontSee('Open the info sheet (PDF)')->assertDontSee('Business Terms');
    $this->get('/help')->assertOk()->assertDontSee('Open the help guide (PDF)');

    putPdf('info');
    putPdf('help');
    putPdf('terms');

    $this->get('/info')->assertOk()
        ->assertSee('Open the info sheet (PDF)')
        ->assertSee('Business Terms')
        ->assertDontSee('Privacy Policy')
        ->assertDontSee('has not written this page yet');
    $this->get('/help')->assertOk()->assertSee('Open the help guide (PDF)');
});

it('uploads and removes the PDFs from the settings page', function () {
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail());

    $base = ['client_name_full' => 'LCE Bowls Club', 'client_name_short' => 'LCE', 'activation' => 'immediate'];

    Livewire::test(SiteSettings::class)
        ->fillForm($base + [
            'info_pdf' => UploadedFile::fake()->create('club-info.pdf', 20, 'application/pdf'),
            'help_pdf' => UploadedFile::fake()->create('how-to.pdf', 20, 'application/pdf'),
        ])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(ClubDocuments::exists('info'))->toBeTrue()
        ->and(ClubDocuments::exists('help'))->toBeTrue();

    Livewire::test(SiteSettings::class)
        ->fillForm($base + ['info_pdf' => null])
        ->call('save')
        ->assertNotified('Settings saved');

    expect(ClubDocuments::exists('info'))->toBeFalse()
        ->and(ClubDocuments::exists('help'))->toBeTrue();
});
