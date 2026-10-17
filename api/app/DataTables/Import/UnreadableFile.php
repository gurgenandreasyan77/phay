<?php

namespace App\DataTables\Import;

use RuntimeException;

/** The file can't be read as a table; the message is shown in the admin (Armenian). */
class UnreadableFile extends RuntimeException {}
