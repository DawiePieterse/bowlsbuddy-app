<?php

use App\Filament\Pages\Licence as LicencePage;
use App\Models\User;
use App\Services\GreenManager;
use App\Support\Licensing\InvalidLicence;
use App\Support\Licensing\Modules;
use App\Support\Licensing\ModuleState;
use App\Support\Settings;
use Database\Seeders\ClubSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    config(['club.admin_password' => 'a-good-password']);
    $this->seed(ClubSeeder::class);
    Carbon::setTestNow('2026-10-06 10:00');
    $this->modules = app(Modules::class);
});

describe('without a licence', function () {
    it('has bookings only', function () {
        expect($this->modules->licence())->toBeNull()
            ->and($this->modules->problem())->toBeNull()
            ->and($this->modules->state('bookings'))->toBe(ModuleState::Active)
            ->and($this->modules->state('competitions'))->toBe(ModuleState::Locked)
            ->and($this->modules->enabled('rollups'))->toBeFalse()
            ->and($this->modules->greens())->toBeNull();
    });

    it('does not limit greens', function () {
        app(GreenManager::class)->add('C', 4);

        expect(app(GreenManager::class)->all())->toHaveKey('C');
    });
});

describe('module states', function () {
    it('switches on the licensed modules', function () {
        withModules(['rollups', 'competitions']);

        expect($this->modules->state('rollups'))->toBe(ModuleState::Active)
            ->and($this->modules->writable('competitions'))->toBeTrue()
            ->and($this->modules->state('comms'))->toBe(ModuleState::Locked);
    });

    it('needs every module a module depends on', function () {
        withModules(['leagues', 'stats']);

        expect($this->modules->state('leagues'))->toBe(ModuleState::Locked)
            ->and($this->modules->state('stats'))->toBe(ModuleState::Locked);

        withModules(['leagues', 'competitions']);

        expect($this->modules->state('leagues'))->toBe(ModuleState::Active);
    });

    it('ignores unknown modules in a licence', function () {
        withModules(['rollups', 'teleporting']);

        expect($this->modules->state('teleporting'))->toBe(ModuleState::Locked)
            ->and($this->modules->state('rollups'))->toBe(ModuleState::Active);
    });

    it('stays active up to and including the last paid day', function () {
        withModules(['rollups'], expires: '2026-10-06');

        expect($this->modules->state('rollups'))->toBe(ModuleState::Active);
    });

    it('gives 14 days of grace after expiry, then turns read-only', function () {
        withModules(['rollups'], expires: '2026-09-22');
        expect($this->modules->state('rollups'))->toBe(ModuleState::Grace)
            ->and($this->modules->writable('rollups'))->toBeTrue();

        withModules(['rollups'], expires: '2026-09-21');
        expect($this->modules->state('rollups'))->toBe(ModuleState::ReadOnly)
            ->and($this->modules->enabled('rollups'))->toBeTrue()
            ->and($this->modules->writable('rollups'))->toBeFalse();
    });

    it('never locks bookings, even when the licence has long expired', function () {
        withModules([], expires: '2025-01-01');

        expect($this->modules->state('bookings'))->toBe(ModuleState::Active);
    });
});

describe('installing', function () {
    it('stores the key through Settings', function () {
        $licence = withModules(['comms'], greens: 2);

        expect(app(Settings::class)->get(Modules::SETTING))->not->toBeNull()
            ->and($licence->greens)->toBe(2)
            ->and($this->modules->greens())->toBe(2)
            ->and($this->modules->expiresAt()?->toDateString())->toBe('2027-10-06');
    });

    it('refuses a licence for another club and keeps the current one', function () {
        withModules(['comms']);

        expect(fn () => $this->modules->install(licenceKey(['comms', 'rollups'], club: 'other.bowlsbuddy.co.za')))
            ->toThrow(InvalidLicence::class, 'This licence is for other.bowlsbuddy.co.za');

        expect($this->modules->enabled('comms'))->toBeTrue()
            ->and($this->modules->enabled('rollups'))->toBeFalse();
    });

    it('reports a stored licence that no longer verifies, and falls back to bookings only', function () {
        withModules(['comms']);
        config(['modules.public_keys' => []]);
        app()->forgetInstance(Modules::class);
        $modules = app(Modules::class);

        expect($modules->licence())->toBeNull()
            ->and($modules->problem())->toContain('does not know')
            ->and($modules->enabled('comms'))->toBeFalse()
            ->and($modules->enabled('bookings'))->toBeTrue();
    });

    it('removes a licence', function () {
        withModules(['comms']);
        $this->modules->uninstall();

        expect($this->modules->licence())->toBeNull()
            ->and($this->modules->enabled('comms'))->toBeFalse();
    });
});

describe('green limit', function () {
    it('refuses a green beyond the licence', function () {
        withModules([], greens: 2);

        expect(fn () => app(GreenManager::class)->add('C', 4))
            ->toThrow(RuntimeException::class, 'Your plan covers 2 greens. Contact Bowls Buddy to add another.');
    });

    it('adds a green within the licence', function () {
        withModules([], greens: 3);
        app(GreenManager::class)->add('C', 4);

        expect(app(GreenManager::class)->all())->toHaveKey('C');
    });

    it('keeps greens the licence no longer covers', function () {
        withModules([], greens: 1);

        expect(app(GreenManager::class)->all())->toHaveKeys(['A', 'B'])
            ->and(rink('B-1')->status)->toBe('enabled');
    });
});

