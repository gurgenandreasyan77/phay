<?php

namespace Tests\Fakes;

use App\Assistant\AssistantClient;
use App\Assistant\AssistantReply;
use App\Assistant\BusinessTools;

/**
 * The model in tests: answers "Fake answer", and first runs the look-ups
 * set in `toolCalls` the way the model would, keeping what they returned.
 */
class FakeAssistantClient implements AssistantClient
{
    public bool $configured = true;

    public ?string $system = null;

    public array $messages = [];

    /** @var list<array{0: string, 1: array<string, mixed>}> tool name and input */
    public array $toolCalls = [];

    /** @var list<array<string, mixed>> what each look-up returned */
    public array $toolResults = [];

    public ?array $toolDefinitions = null;

    /** Thrown instead of answering, like a failed API call. */
    public ?\Throwable $throw = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function reply(string $system, array $messages, ?BusinessTools $tools = null): AssistantReply
    {
        $this->system = $system;
        $this->messages = $messages;
        $this->toolDefinitions = $tools?->definitions();

        if ($this->throw) {
            throw $this->throw;
        }

        foreach ($this->toolCalls as [$name, $input]) {
            $this->toolResults[] = $tools->run($name, $input);
        }

        return new AssistantReply('Fake answer', 120, 30, toolsUsed: array_column($this->toolCalls, 0));
    }
}
