<?php

namespace App\Console\Commands;

use App\Assistant\Attachments;
use Illuminate\Console\Command;

/** Files uploaded to the AI chat and never sent: removed after a day. */
class PruneAssistantAttachments extends Command
{
    protected $signature = 'platform:assistant:prune';

    protected $description = 'Remove AI chat files that were uploaded but never sent';

    public function handle(Attachments $attachments): int
    {
        $this->info('Removed '.$attachments->prune().' unsent file(s).');

        return self::SUCCESS;
    }
}
