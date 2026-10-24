<?php

namespace App\Security;

use App\Enums\SecurityEvent;
use App\Models\SecurityLogEntry;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Writes the security audit log. The request (when there is one) gives the
 * IP and the browser; console commands leave them empty.
 */
class SecurityLog
{
    /** @param array<string, mixed> $meta */
    public function record(
        SecurityEvent $event,
        ?User $user,
        array $meta = [],
        ?User $actor = null,
        ?string $email = null,
    ): SecurityLogEntry {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return SecurityLogEntry::query()->create([
            'user_id' => $user?->getKey(),
            'actor_id' => $actor && ! $actor->is($user) ? $actor->getKey() : null,
            'email' => $email ?? $user?->email,
            'event' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
