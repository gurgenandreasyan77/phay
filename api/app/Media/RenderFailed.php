<?php

namespace App\Media;

use RuntimeException;

/** The media service could not do it (down, too slow, refused it with a reason). */
class RenderFailed extends RuntimeException {}
