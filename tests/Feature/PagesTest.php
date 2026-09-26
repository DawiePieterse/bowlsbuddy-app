<?php

use App\Support\Settings;
use Database\Seeders\ClubSeeder;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

it('shows the info page with the Secretary text when set', function () {
    $this->get('/info')->assertOk()->assertSee('has not written this page yet');

    app(Settings::class)->set('service.info', '<p>Roll-ups every weekday.</p>');

    $this->get('/info')->assertOk()->assertSee('Roll-ups every weekday.');
});

it('shows the help page with a default guide', function () {
    $this->get('/help')->assertOk()->assertSee('How to book');
});

it('shows the forgot password page pointing to the Secretary', function () {
    $this->get('/forgot-password')->assertOk()->assertSee('Club Secretary');
});

it('serves the terms and privacy PDFs once uploaded', function () {
    $this->get('/documents/terms')->assertNotFound();

    @mkdir(storage_path('app/documents'), 0775, true);
    file_put_contents(storage_path('app/documents/terms.pdf'), '%PDF-1.4 test');

    try {
        $this->get('/documents/terms')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    } finally {
        unlink(storage_path('app/documents/terms.pdf'));
    }

    $this->get('/documents/other')->assertNotFound();
});
