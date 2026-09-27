<?php

use App\Support\Settings;
use Database\Seeders\ClubSeeder;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

it('shows the info page with the Secretary text when set', function () {
    $this->get('/info')->assertOk()->assertSee('Club rules for bookings');

    app(Settings::class)->set('service.info', '<p>Roll-ups every weekday.</p>');

    $this->get('/info')->assertOk()->assertSee('Roll-ups every weekday.')->assertDontSee('Club rules for bookings');

    // An emptied editor brings the standard text back
    app(Settings::class)->set('service.info', '<p></p>');
    $this->get('/info')->assertOk()->assertSee('Club rules for bookings');
});

it('shows the help page with a default guide', function () {
    $this->get('/help')->assertOk()->assertSee('How to book');
});

it('shows the forgot password page pointing to the Secretary', function () {
    $this->get('/forgot-password')->assertOk()->assertSee('Club Secretary');
});
