<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Notifications\Notifier;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The bell in the admin's header: the signed-in person's latest
 * notifications in the business they are in, read by polling.
 */
class NotificationController extends Controller
{
    public const LIMIT = 30;

    public function __construct(
        private readonly TenantContext $context,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->context->current();
        $this->notifier->todayOrders($user, $tenant);

        $visible = fn () => AdminNotification::query()->visibleTo($user, $tenant);

        return response()->json([
            'data' => $visible()->with('tenant:id,name')->orderByDesc('id')->limit(self::LIMIT)->get()
                ->map(fn (AdminNotification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'scope' => $n->scope->value,
                    'title' => $n->title,
                    'body' => $n->body,
                    'url' => $n->url,
                    'tenant' => $n->tenant ? ['id' => $n->tenant->id, 'name' => $n->tenant->name] : null,
                    'read' => $n->read_at !== null,
                    'createdAt' => $n->created_at?->toIso8601String(),
                ])->values(),
            'meta' => ['unread' => $visible()->unread()->count()],
        ]);
    }

    public function read(Request $request, int $notification): Response
    {
        AdminNotification::query()
            ->visibleTo($request->user(), $this->context->current())
            ->whereKey($notification)
            ->firstOrFail()
            ->forceFill(['read_at' => now()])
            ->save();

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        AdminNotification::query()
            ->visibleTo($request->user(), $this->context->current())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
