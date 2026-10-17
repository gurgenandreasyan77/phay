<?php

namespace App\Assistant;

final readonly class AssistantReply
{
    public function __construct(
        public string $text,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public bool $refused = false,
    ) {}
}
