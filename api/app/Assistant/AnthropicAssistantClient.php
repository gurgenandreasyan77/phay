<?php

namespace App\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\BadRequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * The assistant on Claude (Anthropic's Messages API). The business's
 * figures are in the system prompt; with tools the model can also look up
 * rows, sums and reports on its own, a few rounds at most, and then answers.
 */
class AnthropicAssistantClient implements AssistantClient
{
    /** Look-ups the model may do for one question before it must answer. */
    private const MAX_TOOL_ROUNDS = 5;

    /** Past this many seconds no more look-ups: the web server waits 120 s. */
    private const TIME_BUDGET = 70;

    /** Off once the API says the account can't use the fallback beta. */
    private bool $fallbacks = true;

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

    public function reply(string $system, array $messages, ?ToolSet $tools = null, ?int $timeBudget = null): AssistantReply
    {
        $budget = min(self::TIME_BUDGET, $timeBudget ?? self::TIME_BUDGET);
        $client = new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => 60, 'maxRetries' => 1]);
        $started = microtime(true);
        $input = 0;
        $output = 0;
        $used = [];

        for ($round = 0; ; $round++) {
            // The last round (or out of time) answers with what it has.
            $mayUseTools = $tools !== null && $round < self::MAX_TOOL_ROUNDS && microtime(true) - $started < $budget;

            $message = $this->create($client, $system, $messages, $tools, $mayUseTools);

            $usage = $message->usage;
            $input += $usage->inputTokens + ($usage->cacheCreationInputTokens ?? 0) + ($usage->cacheReadInputTokens ?? 0);
            $output += $usage->outputTokens;

            if ($message->stopReason === 'refusal') {
                return new AssistantReply('', $input, $output, refused: true, toolsUsed: $used);
            }

            if ($message->stopReason !== 'tool_use' || $tools === null) {
                break;
            }

            // Every look-up of this round goes back in one message.
            $results = [];
            foreach ($message->content as $block) {
                if ($block->type !== 'tool_use') {
                    continue;
                }
                $used[] = $block->name;
                $results[] = $this->runTool($tools, $block->id, $block->name, (array) $block->input);
            }

            $messages[] = ['role' => 'assistant', 'content' => $message->content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new AssistantReply(trim($text), $input, $output, toolsUsed: $used);
    }

    /**
     * One call to the Messages API. Refused requests are retried on the
     * model Anthropic recommends (server-side fallback); an account that
     * can't use that beta gets the plain request instead.
     */
    private function create(Client $client, string $system, array $messages, ?ToolSet $tools, bool $mayUseTools): mixed
    {
        $params = [
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            // The prompt (with the business's figures) is the same on every
            // round of a question: cached, the rounds after the first cost
            // a tenth for it.
            'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            'messages' => $messages,
            'tools' => $tools?->definitions(),
            'toolChoice' => $tools === null ? null : ['type' => $mayUseTools ? 'auto' : 'none'],
            // Thinking is always on for this model; effort sets how deep it goes.
            'outputConfig' => ['effort' => $this->effort],
        ];

        if ($this->fallbacks) {
            try {
                return $client->beta->messages->create(...$params, fallbacks: 'default', betas: ['server-side-fallback-2026-07-01']);
            } catch (BadRequestException $e) {
                if (! preg_match('/fallback|beta/i', $e->getMessage())) {
                    throw $e;
                }
                Log::info('AI assistant: refusal fallback not available, asking without it', ['error' => $e->getMessage()]);
                $this->fallbacks = false;
            }
        }

        return $client->beta->messages->create(...$params);
    }

    /** @return array<string, mixed> a tool_result block */
    private function runTool(ToolSet $tools, string $id, string $name, array $input): array
    {
        try {
            $result = $tools->run($name, $input);
            if ($result instanceof ToolOutput) {
                return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => $result->blocks];
            }
            $content = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

            return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => $content ?: '{}'];
        } catch (InvalidArgumentException $e) {
            return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => 'Error: '.$e->getMessage(), 'is_error' => true];
        } catch (Throwable $e) {
            Log::warning('AI assistant tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return ['type' => 'tool_result', 'tool_use_id' => $id, 'content' => 'Error: this look-up failed, answer from what you have.', 'is_error' => true];
        }
    }
}
