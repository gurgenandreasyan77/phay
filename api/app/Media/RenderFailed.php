<?php

namespace App\Media;

use RuntimeException;

/** The media service could not render a design (down, too slow, refused it). */
class RenderFailed extends RuntimeException {}
