<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's AI team: every agent of the platform with whether the
 * business has it, the brand profile they share, and what each remembers.
 * The owner chats with them (insights, like the assistant); the owner and
 * the admin (settings) see the team on the employees page and teach it.
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly Agents $agents,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $role = $this->allow($request);
        $tenant = $this->context->current();

        return response()->json(['data' => [
            'agents' => $this->agents->overview($tenant),
            'profile' => $this->agents->profile($tenant)->present(),
            'canChat' => $role->can(BusinessRole::INSIGHTS),
            // Asking the platform for an agent goes to the chat (as for modules).
            'canRequest' => $role->can(BusinessRole::SETTINGS) && ! $request->user()->isPlatformStaff(),
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $this->allow($request);

        $text = ['sometimes', 'nullable', 'string', 'max:'.BrandProfile::MAX_TEXT];
        $validated = $request->validate([
            ...array_fill_keys(BrandProfile::FIELDS, $text),
            'colors' => ['sometimes', 'nullable', 'array', 'max:'.BrandProfile::MAX_COLORS],
            'colors.*.hex' => ['required', 'string', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colors.*.name' => ['nullable', 'string', 'max:40'],
        ]);

        if (array_key_exists('colors', $validated)) {
            $validated['colors'] ??= [];
        }

        $profile = $this->agents->saveProfile($this->context->current(), $validated, $request->user());

        return response()->json(['data' => $profile->present()]);
    }

    /** What an agent remembers about the business: the owner reads, edits or clears it. */
    public function updateNotes(Request $request, string $agent): JsonResponse
    {
        $this->allow($request);
        $found = AgentCatalog::find($agent) ?? abort(404);
        $tenant = $this->context->current();
        abort_unless($this->agents->isActive($tenant, $found), 403, 'This AI agent is not on your team yet.');

        $validated = $request->validate(['notes' => ['present', 'nullable', 'string', 'max:'.Agents::MAX_NOTES]]);
        $row = $this->agents->setNotes($tenant, $found, $validated['notes']);

        return response()->json(['data' => ['key' => $found->key(), 'notes' => $row->notes]]);
    }

    /** The owner (chats with the team) or the admin (manages employees). */
    private function allow(Request $request): BusinessRole
    {
        $role = $request->user()->businessRole($this->context->current());
        abort_unless(
            $role && ($role->can(BusinessRole::INSIGHTS) || $role->can(BusinessRole::SETTINGS)),
            403,
            'Your role in this business does not allow this.',
        );

        return $role;
    }
}
