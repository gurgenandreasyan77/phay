<?php

namespace App\Agents;

use App\Assistant\Attachments;
use App\Assistant\BusinessTools;
use App\Assistant\TableEditTools;
use App\Assistant\ToolOutput;
use App\Assistant\ToolSet;
use App\Media\DesignStudio;
use App\Media\LinkReader;
use App\Media\VideoStudio;
use App\Models\AssistantAttachment;
use App\Models\BrandAsset;
use App\Models\BrandProfile;
use App\Models\User;
use App\Social\SocialTools;
use App\Team\HrTools;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * An agent's look-ups: its share of the assistant's (only those its role
 * allows, and of those only the ones the business has: social look-ups need
 * the social module), plus learning the business: saving what the owner
 * tells about the brand into the profile every agent reads, and keeping
 * its own notes. Pictures the owner attaches (a logo, examples of posts)
 * can be kept for the whole team (save_brand_asset) and looked at again in
 * later conversations (view_brand_assets). Every agent can open a link
 * the owner sends (read_link); the designers also make pictures
 * (render_designs, get_design), the video editors cut the owner's clips
 * (edit_video), the table agent prepares changes of the tables
 * (TableEditTools), the HR agent the team's records (HrTools).
 * Everything stays inside the current business.
 *
 * When a teammate consults an agent (Colleagues) it only looks things up
 * (readOnly()): no cards, no saving, no pictures or videos.
 */
class AgentTools implements ToolSet
{
    public const SAVE_PROFILE = 'save_brand_profile';

    public const REMEMBER = 'remember';

    public const SAVE_ASSET = 'save_brand_asset';

    public const VIEW_ASSETS = 'view_brand_assets';

    /** What an agent may do while a teammate consults it: look-ups only. */
    public const READS = [
        'list_tables', 'find_rows', 'group_rows', 'finance_report', 'expenses_report', 'site_orders',
        ...SocialTools::NAMES,
        TableEditTools::ROWS,
        HrTools::TEAM, HrTools::ABSENCES, HrTools::HIRING,
        self::VIEW_ASSETS,
        LinkReader::TOOL,
    ];

    private ?Agent $agent = null;

    private bool $readOnly = false;

    private ?User $user = null;

    public function __construct(
        private readonly BusinessTools $tools,
        private readonly Agents $agents,
        private readonly TenantContext $tenants,
        private readonly Attachments $attachments,
        private readonly DesignStudio $studio,
        private readonly LinkReader $links,
        private readonly VideoStudio $videos,
        private readonly TableEditTools $tableEdits,
        private readonly HrTools $hr,
    ) {}

    /** Counts renders, links and videos afresh, and forgets the pictures of an earlier question. */
    public function newQuestion(): void
    {
        $this->studio->newQuestion();
        $this->links->newQuestion();
        $this->videos->newQuestion();
    }

    /** Whether this agent makes pictures here (its role, and the media service is connected). */
    public function designs(): bool
    {
        return ! $this->readOnly && $this->agent()->designs() && $this->studio->available();
    }

    /** Whether this agent cuts videos here (its role, and the media service is connected). */
    public function edits(): bool
    {
        return ! $this->readOnly && $this->agent()->edits() && $this->videos->available();
    }

    /** The same tools for another agent, saving as the given person. */
    public function for(Agent $agent, ?User $user): self
    {
        $tools = clone $this;
        $tools->agent = $agent;
        $tools->user = $user;
        $tools->readOnly = false;

        return $tools;
    }

    /** The same agent's look-ups only, for answering a teammate. */
    public function readOnly(): self
    {
        $tools = clone $this;
        $tools->readOnly = true;

        return $tools;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function definitions(): array
    {
        if ($this->readOnly) {
            return array_values(array_filter($this->all(), fn (array $definition) => in_array($definition['name'], self::READS, true)));
        }

        return $this->all();
    }

    /** @return list<array<string, mixed>> */
    private function all(): array
    {
        $allowed = $this->agent()->tools();
        $shared = array_values(array_filter(
            [
                ...$this->tools->definitions(),
                ...($this->tableEdits->available() ? $this->tableEdits->definitions() : []),
                ...($this->hr->available() ? $this->hr->definitions() : []),
            ],
            fn (array $definition) => in_array($definition['name'], $allowed, true),
        ));

        $text = fn (string $what) => ['type' => 'string', 'description' => $what];

        return [
            ...$shared,
            [
                'name' => self::SAVE_PROFILE,
                'description' => 'Saves what the owner told you about the business into its brand profile, which every agent of the team reads. Pass only the fields you learned or the owner corrected, in the owner\'s words and language, complete (a field you pass replaces what was there, so merge with the profile you see). Save as soon as the owner answered, before you go on.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'about' => $text('What the business is and does, where, since when, what makes it different.'),
                        'offer' => $text('Its main services or products, prices and offers.'),
                        'audience' => $text('Who its customers are: age, place, needs, why they buy.'),
                        'tone' => $text('How it speaks: formal or friendly, emoji or not, languages, words it uses.'),
                        'visual_style' => $text('How its pictures and videos look: light, backgrounds, mood, fonts, what is in the frame.'),
                        'colors' => [
                            'type' => 'array',
                            'description' => 'Brand colours (the whole list): hex codes with a short name.',
                            'items' => [
                                'type' => 'object',
                                'properties' => ['hex' => $text('#RRGGBB'), 'name' => $text('e.g. navy, deep green')],
                                'required' => ['hex'],
                            ],
                        ],
                        'examples' => $text('Accounts, posts, ads or pictures it likes, and links.'),
                        'avoid' => $text('What must never appear or be said.'),
                    ],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::REMEMBER,
                'description' => 'Keeps one short fact or preference about this business for your next conversations (e.g. "posts go out at 19:00", "prefers Russian captions", "no discounts below 10%"). Only things that will matter again; brand facts go to save_brand_profile.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['note' => $text('One line.')],
                    'required' => ['note'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => self::SAVE_ASSET,
                'description' => 'Keeps a picture the owner attached in this chat (named "[file #N: …, image]") for the whole team: as the business\'s logo (replaces the old one) or as an example they like (a post, an ad, a photo; up to '.BrandAsset::MAX_EXAMPLES.'). Save when the owner says it is their logo or an example to follow, or agrees when you offer. Also write what you see (colours, style) into the brand profile with save_brand_profile.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'file' => ['type' => 'integer', 'description' => 'The N of [file #N].'],
                        'kind' => ['type' => 'string', 'enum' => BrandAsset::KINDS],
                        'note' => $text('A short line: what it shows, what to take from it.'),
                    ],
                    'required' => ['file', 'kind'],
                    'additionalProperties' => false,
                ],
            ],
            // Only once there is something to look at.
            ...(BrandAsset::query()->exists() ? [[
                'name' => self::VIEW_ASSETS,
                'description' => 'Shows you the business\'s saved logo and example pictures (with their ids for {{asset:ID}}). Look before you describe, design or write anything about how the brand looks.',
                'inputSchema' => ['type' => 'object'],
            ]] : []),
            $this->links->definition(),
            ...($this->designs() ? $this->studio->definitions() : []),
            ...($this->edits() ? [$this->videos->definition()] : []),
        ];
    }

