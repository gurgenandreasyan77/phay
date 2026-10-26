<?php

namespace App\Agents;

use App\Assistant\BusinessTools;
use App\Assistant\ToolSet;
use App\Models\BrandProfile;
use App\Models\User;
use App\Tenancy\TenantContext;
use InvalidArgumentException;

/**
 * An agent's look-ups: its share of the assistant's (only those its role
 * allows, and of those only the ones the business has: social look-ups need
 * the social module), plus learning the business: saving what the owner
 * tells about the brand into the profile every agent reads, and keeping
 * its own notes. Everything stays inside the current business.
 */
class AgentTools implements ToolSet
{
    public const SAVE_PROFILE = 'save_brand_profile';

    public const REMEMBER = 'remember';

    private ?Agent $agent = null;

    private ?User $user = null;

    public function __construct(
        private readonly BusinessTools $tools,
        private readonly Agents $agents,
        private readonly TenantContext $tenants,
    ) {}

    /** The same tools for another agent, saving as the given person. */
    public function for(Agent $agent, ?User $user): self
    {
        $tools = clone $this;
        $tools->agent = $agent;
        $tools->user = $user;

        return $tools;
    }

    public function definitions(): array
    {
        $allowed = $this->agent()->tools();
        $shared = array_values(array_filter(
            $this->tools->definitions(),
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
        ];
    }

    public function run(string $name, array $input): array
    {
        $tenant = $this->tenants->current();

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

        return $this->tools->run($name, $input);
    }

    private function agent(): Agent
    {
        return $this->agent ?? throw new InvalidArgumentException('No agent: call for() first.');
    }
}
