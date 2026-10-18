<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportMessageRequest;
use App\Http\Resources\Admin\SupportMessageResource;
use App\Models\SupportMessage;
use App\Models\Tenant;
use App\Services\SupportChat;
use Illuminate\Http\JsonResponse;

/**
 * The platform side of the chat: every business's conversation, answered
 * by platform staff (super admin and moderators).
 */
class PlatformSupportController extends Controller
{
    public function __construct(private readonly SupportChat $chat) {}

    /** Businesses that have written or been written to, latest conversation first. */
    public function conversations(): JsonResponse
    {
        $last = SupportMessage::withoutTenancy()
            ->selectRaw('tenant_id, MAX(id) as last_id')
            ->groupBy('tenant_id');

        $unread = SupportMessage::withoutTenancy()
            ->unreadFrom(false)
            ->selectRaw('tenant_id, COUNT(*) as unread')
            ->groupBy('tenant_id')
            ->pluck('unread', 'tenant_id');

        $lastMessages = SupportMessage::withoutTenancy()
            ->joinSub($last, 'last', fn ($join) => $join->on('support_messages.id', '=', 'last.last_id'))
            ->get(['support_messages.*'])
            ->keyBy('tenant_id');

        $tenants = Tenant::query()->whereKey($lastMessages->keys())->get();

        $rows = $tenants
            ->map(fn (Tenant $tenant) => [
                'tenant' => ['id' => $tenant->id, 'name' => $tenant->name, 'logo' => $tenant->logo],
                'lastMessage' => new SupportMessageResource($lastMessages[$tenant->id]),
                'unread' => (int) ($unread[$tenant->id] ?? 0),
            ])
            ->sortByDesc(fn (array $row) => $row['lastMessage']->id)
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function index(Tenant $tenant): JsonResponse
    {
        $this->chat->markRead($tenant, readerIsPlatform: true);

        return SupportMessageResource::collection($this->chat->messages($tenant))->response();
    }

    public function store(StoreSupportMessageRequest $request, Tenant $tenant): JsonResponse
    {
        $message = $this->chat->send($tenant, $request->user(), true, $request->validated('body'));

        return (new SupportMessageResource($message))->response()->setStatusCode(201);
    }
}
