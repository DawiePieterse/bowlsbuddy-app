<?php

namespace App\Support;

/**
 * South African cellphone (WhatsApp) numbers, stored normalised as +27821234567. Members type
 * them any way they like: 082 123 4567, 0821234567, +27 82 123 4567, 27821234567.
 */
class Phone
{
    /**
     * The normalised number, or null when it isn't a valid SA cellphone number.
     */
    public static function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number) ?? '';

        if (str_starts_with($digits, '27') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        // A local number: 10 digits starting 06/07/08 (SA mobile ranges).
        if (! preg_match('/^0[678]\d{8}$/', $digits)) {
            return null;
        }

        return '+27'.substr($digits, 1);
    }

    /** For display: +27821234567 -> 082 123 4567. */
    public static function pretty(?string $normalized): string
    {
        if ($normalized === null || ! str_starts_with($normalized, '+27')) {
            return (string) $normalized;
        }

        $local = '0'.substr($normalized, 3);

        return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
    }
}
