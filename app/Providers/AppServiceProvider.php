<?php

namespace App\Providers;

use App\Support\PhoneNumber;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
    }

    public function boot(): void
    {
        // Outside production, fail on lazy loading (catches one-query-per-row pages) and on unknown attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn () => Password::min(8));

        // Five login attempts per minute per mobile number and IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            (PhoneNumber::normalize((string) $request->input('phone')) ?? Str::lower((string) $request->input('phone'))).'|'.$request->ip()
        ));
    }
}