    public function run(string $name, array $input): array|ToolOutput
    {
        $tenant = $this->tenants->current();

        if ($this->readOnly && ! in_array($name, self::READS, true)) {
            throw new InvalidArgumentException('A teammate asked you: you can only look things up now. Say what should change instead.');
        }

        if ($name === self::SAVE_ASSET) {
            return $this->saveAsset($input);
        }

        if ($name === self::VIEW_ASSETS) {
            return $this->viewAssets();
        }

        if ($name === LinkReader::TOOL) {
            return $this->links->read((string) ($input['url'] ?? ''));
        }

        if (in_array($name, [DesignStudio::RENDER, DesignStudio::GET], true)) {
            if (! $this->designs()) {
                throw new InvalidArgumentException('You can\'t make pictures here: give a brief instead.');
            }

            return $name === DesignStudio::RENDER ? $this->studio->render($input, $this->user) : $this->studio->get($input, $this->user);
        }

        if ($name === VideoStudio::EDIT) {
            if (! $this->edits()) {
                throw new InvalidArgumentException('You can\'t cut videos here: give the editing plan instead.');
            }

            return $this->videos->edit($input, $this->user);
        }

        if ($name === self::SAVE_PROFILE) {
            $data = array_intersect_key($input, array_flip([...BrandProfile::FIELDS, 'colors']));
            if ($data === []) {
                throw new InvalidArgumentException('Pass at least one field of the profile.');
            }
            $profile = $this->agents->saveProfile($tenant, $data, $this->user);

            return ['saved' => array_keys($data), 'profile' => $profile->present()];
        }

        if ($name === self::REMEMBER) {
            $note = trim((string) ($input['note'] ?? ''));
            if ($note === '') {
                throw new InvalidArgumentException('note is required.');
            }
            $this->agents->remember($tenant, $this->agent(), $note);

            return ['remembered' => true];
        }

        if (! in_array($name, $this->agent()->tools(), true)) {
            throw new InvalidArgumentException("Unknown tool [{$name}].");
        }

        if (in_array($name, TableEditTools::NAMES, true)) {
            return $this->tableEdits->run($name, $input);
        }

        if (in_array($name, HrTools::NAMES, true)) {
            return $this->hr->run($name, $input);
        }

        return $this->tools->run($name, $input);
    }

    /** @param  array<string, mixed>  $input */
    private function saveAsset(array $input): array
    {
        $kind = (string) ($input['kind'] ?? '');
        if (! in_array($kind, BrandAsset::KINDS, true)) {
            throw new InvalidArgumentException('kind must be logo or example.');
        }
        // Only a file the person in this chat attached, in this business.
        $attachment = $this->user
            ? AssistantAttachment::query()->of($this->user)->find((int) ($input['file'] ?? 0))
            : null;
        if (! $attachment) {
            throw new InvalidArgumentException('No such file in this chat.');
        }

        try {
            $asset = $this->attachments->toBrandAsset($attachment, $kind, isset($input['note']) ? (string) $input['note'] : null, $this->user);
        } catch (ValidationException $e) {
            throw new InvalidArgumentException(collect($e->errors())->flatten()->first() ?? 'Could not save it.');
        }

        return ['saved' => $asset->present(), 'examples' => BrandAsset::query()->where('kind', BrandAsset::EXAMPLE)->count()];
    }

    private function viewAssets(): ToolOutput
    {
        $assets = BrandAsset::query()->orderByRaw("case when kind = 'logo' then 0 else 1 end")->orderBy('id')->get();
        if ($assets->isEmpty()) {
            return new ToolOutput([['type' => 'text', 'text' => 'No pictures are saved yet.']]);
        }

        $blocks = [];
        foreach ($assets as $asset) {
            $label = $asset->kind === BrandAsset::LOGO ? 'The logo ({{logo}})' : "Example #{$asset->id} ({{asset:{$asset->id}}})";
            $blocks[] = ['type' => 'text', 'text' => $label.($asset->note ? ": {$asset->note}" : '')];
            $blocks[] = $this->attachments->block($asset);
        }

        return new ToolOutput($blocks);
    }

    private function agent(): Agent
    {
        return $this->agent ?? throw new InvalidArgumentException('No agent: call for() first.');
    }
}
