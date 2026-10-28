<?php

namespace App\Documents;

use RuntimeException;

/** A document that can't be read or filled; the message is for the owner (Armenian). */
class UnreadableDocument extends RuntimeException {}
