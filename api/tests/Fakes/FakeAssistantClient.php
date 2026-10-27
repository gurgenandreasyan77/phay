<?php

namespace Tests\Fakes;

use App\Assistant\AssistantClient;
use App\Assistant\AssistantReply;
use App\Assistant\ToolSet;

/**
 * The model in tests: answers "Fake answer", and first runs the look-ups
 * set in `toolCalls` the way the model would, keeping what they returned.
 *
 * A teammate consulted while answering (ask_colleague) is a call inside
 * the call: it runs `colleagueToolCalls` and answers the next of
 * `colleagueAnswers`. The fields above stay the owner's call; `calls`
 * keeps every call, the teammates' too.
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

    /** @var list<array{0: string, 1: array<string, mixed>}> look-ups of a consulted teammate */
    public array $colleagueToolCalls = [];

    /** @var list<string> what consulted teammates answer, in turn */
    public array $colleagueAnswers = [];

    /** @var list<array{system: string, messages: array, tools: list<string>, results: array, timeBudget: int|null}> */
    public array $calls = [];

    private int $depth = 0;

    /** Thrown instead of answering, like a failed API call. */
    public ?\Throwable $throw = null;

    /** Runs while the owner's question is being answered (what others see meanwhile). */
    public ?\Closure $whileAnswering = null;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function reply(string $system, array $messages, ?ToolSet $tools = null, ?int $timeBudget = null): AssistantReply
    {
        $nested = $this->depth > 0;
        $definitions = $tools?->definitions();
        if (! $nested) {
            $this->system = $system;
            $this->messages = $messages;
            $this->toolDefinitions = $definitions;
            if ($this->whileAnswering) {
                ($this->whileAnswering)();
            }
        }

        if ($this->throw) {
            throw $this->throw;
        }

        $calls = $nested ? $this->colleagueToolCalls : $this->toolCalls;
        $results = [];
        $this->depth++;
        try {
            foreach ($calls as [$name, $input]) {
                $results[] = $result = $tools->run($name, $input);
                if (! $nested) {
                    $this->toolResults[] = $result;
                }
            }
        } finally {
            $this->depth--;
        }

        $this->calls[] = [
            'system' => $system,
            'messages' => $messages,
            'tools' => array_column($definitions ?? [], 'name'),
            'results' => $results,
            'timeBudget' => $timeBudget,
        ];

        $text = $nested ? (array_shift($this->colleagueAnswers) ?? 'Fake colleague answer') : 'Fake answer';

        return new AssistantReply($text, 120, 30, toolsUsed: array_column($calls, 0));
    }
}
