<?php

namespace App\Assistant;

/**
 * A look-up that answers with more than JSON: content blocks (text and
 * images) for the tool_result, e.g. the brand's logo for an agent to look at.
 */
final class ToolOutput
{
    /** @param  list<array<string, mixed>>  $blocks  text and image blocks of the Messages API */
    public function __construct(public readonly array $blocks) {}
}
