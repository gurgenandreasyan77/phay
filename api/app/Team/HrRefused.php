<?php

namespace App\Team;

use InvalidArgumentException;

/** Why a change of the team's records can't be prepared, applied or undone (Armenian; the AI gets it as a tool error). */
final class HrRefused extends InvalidArgumentException {}
