<?php

namespace App\Google;

use RuntimeException;

/** Google answered with an error, or couldn't be reached. */
class GoogleException extends RuntimeException {}
