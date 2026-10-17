<?php

namespace App\Assistant;

use Anthropic\Client;

class AnthropicAssistantClient implements AssistantClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $effort,
        private readonly int $maxTokens,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function reply(string $system, array $messages): AssistantReply
    {
        $client = new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => 110, 'maxRetries' => 1]);

        $message = $client->beta->messages->create(
            model: $this->model,
            maxTokens: $this->maxTokens,
            system: $system,
            messages: $messages,
            // Thinking is always on for this model; effort sets how deep it goes.
            outputConfig: ['effort' => $this->effort],
            // A declined request is retried on the model Anthropic recommends.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            return new AssistantReply('', $message->usage->inputTokens, $message->usage->outputTokens, refused: true);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AssistantReply(trim($text), $message->usage->inputTokens, $message->usage->outputTokens);
    }
}
