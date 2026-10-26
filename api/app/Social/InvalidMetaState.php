<?php

namespace App\Social;

use RuntimeException;

/** The answer from Meta's login page isn't this person's, for this business, or came too late. */
class InvalidMetaState extends RuntimeException {}
