<?php

use App\Filament\Pages\Maintenance;
use App\Models\Rink;
use App\Models\User;
use App\Services\DatabaseBackup;
use Database\Seeders\ClubSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
});

it('dumps every table with its data as SQL', function () {
    $sql = '';

    app(DatabaseBackup::class)->dump(function (string $chunk) use (&$sql) {
        $sql .= $chunk;
    });

    expect($sql)->toContain('CREATE TABLE `bs_users`')
        ->and($sql)->toContain('CREATE TABLE `bs_squares`')
        ->and($sql)->toContain('CREATE TABLE `bb_migrations`')
        ->and($sql)->toContain('secretary@example.com')
        ->and($sql)->toContain('INSERT INTO `bs_squares`')
        ->and($sql)->toStartWith('-- Bowls Buddy backup');

    // The dump restores: reload it into the same database (comment lines stripped per statement)
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
        $statement = trim(implode("\n", array_filter(
            explode("\n", $statement),
            fn (string $line) => ! str_starts_with(trim($line), '--'),
        )));

        if ($statement !== '') {
            DB::unprepared($statement.';');
        }
    }

    expect(Rink::query()->count())->toBe(12)
        ->and(User::query()->where('email', 'secretary@example.com')->exists())->toBeTrue();
});

it('downloads the backup from the maintenance page', function () {
    $this->actingAs(User::query()->where('email', 'secretary@example.com')->firstOrFail());

    Livewire::test(Maintenance::class)
        ->callAction('downloadBackup')
        ->assertFileDownloaded();
});
