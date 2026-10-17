<?php

namespace App\Assistant;

/**
 * The language model behind the business assistant; tests swap in a fake.
 */
interface AssistantClient
{
    public function isConfigured(): bool;

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function reply(string $system, array $messages): AssistantReply;
}
