<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportMessageRequest;
use App\Http\Resources\Admin\SupportMessageResource;
use App\Services\SupportChat;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business side of the chat with the platform team (owner and admin).
 * Platform staff answer from the platform level instead
 * (PlatformSupportController), so they get 403 here: a message they wrote
 * here would look like the business's own.
 */
class SupportController extends Controller
{
    public function __construct(
        private readonly SupportChat $chat,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureBusinessSide($request);

        $tenant = $this->context->current();
        $this->chat->markRead($tenant, readerIsPlatform: false);

        return SupportMessageResource::collection($this->chat->messages($tenant))->response();
    }

    public function store(StoreSupportMessageRequest $request): JsonResponse
    {
        $this->ensureBusinessSide($request);

        $message = $this->chat->send($this->context->current(), $request->user(), false, $request->validated('body'));

        return (new SupportMessageResource($message))->response()->setStatusCode(201);
    }

    /**
     * Unread messages for the menu badge: the platform team's for a
     * business's people, all businesses' for platform staff.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->context->current();

        return response()->json(['data' => [
            'business' => ! $user->isPlatformStaff() && $user->businessRole($tenant)?->can(BusinessRole::SETTINGS)
                ? $this->chat->unreadForBusiness($tenant)
                : null,
            'platform' => $user->isPlatformStaff() ? $this->chat->unreadForPlatform() : null,
        ]]);
    }

    private function ensureBusinessSide(Request $request): void
    {
        abort_if($request->user()->isPlatformStaff(), 403, 'Platform staff answer businesses from the platform level.');
    }
}
