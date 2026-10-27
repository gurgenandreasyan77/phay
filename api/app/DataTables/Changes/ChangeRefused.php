<?php

namespace App\DataTables\Changes;

use InvalidArgumentException;

/**
 * Why a change of a table can't be prepared, applied or undone, in Armenian
 * for the admin (the AI agent gets it too, as a tool error).
 */
final class ChangeRefused extends InvalidArgumentException {}
