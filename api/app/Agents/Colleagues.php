<?php

namespace App\Agents;

use App\Assistant\AssistantClient;
use App\Assistant\BusinessAssistant;
use App\Assistant\ReplyActions;
use App\Assistant\ToolOutput;
use App\Assistant\ToolSet;
use App\Documents\DocumentStudio;
use App\Media\DesignStudio;
use App\Media\VideoStudio;
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
 *
 * Every agent keeps to its specialty. Work of another one (a picture asked
 * of the table agent) goes to that agent: when the business has it, the
 * asker hands it over in the background with delegate_to_colleague and
 * reports the result in the same answer. The teammate then works as
 * itself with all its tools (pictures, cards), for the same owner; what
 * it makes comes under the asker's answer, and the chat shows the
 * handover with its report ({type: delegate}). It can't ask or hand on in
 * turn (depth 1). When the business doesn't have that agent, the asker
 * calls refer_to_colleague, a card under the answer ({type: refer}) to
 * request it; once it is on the team the owner comes back to the chat
 * and the asker goes on. The business assistant works the same way
 * (Agents::directory()).
 */
class Colleagues
{
    public const TOOL = 'ask_colleague';

    public const REFER = 'refer_to_colleague';

    public const DELEGATE = 'delegate_to_colleague';

    /** Teammates one answer may consult. */
    public const MAX_PER_QUESTION = 2;

    /** Pieces of work one answer may hand over. */
    public const MAX_DELEGATIONS = 2;

    /** Seconds a handed-over piece of work may take at most (pictures take a while). */
    private const DELEGATE_BUDGET = 45;

    private const MIN_DELEGATE_TIME = 15;

    /** Seconds a consultation may take at most, and the least worth starting. */
    private const TIME_BUDGET = 30;

    private const MIN_TIME = 10;

    /** Past this many seconds of the question no one is asked: the owner waits. */
    private const QUESTION_BUDGET = 55;

    /** Characters of a teammate's answer kept for the chat. */
    private const MAX_ANSWER = 2000;

    private int $asked = 0;

    private int $delegated = 0;

