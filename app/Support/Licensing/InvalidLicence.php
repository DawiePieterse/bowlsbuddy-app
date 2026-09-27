<?php

namespace App\Support\Licensing;

use RuntimeException;

/** A licence key that can't be used; the message is shown to the Secretary as it is. */
class InvalidLicence extends RuntimeException {}
