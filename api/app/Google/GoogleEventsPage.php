<?php

namespace App\Google;

final readonly class GoogleEventsPage
{
    /** @param list<array> $items events as Google sends them */
    public function __construct(
        public array $items,
        public ?string $nextPageToken = null,
        public ?string $nextSyncToken = null,
    ) {}
}
