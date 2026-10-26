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
     * in the business's data (a ToolSet) before it answers.
     *
     * @param  list<array{role: 'user'|'assistant', content: string|list<array<string, mixed>>}>  $messages  content blocks for a question with files (Attachments::forModel)
     */
    public function reply(string $system, array $messages, ?ToolSet $tools = null): AssistantReply;
}
