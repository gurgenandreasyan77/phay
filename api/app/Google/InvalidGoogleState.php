<?php

namespace App\Google;

use RuntimeException;

/** The answer from Google's consent page isn't this person's, or came too late. */
class InvalidGoogleState extends RuntimeException {}