describe('gates', function () {
    beforeEach(function () {
        Route::middleware(['web', 'module:rollups'])->match(['get', 'post'], '/_test/rollups', fn () => 'ok');
    });

    it('hides a route of a module the club has not got', function () {
        $this->get('/_test/rollups')->assertNotFound();
    });

    it('opens a route of a licensed module', function () {
        withModules(['rollups']);

        $this->get('/_test/rollups')->assertOk();
        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/_test/rollups')->assertOk();
    });

    it('lets a read-only module be read but not changed', function () {
        withModules(['rollups'], expires: '2026-01-01');

        $this->get('/_test/rollups')->assertOk();
        $this->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/_test/rollups')->assertForbidden();
    });

    it('shows Blade content only for a licensed module', function () {
        $view = "@module('rollups') Roll-ups menu @endmodule";

        expect(Blade::render($view))->not->toContain('Roll-ups menu');

        withModules(['rollups']);

        expect(Blade::render($view))->toContain('Roll-ups menu');
    });
});

describe('Licence page', function () {
    it('keeps the page from staff without the configuration privilege', function () {
        $this->actingAs(staff('admin.see-menu'))->get('/admin/licence')->assertForbidden();
    });

    it('shows bookings only without a licence', function () {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/licence')
            ->assertOk()
            ->assertSee('No licence is installed')
            ->assertSee('Competitions')
            ->assertSee('Not included');
    });

    it('shows the licence, the modules and the greens', function () {
        withModules(['rollups'], expires: '2027-09-30', greens: 2);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/licence')
            ->assertOk()
            ->assertSee(Modules::host())
            ->assertSee('30 September 2027')
            ->assertSee('2 in use, 2 paid for');
    });

    it('installs a pasted key', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(LicencePage::class)
            ->set('data.key', licenceKey(['comms']))
            ->call('install')
            ->assertNotified('Licence installed');

        expect(app(Modules::class)->enabled('comms'))->toBeTrue();
    });

    it('refuses a bad key with the reason', function () {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(LicencePage::class)
            ->set('data.key', 'not a licence')
            ->call('install')
            ->assertNotified('Licence not installed');

        expect(app(Modules::class)->licence())->toBeNull();
    });
});

describe('expiry banner', function () {
    it('is not shown while the licence is paid', function () {
        withModules(['rollups']);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()->assertDontSee('licence expired');
    });

    it('asks for renewal during the grace period', function () {
        withModules(['rollups'], expires: '2026-10-01');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()
            ->assertSee('The Bowls Buddy licence expired on 1 October 2026.')
            ->assertSee('Please renew by 15 October 2026');
    });

    it('says the modules are read-only after the grace period', function () {
        withModules(['rollups'], expires: '2026-09-01');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()
            ->assertSee('read-only until it is renewed');
    });
});

describe('commands', function () {
    it('issues a licence that installs', function () {
        $pair = sodium_crypto_sign_keypair();
        config([
            'modules.private_key' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'modules.private_key_id' => 'cli',
            'modules.public_keys' => ['cli' => base64_encode(sodium_crypto_sign_publickey($pair))],
        ]);

        $status = Artisan::call('licence:issue', [
            '--club' => Modules::host(), '--modules' => 'rollups,comms', '--greens' => 2, '--expires' => '2027-09-30',
        ]);
        $key = trim(Artisan::output());

        expect($status)->toBe(0);
        $this->artisan('licence:install', ['key' => $key])->assertSuccessful();

        expect(app(Modules::class)->enabled('comms'))->toBeTrue()
            ->and(app(Modules::class)->greens())->toBe(2);
    });

    it('refuses to issue without the private key or with unknown modules', function () {
        $this->artisan('licence:issue', ['--club' => 'x.example', '--expires' => '2027-01-01'])->assertFailed();

        $pair = sodium_crypto_sign_keypair();
        config(['modules.private_key' => base64_encode(sodium_crypto_sign_secretkey($pair)), 'modules.private_key_id' => 'cli']);

        $this->artisan('licence:issue', ['--club' => 'x.example', '--expires' => '2027-01-01', '--modules' => 'nope'])
            ->expectsOutputToContain('Unknown modules: nope')
            ->assertFailed();

        $this->artisan('licence:issue', ['--club' => 'x.example', '--expires' => 'soon'])->assertFailed();
    });

    it('refuses to install a bad key', function () {
        $this->artisan('licence:install', ['key' => 'nonsense'])->assertFailed();
    });

    it('shows the licence', function () {
        withModules(['rollups'], greens: 2);

        $this->artisan('licence:show')
            ->expectsOutputToContain('Greens: 2 in use, 2 paid for')
            ->assertSuccessful();
    });

    it('makes a key pair', function () {
        $this->artisan('licence:keygen', ['id' => '2026-1'])
            ->expectsOutputToContain("'2026-1' => '")
            ->expectsOutputToContain('BB_LICENCE_PRIVATE_KEY=')
            ->assertSuccessful();
    });
});
