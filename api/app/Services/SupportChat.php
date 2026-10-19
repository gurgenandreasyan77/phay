<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The conversation between a business and the platform team. Both sides
 * read it by polling; opening it marks the other side's messages as read.
 */
class SupportChat
{
    /** @return Collection<int, SupportMessage> the latest messages, oldest first */
    public function messages(Tenant $tenant): Collection
    {
        return $tenant->supportMessages()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(SupportMessage::WINDOW)
            ->get()
            ->reverse()
            ->values();
    }

    /** The reader has opened the conversation: what the other side wrote is read now. */
    public function markRead(Tenant $tenant, bool $readerIsPlatform): void
    {
        $tenant->supportMessages()
            ->unreadFrom(! $readerIsPlatform)
            ->update(['read_at' => now()]);
    }

    public function send(Tenant $tenant, User $author, bool $fromPlatform, string $body): SupportMessage
    {
        $message = $tenant->supportMessages()->create([
            'user_id' => $author->id,
            'from_platform' => $fromPlatform,
            'body' => $body,
        ]);

        return $message->setRelation('user', $author);
    }

    /** A message from the platform itself (no author), e.g. about the subscription. */
    public function notify(Tenant $tenant, string $body): SupportMessage
    {
        return $tenant->supportMessages()->create([
            'user_id' => null,
            'from_platform' => true,
            'body' => $body,
        ]);
    }

    /** Messages of the platform team the business hasn't read. */
    public function unreadForBusiness(Tenant $tenant): int
    {
        return $tenant->supportMessages()->unreadFrom(true)->count();
    }

    /** Messages of all businesses the platform team hasn't read. */
    public function unreadForPlatform(): int
    {
        return SupportMessage::withoutTenancy()->unreadFrom(false)->count();
    }
}
