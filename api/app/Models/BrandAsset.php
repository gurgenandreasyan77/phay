<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A picture of the business its AI agents look at (App\Agents\AgentTools
 * view_brand_assets): its logo (one) or an example it likes (a post, an
 * ad, a photo). Saved from a file attached in the chat or uploaded on the
 * team's page; kept on the private disk.
 */
class BrandAsset extends Model
{
    use BelongsToTenant;

    public const LOGO = 'logo';

    public const EXAMPLE = 'example';

    public const KINDS = [self::LOGO, self::EXAMPLE];

    /** Examples a business keeps at most: the agents look at all of them. */
    public const MAX_EXAMPLES = 6;

    protected $fillable = ['kind', 'mime', 'name', 'size', 'path', 'width', 'height', 'note', 'created_by'];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /** @return array<string, mixed> */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => $this->size,
            'note' => $this->note,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
