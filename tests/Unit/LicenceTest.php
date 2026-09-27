<?php

use App\Support\Licensing\InvalidLicence;
use App\Support\Licensing\Licence;
use Illuminate\Support\Carbon;

function signedLicence(array $overrides = []): array
{
    $pair = sodium_crypto_sign_keypair();
    $licence = new Licence(...array_merge([
        'keyId' => 'k1',
        'club' => 'lce.bowlsbuddy.co.za',
        'plan' => 'club',
        'modules' => ['rollups', 'competitions'],
        'greens' => 2,
        'issued' => Carbon::parse('2026-10-01'),
        'expires' => Carbon::parse('2027-09-30'),
    ], $overrides));

    return [$licence->sign(sodium_crypto_sign_secretkey($pair)), ['k1' => base64_encode(sodium_crypto_sign_publickey($pair))]];
}

it('reads back what was signed', function () {
    [$key, $keys] = signedLicence();

    $licence = Licence::parse($key, $keys);

    expect($licence->club)->toBe('lce.bowlsbuddy.co.za')
        ->and($licence->plan)->toBe('club')
        ->and($licence->modules)->toBe(['rollups', 'competitions'])
        ->and($licence->greens)->toBe(2)
        ->and($licence->expires->toDateString())->toBe('2027-09-30')
        ->and($licence->trial)->toBeFalse()
        ->and($key)->not->toContain('+', '/', '=', ' ');
});

it('reads a licence without a green limit', function () {
    [$key, $keys] = signedLicence(['greens' => null]);

    expect(Licence::parse($key, $keys)->greens)->toBeNull();
});

it('refuses a changed payload', function () {
    [$key, $keys] = signedLicence();
    [$payload, $signature] = explode('.', $key);

    $json = str_replace('"greens":2', '"greens":9', base64_decode(strtr($payload, '-_', '+/')));
    $tampered = rtrim(strtr(base64_encode($json), '+/', '-_'), '=').'.'.$signature;

    expect(fn () => Licence::parse($tampered, $keys))->toThrow(InvalidLicence::class, 'changed or is incomplete');
});

it('refuses a changed signature', function () {
    [$key, $keys] = signedLicence();
    [$payload] = explode('.', $key);
    [, $otherSignature] = explode('.', signedLicence()[0]);

    expect(fn () => Licence::parse($payload.'.'.$otherSignature, $keys))->toThrow(InvalidLicence::class, 'changed or is incomplete');
});

it('refuses a key signed with an unknown key', function () {
    [$key] = signedLicence();
    [, $otherKeys] = signedLicence();

    expect(fn () => Licence::parse($key, ['k2' => $otherKeys['k1']]))->toThrow(InvalidLicence::class, 'does not know')
        ->and(fn () => Licence::parse($key, []))->toThrow(InvalidLicence::class, 'does not know');
});

it('refuses keys that are not licences', function (string $key) {
    [, $keys] = signedLicence();

    expect(fn () => Licence::parse($key, $keys))->toThrow(InvalidLicence::class);
})->with([
    'empty' => '',
    'words' => 'not a licence',
    'one part' => 'eyJ2IjoxfQ',
    'three parts' => 'a.b.c',
    'not json' => 'bm90IGpzb24.c2ln',
]);

it('ignores spaces and line breaks around a pasted key', function () {
    [$key, $keys] = signedLicence();

    expect(Licence::parse("  {$key}\n", $keys)->club)->toBe('lce.bowlsbuddy.co.za');
});
