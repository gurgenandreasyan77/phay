<?php

namespace App\Assistant;

/**
 * The language model behind the business assistant; tests swap in a fake.
 */
interface AssistantClient
{
    public function isConfigured(): bool;

    /**
     * Answers the conversation. With tools, the model may look things up
     * in the business's data (BusinessTools) before it answers.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function reply(string $system, array $messages, ?BusinessTools $tools = null): AssistantReply;
}
