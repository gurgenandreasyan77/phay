<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Models\SecurityLogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The platform's security audit log (super admin): every sign-in, failed
 * attempt, 2FA change, password reset and invitation, newest first.
 */
class SecurityEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['nullable', 'string', Rule::enum(SecurityEvent::class)],
            'email' => ['nullable', 'string', 'max:255'],
            'warnings' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $warnings = array_values(array_filter(SecurityEvent::cases(), fn (SecurityEvent $e) => $e->isWarning()));

        $page = SecurityLogEntry::query()
            ->with(['user:id,name,email', 'actor:id,name,email'])
            ->when($validated['event'] ?? null, fn ($q, $event) => $q->where('event', $event))
            ->when($validated['email'] ?? null, fn ($q, $email) => $q->where('email', 'like', '%'.mb_strtolower($email).'%'))
            ->when($request->boolean('warnings'), fn ($q) => $q->whereIn('event', $warnings))
            ->latest('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (SecurityLogEntry $entry) => self::present($entry, withPeople: true)),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
            'events' => collect(SecurityEvent::cases())->map(fn (SecurityEvent $e) => ['value' => $e->value, 'label' => $e->label()]),
        ]);
    }

    /** @return array<string, mixed> */
    public static function present(SecurityLogEntry $entry, bool $withPeople = false): array
    {
        return [
            'id' => $entry->id,
            'event' => $entry->event->value,
            'label' => $entry->event->label(),
            'warning' => $entry->event->isWarning(),
            'ipAddress' => $entry->ip_address,
            'userAgent' => $entry->user_agent,
            'meta' => $entry->meta ?? (object) [],
            'createdAt' => $entry->created_at?->toIso8601String(),
            ...($withPeople ? [
                'email' => $entry->email,
                'user' => $entry->user ? ['id' => $entry->user->id, 'name' => $entry->user->name] : null,
                'actor' => $entry->actor ? ['id' => $entry->actor->id, 'name' => $entry->actor->name, 'email' => $entry->actor->email] : null,
            ] : [
                'actor' => $entry->actor_id ? ['name' => $entry->actor?->name] : null,
            ]),
        ];
    }
}
