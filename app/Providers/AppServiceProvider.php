<?php

namespace App\Providers;

use App\Support\Licensing\Modules;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Modules::class);
    }

    public function boot(): void
    {
        // Outside production, fail on lazy loading (catches one-query-per-row pages) and on unknown attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // @module('competitions') ... @endmodule shows its content only when the club has that module.
        Blade::if('module', fn (string $module) => $this->app->make(Modules::class)->enabled($module));

        Password::defaults(fn () => Password::min(8));

        // Five login attempts per minute per identifier (cellphone number or email) and IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('login')).'|'.$request->ip()
        ));
    }
}
