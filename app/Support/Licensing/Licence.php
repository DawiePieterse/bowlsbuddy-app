<?php

namespace App\Support\Licensing;

use Illuminate\Support\Carbon;
use JsonException;
use SodiumException;

/**
 * A Bowls Buddy licence: which modules and how many greens a club has paid for, until when. The key is
 * base64url(JSON payload) "." base64url(Ed25519 signature), one line that is easy to paste or send on
 * WhatsApp. Only Bowls Buddy holds the private key, so a club can read its licence but not change it
 * or use it on another club's install. It is checked offline: shared hosting needs no licence server.
 */
final class Licence
{
    public const VERSION = 1;

    /**
     * @param  list<string>  $modules
     */
    public function __construct(
        public readonly string $keyId,
        public readonly string $club,
        public readonly string $plan,
        public readonly array $modules,
        public readonly ?int $greens,
        public readonly Carbon $issued,
        public readonly Carbon $expires,
        public readonly bool $trial = false,
    ) {}

    /**
     * Reads and checks a key against the known public keys. The host and expiry are checked by
     * Modules, so an expired licence still reads and the Secretary can see what it covered.
     *
     * @param  array<string, string>  $publicKeys  key id => base64 Ed25519 public key
     *
     * @throws InvalidLicence
     */
    public static function parse(string $key, array $publicKeys): self
    {
        $parts = explode('.', trim($key));

        if (count($parts) !== 2) {
            throw new InvalidLicence('This is not a Bowls Buddy licence key. Please paste the whole key.');
        }

        $json = self::decode($parts[0]);
        $signature = self::decode($parts[1]);

        try {
            /** @var mixed $payload */
            $payload = json_decode((string) $json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = null;
        }

        if ($json === null || $signature === null || ! is_array($payload)) {
            throw new InvalidLicence('This is not a Bowls Buddy licence key. Please paste the whole key.');
        }

        $keyId = is_string($payload['kid'] ?? null) ? $payload['kid'] : '';
        $publicKey = base64_decode($publicKeys[$keyId] ?? '', true);

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidLicence('This licence was signed with a key this version of Bowls Buddy does not know.');
        }

        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $json, $publicKey)) {
            throw new InvalidLicence('This licence key has been changed or is incomplete.');
        }

        if (($payload['v'] ?? null) !== self::VERSION) {
            throw new InvalidLicence('This licence needs a newer version of Bowls Buddy.');
        }

        return self::fromPayload($keyId, $payload);
    }

    /**
     * Signs a licence. Runs only where the private key is (licence:issue on Bowls Buddy's machine).
     *
     * @throws SodiumException
     */
    public function sign(string $secretKey): string
    {
        $json = json_encode($this->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return self::encode($json).'.'.self::encode(sodium_crypto_sign_detached($json, $secretKey));
    }

    /** @return array{v: int, kid: string, club: string, plan: string, modules: list<string>, greens: ?int, issued: string, expires: string, trial: bool} */
    public function payload(): array
    {
        return [
            'v' => self::VERSION,
            'kid' => $this->keyId,
            'club' => $this->club,
            'plan' => $this->plan,
            'modules' => $this->modules,
            'greens' => $this->greens,
            'issued' => $this->issued->toDateString(),
            'expires' => $this->expires->toDateString(),
            'trial' => $this->trial,
        ];
    }

    /** @param  array<mixed>  $payload */
    private static function fromPayload(string $keyId, array $payload): self
    {
        $modules = $payload['modules'] ?? null;
        $greens = $payload['greens'] ?? null;

        if (! is_string($payload['club'] ?? null) || ! is_array($modules)
            || ! is_string($payload['issued'] ?? null) || ! is_string($payload['expires'] ?? null)
            || ($greens !== null && ! is_int($greens))) {
            throw new InvalidLicence('This licence key is incomplete. Please ask Bowls Buddy for a new one.');
        }

        try {
            $issued = Carbon::createFromFormat('!Y-m-d', $payload['issued']);
            $expires = Carbon::createFromFormat('!Y-m-d', $payload['expires']);
        } catch (\Throwable) {
            $issued = $expires = null;
        }

        if (! $issued instanceof Carbon || ! $expires instanceof Carbon) {
            throw new InvalidLicence('This licence key is incomplete. Please ask Bowls Buddy for a new one.');
        }

        return new self(
            keyId: $keyId,
            club: strtolower($payload['club']),
            plan: is_string($payload['plan'] ?? null) ? $payload['plan'] : '',
            modules: array_values(array_filter($modules, 'is_string')),
            greens: $greens,
            issued: $issued,
            expires: $expires,
            trial: ($payload['trial'] ?? false) === true,
        );
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }
}
