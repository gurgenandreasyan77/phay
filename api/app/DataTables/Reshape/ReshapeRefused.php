<?php

namespace App\DataTables\Reshape;

use InvalidArgumentException;

/**
 * Why tables can't be split or put together as asked, in Armenian for the
 * admin (the AI assistant gets it too, as a tool error).
 */
final class ReshapeRefused extends InvalidArgumentException {}
