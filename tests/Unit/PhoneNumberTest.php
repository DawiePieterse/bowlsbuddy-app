<?php

use App\Support\PhoneNumber;

it('stores mobile numbers in international form', function (string $typed, string $stored) {
    expect(PhoneNumber::normalize($typed))->toBe($stored);
})->with([
    ['0821234567', '+27821234567'],
    ['082 123 4567', '+27821234567'],
    ['(082) 123-4567', '+27821234567'],
    ['+27 82 123 4567', '+27821234567'],
    ['0027821234567', '+27821234567'],
    ['27821234567', '+27821234567'],
    ['+44 7700 900123', '+447700900123'],
]);

it('rejects what is not a phone number', function (?string $typed) {
    expect(PhoneNumber::normalize($typed))->toBeNull();
})->with([null, '', 'anna@example.com', '12345', '082 123', '+0821234567', '+27 82 123 4567 890 123']);
