<?php

namespace App\Agents;

use App\Assistant\AssistantClient;
use App\Assistant\BusinessAssistant;
use App\Assistant\ReplyActions;
use App\Assistant\ToolOutput;
use App\Assistant\ToolSet;
use App\Models\User;
use App\Tenancy\TenantContext;
use InvalidArgumentException;

/**
 * The AI team talking among itself: while answering the owner, an agent
 * (or the business assistant) may ask a teammate one question with
 * ask_colleague, e.g. the table agent asks the HR agent whether the
 * performer of a new order is away that day. The teammate answers from its
 * own look-ups, as itself (its role, the brand profile, its notes), but
 * read-only: it prepares no cards and saves nothing, and it can't ask
 * anyone in turn. Changes stay with the agent the owner talks to, as cards
 * the owner applies.
 *
 * Only the business's own agents, in the same business, for the same
 * person. A consultation is part of the owner's question: no request of
 * its own, its tokens are added to the question's. The chat shows who was
 * consulted, with the question and the answer ({type: consult}).
 */
class Colleagues
{
    public const TOOL = 'ask_colleague';

    /** Teammates one answer may consult. */
    public const MAX_PER_QUESTION = 2;

    /** Seconds a consultation may take at most, and the least worth starting. */
    private const TIME_BUDGET = 30;

    private const MIN_TIME = 10;

    /** Past this many seconds of the question no one is asked: the owner waits. */
    private const QUESTION_BUDGET = 55;

    /** Characters of a teammate's answer kept for the chat. */
    private const MAX_ANSWER = 2000;

    private int $asked = 0;

    private float $started = 0;

    private int $inputTokens = 0;

    private int $outputTokens = 0;

    public function __construct(
        private readonly Agents $agents,
        private readonly TenantContext $tenants,
        private readonly AssistantClient $client,
        private readonly AgentPrompt $prompt,
        private readonly AgentTools $tools,
        private readonly ReplyActions $actions,
    ) {}

    /** «Անյա · HR և հավաքագրում (hr): <tagline>», for prompts and the tool. */
    public static function introduce(Agent $agent): string
    {
        return "{$agent->label('en')} ({$agent->label('hy')}), key \"{$agent->key()}\": {$agent->tagline()}";
    }

    /** A new question of the owner: nobody asked yet. */
    public function newQuestion(): void
    {
        $this->asked = 0;
        $this->started = microtime(true);
        $this->inputTokens = 0;
        $this->outputTokens = 0;
    }

    /** @return array{0: int, 1: int} input and output tokens of this question's consultations */
    public function tokens(): array
    {
        return [$this->inputTokens, $this->outputTokens];
    }

    /**
     * The tools of whoever answers the owner, with ask_colleague added when
     * the business has teammates for it.
     */
    public function around(ToolSet $tools, ?Agent $asker, BusinessAssistant $assistant, ?User $user): ToolSet
    {
        $team = $this->agents->colleagues($this->tenants->current(), $asker);

        if ($team === []) {
            return $tools;
        }

        return new class($tools, $this->definition($team), fn (array $input) => $this->ask($input, $team, $asker, $assistant, $user)) implements ToolSet
        {
            public function __construct(
                private readonly ToolSet $tools,
                private readonly array $definition,
                private readonly \Closure $ask,
            ) {}

            public function definitions(): array
            {
                return [...$this->tools->definitions(), $this->definition];
            }

            public function run(string $name, array $input): array|ToolOutput
            {
                return $name === Colleagues::TOOL ? ($this->ask)($input) : $this->tools->run($name, $input);
            }
        };
    }

    /**
     * One line for the business assistant's prompt: the team it may ask.
     */
    public function guide(): string
    {
        $team = $this->agents->colleagues($this->tenants->current());

        return $team === []
            ? ''
            : 'The business also has an AI team of agents. When a question needs what one of them keeps or knows best (e.g. who of the staff is away: the HR person), ask them with ask_colleague, then answer with what they said.';
    }

    /**
     * @param  list<Agent>  $team
     * @return array<string, mixed>
     */
    private function definition(array $team): array
    {
        return [
            'name' => self::TOOL,
            'description' => 'Asks a teammate of the AI team one question and gives back their answer, e.g. the HR person whether an employee is away on some days, the accountant a figure, the content creator the brand\'s tone. They look it up in their own records and change nothing. Ask when your work depends on it, at most '.self::MAX_PER_QUESTION.' times per answer. Teammates: '.implode('; ', array_map(fn (Agent $a) => self::introduce($a), $team)).'.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'colleague' => ['type' => 'string', 'enum' => array_map(fn (Agent $a) => $a->key(), $team), 'description' => 'The teammate\'s key.'],
                    'question' => ['type' => 'string', 'description' => 'One clear question with the details they need: names, dates (YYYY-MM-DD), ids, what you are about to do. In the owner\'s language: they see it in the chat.'],
                ],
                'required' => ['colleague', 'question'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<Agent>  $team
     * @return array<string, mixed>
     */
    private function ask(array $input, array $team, ?Agent $asker, BusinessAssistant $assistant, ?User $user): array
    {
        $colleague = collect($team)->first(fn (Agent $a) => $a->key() === ($input['colleague'] ?? null));
        if (! $colleague) {
            throw new InvalidArgumentException('No such teammate in this business.');
        }
        $question = trim((string) ($input['question'] ?? ''));
        if ($question === '') {
            throw new InvalidArgumentException('question is required.');
        }
        $question = mb_substr($question, 0, 1000);

        if ($this->asked >= self::MAX_PER_QUESTION) {
            throw new InvalidArgumentException('You asked your teammates enough for this answer: answer with what you have.');
        }
        $left = min(self::TIME_BUDGET, self::QUESTION_BUDGET - (microtime(true) - $this->started));
        if ($left < self::MIN_TIME) {
            throw new InvalidArgumentException('No time to ask a teammate now: answer with what you have, and say what to check.');
        }
        $this->asked++;

        // The teammate as itself, but only looking things up, and asking no one.
        $reply = $this->client->reply(
            $this->prompt->build($colleague, $assistant, consulted: true, askedBy: $asker),
            [['role' => 'user', 'content' => $question]],
            $this->tools->for($colleague, $user)->readOnly(),
            (int) $left,
        );

        $this->inputTokens += $reply->inputTokens;
        $this->outputTokens += $reply->outputTokens;

        $answer = $reply->refused || trim($reply->text) === ''
            ? null
            : mb_substr(trim($reply->text), 0, self::MAX_ANSWER);

        $this->actions->add(array_filter([
            'type' => 'consult',
            'agent' => $colleague->key(),
            'name' => $colleague->persona()['hy'],
            'role' => $colleague->role()['hy'],
            'question' => $question,
            'answer' => $answer,
        ], fn ($value) => $value !== null));

        return $answer === null
            ? ['colleague' => $colleague->label('en'), 'answer' => null, 'note' => 'They could not answer: go on without it and tell the owner what to check.']
            : ['colleague' => $colleague->label('en'), 'answer' => $answer];
    }
}
