<?php

namespace App\Google;

/** Google refused the tokens: the access was revoked or expired for good. */
class GoogleAuthException extends GoogleException {}
