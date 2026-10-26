<?php

namespace App\Social;

/** Meta no longer accepts the token (expired, password changed, app removed): connect again. */
class MetaAuthException extends MetaException {}
