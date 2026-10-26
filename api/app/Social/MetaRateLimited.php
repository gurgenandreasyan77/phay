<?php

namespace App\Social;

/** Meta's rate limit for the app or the account is reached: the next scheduled sync tries again. */
class MetaRateLimited extends MetaException {}
