<?php

namespace App\Support;

/**
 * Mobile numbers as members type them ("082 123 4567", "+27 82 123 4567", "0027821234567"), stored in one
 * international form ("+27821234567") so that each number matches exactly one account.
 */
class PhoneNumber
{
    /** Country code for numbers typed with a leading 0 (South Africa). */
    private const COUNTRY_CODE = '27';

    public static function normalize(?string $number): ?string
    {
        $number = preg_replace('/[\s\-().]/', '', (string) $number);

        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        } elseif (preg_match('/^0\d{9}$/', $number)) {
            $number = '+'.self::COUNTRY_CODE.substr($number, 1);
        } elseif (preg_match('/^'.self::COUNTRY_CODE.'\d{9}$/', $number)) {
            $number = '+'.$number;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) ? $number : null;
    }
}
