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
