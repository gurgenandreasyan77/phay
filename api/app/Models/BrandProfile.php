<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * What every AI agent of a business knows about it: what it does and
 * sells, for whom, how it speaks, how it looks (style, colours), examples
 * it likes and what to avoid. The owner fills it in a form, or an agent
 * asks in the chat and saves the answers (App\Agents\AgentTools).
 */
class BrandProfile extends Model
{
    use BelongsToTenant;

    /** The text fields, in the order the form and the prompt show them. */
    public const FIELDS = ['about', 'offer', 'audience', 'tone', 'visual_style', 'examples', 'avoid'];

    /** Characters a text field keeps at most: the profile goes into every agent's prompt. */
    public const MAX_TEXT = 1500;

    public const MAX_COLORS = 8;

    protected $fillable = ['about', 'offer', 'audience', 'tone', 'visual_style', 'colors', 'examples', 'avoid', 'updated_by'];

    protected $casts = [
        'colors' => 'array',
    ];

    /** The fields filled so far (colours included). */
    public function filled(): array
    {
        $filled = array_values(array_filter(self::FIELDS, fn (string $f) => filled($this->{$f})));

        return ($this->colors ?? []) === [] ? $filled : [...$filled, 'colors'];
    }

    /**
     * Enough for the agents to work on brand: what the business is, for
     * whom, and how it speaks or looks.
     */
    public function isComplete(): bool
    {
        return filled($this->about) && filled($this->audience) && (filled($this->tone) || filled($this->visual_style));
    }

    /** @return list<string> the fields an agent should still ask about */
    public function missing(): array
    {
        return array_values(array_diff([...self::FIELDS, 'colors'], $this->filled()));
    }

    /**
     * The profile for the API and the prompt.
     *
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'about' => $this->about,
            'offer' => $this->offer,
            'audience' => $this->audience,
            'tone' => $this->tone,
            'visualStyle' => $this->visual_style,
            'colors' => array_values($this->colors ?? []),
            'examples' => $this->examples,
            'avoid' => $this->avoid,
            'complete' => $this->isComplete(),
            'filled' => count($this->filled()),
            'total' => count(self::FIELDS) + 1,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
