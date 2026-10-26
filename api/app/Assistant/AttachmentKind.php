<?php

namespace App\Assistant;

/** What a file in the AI chat is, which decides how the model receives it. */
enum AttachmentKind: string
{
    /** JPG, PNG, WEBP or GIF: the model sees it (vision). */
    case Image = 'image';

    /** The model reads its text and sees its pages. */
    case Pdf = 'pdf';

    /** TXT, CSV or Markdown in UTF-8: the model reads it as text. */
    case Text = 'text';
}
