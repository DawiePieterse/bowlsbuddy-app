<?php

namespace App\Services;

use RuntimeException;

/**
 * A booking the rules refused. The message is the reason, safe to show to the member.
 */
class BookingRefused extends RuntimeException {}
