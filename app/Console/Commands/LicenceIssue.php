<?php

namespace App\Console\Commands;

use App\Support\Licensing\Licence;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Signs a licence for a club, matching its invoice (docs/MODULES.md section 7). Works only where the
 * private key is set, which is never a club server.
 */
class LicenceIssue extends Command
{
    protected $signature = 'licence:issue
        {--club= : The club\'s host, for example lce.bowlsbuddy.co.za}
        {--plan=bookings : Plan or bundle name, shown to the Secretary}
        {--modules= : Comma-separated module keys, for example rollups,competitions}
        {--greens= : Greens paid for (leave out for no limit)}
        {--expires= : Last paid day, YYYY-MM-DD}
        {--trial : Mark it as a trial}';

    protected $description = 'Sign a Bowls Buddy licence for a club';

    public function handle(): int
    {
        $secret = base64_decode((string) config('modules.private_key'), true);
        $keyId = (string) config('modules.private_key_id');

        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES || $keyId === '') {
            $this->error('Set BB_LICENCE_PRIVATE_KEY and BB_LICENCE_KEY_ID first (php artisan licence:keygen).');

            return self::FAILURE;
        }

        $club = strtolower(trim((string) $this->option('club')));
        try {
            $expires = Carbon::createFromFormat('!Y-m-d', (string) $this->option('expires')) ?: null;
        } catch (\Throwable) {
            $expires = null;
        }

        if ($club === '' || $expires === null) {
            $this->error('Give at least --club and --expires.');

            return self::FAILURE;
        }

        $known = array_keys((array) config('modules.modules'));
        $modules = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $this->option('modules'))))));

        if (($unknown = array_diff($modules, $known)) !== []) {
            $this->error('Unknown modules: '.implode(', ', $unknown).'. Known: '.implode(', ', $known).'.');

            return self::FAILURE;
        }

        $greens = $this->option('greens');

        $licence = new Licence(
            keyId: $keyId,
            club: $club,
            plan: (string) $this->option('plan'),
            modules: $modules,
            greens: $greens === null || $greens === '' ? null : max(1, (int) $greens),
            issued: Carbon::today(),
            expires: $expires,
            trial: (bool) $this->option('trial'),
        );

        $this->line($licence->sign($secret));

        return self::SUCCESS;
    }
}
