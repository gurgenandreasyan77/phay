<?php

namespace App\Assistant;

final readonly class AssistantReply
{
    /** @param  list<string>  $toolsUsed  tools the model looked things up with, in order */
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public bool $refused = false,
        public array $toolsUsed = [],
    ) {}
}
