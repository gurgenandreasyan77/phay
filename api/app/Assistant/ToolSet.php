<?php

namespace App\Assistant;

use InvalidArgumentException;

/**
 * Look-ups the model may make while it answers: the assistant's own
 * (BusinessTools) or an agent's share of them (App\Agents\AgentTools).
 */
interface ToolSet
{
    /**
     * Tool definitions for the Messages API (SDK keys: inputSchema).
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException on a bad tool or input
     */
    public function run(string $name, array $input): array;
}
