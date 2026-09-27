<?php

namespace App\Console\Commands;

use App\Support\Licensing\InvalidLicence;
use App\Support\Licensing\Modules;
use Illuminate\Console\Command;

/** Installs a licence key on this club's install; the Secretary can also paste it on the Licence page. */
class LicenceInstall extends Command
{
    protected $signature = 'licence:install {key : The licence key}';

    protected $description = 'Install a Bowls Buddy licence key';

    public function handle(Modules $modules): int
    {
        try {
            $licence = $modules->install((string) $this->argument('key'));
        } catch (InvalidLicence $invalid) {
            $this->error($invalid->getMessage());

            return self::FAILURE;
        }

        $this->info("Licence installed for {$licence->club}, valid until {$licence->expires->toDateString()}.");

        return $this->call('licence:show');
    }
}
