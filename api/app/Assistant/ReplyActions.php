<?php

namespace App\Assistant;

/**
 * What one answer of the assistant offers besides its text, collected
 * from its tools while it answers: a reshape of tables for the owner to
 * apply ({type: reshape, id}) or a file to download ({type: download,
 * label, href, format}). The answer keeps them (assistant_messages.actions)
 * and the chat shows them as cards: the model never changes the tables
 * itself, the owner presses the button.
 */
final class ReplyActions
{
    /** Cards one answer may hold. */
    public const MAX = 6;

    /** @var list<array<string, mixed>> */
    private array $actions = [];

    /** @param  array<string, mixed>  $action */
    public function add(array $action): void
    {
        // The same card twice (the model asked again) shows once.
        if (in_array($action, $this->actions, true) || count($this->actions) >= self::MAX) {
            return;
        }

        $this->actions[] = $action;
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->actions;
    }

    public function reset(): void
    {
        $this->actions = [];
    }
}