    /** @var string|list<array<string, mixed>>|null the owner's latest message, for a teammate given work */
    private string|array|null $ownerMessage = null;

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
        private readonly DesignStudio $studio,
        private readonly VideoStudio $videos,
        private readonly DocumentStudio $documents,
    ) {}

    /** «Անյա · HR և հավաքագրում (hr): <tagline>», for prompts and the tool. */
    public static function introduce(Agent $agent): string
    {
        return "{$agent->label('en')} ({$agent->label('hy')}), key \"{$agent->key()}\": {$agent->tagline()}";
    }

    /**
     * A new question of the owner: nobody asked yet.
     *
     * @param  string|list<array<string, mixed>>|null  $ownerMessage  what the owner wrote (with files), passed on with work handed over
     */
    public function newQuestion(string|array|null $ownerMessage = null): void
    {
        $this->asked = 0;
        $this->delegated = 0;
        $this->ownerMessage = $ownerMessage;
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
     * The tools of whoever answers the owner: with teammates in the
     * business, ask_colleague and delegate_to_colleague; with agents of the
     * platform the business doesn't have, refer_to_colleague.
     */
    public function around(ToolSet $tools, ?Agent $asker, BusinessAssistant $assistant, ?User $user): ToolSet
    {
        $team = $this->agents->colleagues($this->tenants->current(), $asker);
        $keys = array_map(fn (Agent $a) => $a->key(), $team);
        $missing = array_values(array_filter(self::others($asker), fn (Agent $a) => ! in_array($a->key(), $keys, true)));

        $added = array_filter([
            self::REFER => $missing === [] ? null : $this->referDefinition($missing),
            self::TOOL => $team === [] ? null : $this->definition($team),
            self::DELEGATE => $team === [] ? null : $this->delegateDefinition($team),
        ]);
        $run = fn (string $name, array $input) => match ($name) {
            self::REFER => $this->refer($input, $asker),
            self::DELEGATE => $this->delegate($input, $team, $asker, $assistant, $user),
            default => $this->ask($input, $team, $asker, $assistant, $user),
        };

        return new class($tools, $added, $run) implements ToolSet
        {
            /** @param  array<string, array<string, mixed>>  $added */
            public function __construct(
                private readonly ToolSet $tools,
                private readonly array $added,
                private readonly \Closure $run,
            ) {}

            public function definitions(): array
            {
                return [...$this->tools->definitions(), ...array_values($this->added)];
            }

            public function run(string $name, array $input): array|ToolOutput
            {
                return isset($this->added[$name]) ? ($this->run)($name, $input) : $this->tools->run($name, $input);
            }
        };
    }

    /**
     * How the business assistant shares the work with the team: it keeps
     * the figures and advice, the agents' specialist work goes to them.
     */
    public function guide(): string
    {
        $team = $this->agents->colleagues($this->tenants->current());

        return implode("\n", array_filter([
            $this->agents->directory($this->tenants->current()),
            'Your own work is the business\'s figures and advice on selling more. The specialist work of the agents above (pictures, image prompts and the brand\'s look; post, caption and ad texts and content plans; reel scripts; video editing; changing the tables; hiring, job postings and staff time off; reviewing documents and writing or filling contracts, acts and forms) is not yours: don\'t do it yourself, not even as a draft or a prompt. When that agent is on this business\'s team, hand the work to them right away with '.self::DELEGATE.' (in the background, with every detail from the conversation) and report what they did in a sentence or two: their pictures and cards come under your answer. One message of the owner is enough for the whole job: use every teammate it needs, and tell in order whom you consulted and who did what (e.g. "I asked the analyst, then the designer made the picture"). Never send the owner to another chat for it. When that agent is NOT on the team, say plainly that this specialist isn\'t available for their business yet, that it can be added for a monthly fee with the button under your answer ('.self::REFER.'), and that once it is added they can come back here and you will have it done.',
            $team === [] ? null : 'When a question needs what one of the business\'s agents keeps or knows best (e.g. who of the staff is away: the HR person), ask them with ask_colleague, then answer with what they said.',
        ]));
    }

    /** @return list<Agent> the catalog without the asker */
    public static function others(?Agent $asker): array
    {
        return array_values(array_filter(AgentCatalog::all(), fn (Agent $agent) => $agent->key() !== $asker?->key()));
    }

    /**
     * @param  list<Agent>  $missing  agents of the platform the business doesn't have
     * @return array<string, mixed>
     */
    private function referDefinition(array $missing): array
    {
        return [
            'name' => self::REFER,
            'description' => 'When the owner\'s request is the work of an agent this business does NOT have yet: puts a card under your answer to request (buy) that agent. Tell the owner in a sentence that this specialist is not available for them yet, that it can be added for a monthly fee with the button, and that once it is added they can come back to this chat and you will go on. The card does the rest, never write a link. For agents on the team use '.self::DELEGATE.' or ask_colleague instead.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'colleague' => ['type' => 'string', 'enum' => array_map(fn (Agent $a) => $a->key(), $missing), 'description' => 'The key of the agent whose work it is.'],
                    'task' => ['type' => 'string', 'description' => 'What the owner wants of them, in a short line in the owner\'s language (shown on the card).'],
                ],
                'required' => ['colleague'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * The card to request an agent the business doesn't have.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function refer(array $input, ?Agent $asker): array
    {
        $agent = collect(self::others($asker))->first(fn (Agent $a) => $a->key() === ($input['colleague'] ?? null));
        if (! $agent) {
            throw new InvalidArgumentException('No such agent.');
        }
        if ($this->agents->isActive($this->tenants->current(), $agent)) {
            throw new InvalidArgumentException("{$agent->label('en')} is on this business's team: don't send the owner to them. Hand the work over with ".self::DELEGATE.' or ask them with '.self::TOOL.', then report the result.');
        }

        return $this->referTo($agent, isset($input['task']) ? (string) $input['task'] : null);
    }

    /** @return array<string, mixed> */
    private function referTo(Agent $agent, ?string $task): array
    {
        $task = $task === null ? null : (mb_substr(trim($task), 0, 200) ?: null);
        $available = $this->agents->isActive($this->tenants->current(), $agent);

        $this->actions->add(array_filter([
            'type' => 'refer',
            'agent' => $agent->key(),
            'name' => $agent->persona()['hy'],
            'role' => $agent->role()['hy'],
            'task' => $task,
        ], fn ($value) => $value !== null));

        return [
            'colleague' => $agent->label('en'),
            'available' => $available,
            'note' => $available
                ? 'The card under your answer opens their chat. Don\'t do their part yourself.'
                : 'They are not on this business\'s team. Tell the owner this specialist is not available for them yet, that it can be added for a monthly fee, that the button on the card under your answer sends the request, and that once it is added they can come back to this chat and you will go on. Don\'t do their part yourself.',
        ];
    }

    /**
     * @param  list<Agent>  $team
     * @return array<string, mixed>
     */
    private function delegateDefinition(array $team): array
    {
        return [
            'name' => self::DELEGATE,
            'description' => 'Hands a piece of the owner\'s request that is a teammate\'s work over to them, in the background, and gives back their report: e.g. a picture to the designer, a post text to the content creator, a table change to the data manager, a vacation to the HR person. They do it right away as themselves, with their own tools; their pictures, videos and cards appear under your answer for the owner (cards are applied by the owner). They see the owner\'s latest message with its files, not the earlier chat, so put every detail they need into the task. They can\'t ask the owner anything: give them what you know. Use it instead of sending the owner to them; at most '.self::MAX_DELEGATIONS.' times per answer. Teammates: '.implode('; ', array_map(fn (Agent $a) => self::introduce($a), $team)).'.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'colleague' => ['type' => 'string', 'enum' => array_map(fn (Agent $a) => $a->key(), $team), 'description' => 'The teammate\'s key.'],
                    'task' => ['type' => 'string', 'description' => 'What to do, with every detail from the conversation they need (names, dates YYYY-MM-DD, amounts, texts, formats, style, what you already found out). In the owner\'s language: the owner sees it in the chat.'],
                ],
                'required' => ['colleague', 'task'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * The teammate does the work itself, with all its tools, as part of the
     * owner's question: what it makes lands in this answer.
     *
     * @param  array<string, mixed>  $input
     * @param  list<Agent>  $team
     * @return array<string, mixed>
     */
    private function delegate(array $input, array $team, ?Agent $asker, BusinessAssistant $assistant, ?User $user): array
    {
        $colleague = collect($team)->first(fn (Agent $a) => $a->key() === ($input['colleague'] ?? null));
        if (! $colleague) {
            // Not on the team: the owner gets the card to request it, and comes back once it is.
            $missing = collect(self::others($asker))->first(fn (Agent $a) => $a->key() === ($input['colleague'] ?? null));
            if ($missing) {
                return ['done' => false, ...$this->referTo($missing, isset($input['task']) ? (string) $input['task'] : null)];
            }
            throw new InvalidArgumentException('No such teammate in this business.');
        }
        $task = trim((string) ($input['task'] ?? ''));
        if ($task === '') {
            throw new InvalidArgumentException('task is required.');
        }
        $task = mb_substr($task, 0, 2000);

        if ($this->delegated >= self::MAX_DELEGATIONS) {
            throw new InvalidArgumentException('You handed over enough work for this answer: answer with what you have.');
        }
        $left = min(self::DELEGATE_BUDGET, self::QUESTION_BUDGET - (microtime(true) - $this->started));
        if ($left < self::MIN_DELEGATE_TIME) {
            throw new InvalidArgumentException('No time to hand work over now: tell the owner you will pass it on when they write again, and answer with what you have.');
        }
        $this->delegated++;

        $cards = count($this->actions->all());
        $designs = count($this->studio->produced());
        $videos = count($this->videos->started());
        $documents = count($this->documents->produced());

        // The teammate as itself, with all its tools, but asking and handing on no one.
        $reply = $this->client->reply(
            $this->prompt->build($colleague, $assistant, askedBy: $asker, delegated: true),
            [['role' => 'user', 'content' => $this->handover($task, $asker)]],
            $this->tools->for($colleague, $user),
            (int) $left,
        );

        $this->inputTokens += $reply->inputTokens;
        $this->outputTokens += $reply->outputTokens;

        $report = $reply->refused || trim($reply->text) === ''
            ? null
            : mb_substr(trim($reply->text), 0, self::MAX_ANSWER);
        $made = array_filter([
            'pictures' => count($this->studio->produced()) - $designs,
            'videos' => count($this->videos->started()) - $videos,
            'documents' => count($this->documents->produced()) - $documents,
            'cards' => count($this->actions->all()) - $cards,
        ]);

        $this->actions->add(array_filter([
            'type' => 'delegate',
            'agent' => $colleague->key(),
            'name' => $colleague->persona()['hy'],
            'role' => $colleague->role()['hy'],
            'task' => $task,
            'answer' => $report,
        ], fn ($value) => $value !== null));

        if ($report === null && $made === []) {
            return ['colleague' => $colleague->label('en'), 'done' => false, 'note' => 'They could not do it now: tell the owner in a sentence and what to try.'];
        }

        return [
            'colleague' => $colleague->label('en'),
            'done' => true,
            'report' => $report,
            'made' => $made,
            'note' => 'Their pictures, videos, documents and cards are already under your answer. Tell the owner in a sentence or two what they did (e.g. "'.$colleague->persona()['hy'].'ն պատրաստեց 2 տարբերակ, ընտրեք ներքևում"); don\'t redo or repeat their work.',
        ];
    }

    /**
     * What a teammate given work reads: the task, and the owner's latest
     * message with its files (pictures stay pictures).
     *
     * @return string|list<array<string, mixed>>
     */
    private function handover(string $task, ?Agent $asker): string|array
    {
        $who = $asker ? $asker->label('en') : 'The business assistant';
        $text = "{$who} hands you this work of the owner's request: {$task}";

        $owner = $this->ownerMessage;
        if (is_string($owner) && trim($owner) !== '') {
            return "The owner wrote to {$who}: «".mb_substr(trim($owner), 0, 2000)."»\n\n{$text}";
        }
        if (is_array($owner) && $owner !== []) {
            return [
                ['type' => 'text', 'text' => "The owner's message to {$who}, with its files:"],
                ...array_values(array_filter($owner, 'is_array')),
                ['type' => 'text', 'text' => $text],
            ];
        }

        return $text;
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
            // An agent of the platform the business hasn't added: the owner gets the card to request it.
            $missing = collect(self::others($asker))->first(fn (Agent $a) => $a->key() === ($input['colleague'] ?? null));
            if ($missing) {
                return ['answer' => null, ...$this->referTo($missing, null)];
            }
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
