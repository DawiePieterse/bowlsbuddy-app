<?php

namespace App\Services;

use RuntimeException;

/** Thrown when a booking can't be made or cancelled; the refusal says why. */
class BookingRefused extends RuntimeException
{
    public function __construct(public readonly ?BookingRefusal $refusal, string $message = '')
    {
        parent::__construct($message ?: ($refusal?->message() ?? 'This booking cannot be changed.'));
    }
}
