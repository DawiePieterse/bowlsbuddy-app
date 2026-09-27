<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Makes a new Ed25519 key pair for signing licences. Run it on your own machine: the public key goes
 * into config/modules.php, the private key into your password manager and the .env of the machine
 * that issues licences, never onto a club server.
 */
class LicenceKeygen extends Command
{
    protected $signature = 'licence:keygen {id? : Key id, for example 2026-1}';

    protected $description = 'Make a key pair for signing Bowls Buddy licences';

    public function handle(): int
    {
        $id = (string) ($this->argument('id') ?? now()->format('Y').'-1');
        $pair = sodium_crypto_sign_keypair();

        $this->line('Add to config/modules.php, under public_keys:');
        $this->line("    '{$id}' => '".base64_encode(sodium_crypto_sign_publickey($pair))."',");
        $this->newLine();
        $this->line('Keep secret (password manager, and the .env where you issue licences):');
        $this->line('BB_LICENCE_KEY_ID='.$id);
        $this->line('BB_LICENCE_PRIVATE_KEY='.base64_encode(sodium_crypto_sign_secretkey($pair)));

        return self::SUCCESS;
    }
}
