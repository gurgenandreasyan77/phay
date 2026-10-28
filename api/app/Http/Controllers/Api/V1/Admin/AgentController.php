<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Agents\AgentTerms;
use App\Assistant\Attachments;
use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\AssistantAttachment;
use App\Models\BrandAsset;
use App\Models\BrandProfile;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as FileResponse;

/**
 * The business's AI team: every agent of the platform with whether the
 * business has it, the brand profile they share, and what each remembers.
 * The owner chats with them (insights, like the assistant); the owner and
 * the admin (settings) see the team on the employees page and teach it,
 * with the brand's pictures (its logo and examples) the agents look at.
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly Agents $agents,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request, AgentTerms $terms): JsonResponse
    {
        $role = $this->allow($request);
        $tenant = $this->context->current();
        $terms->sync($tenant);

        return response()->json(['data' => [
            'agents' => $this->agents->overview($tenant),
            'profile' => $this->agents->profile($tenant)->present(),
            'assets' => self::assets(),
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

    /**
     * Keeps a picture for the agents: uploaded here, or one the person
     * attached in the chat (attachment_id). A logo replaces the old one.
     */
    public function storeAsset(Request $request, Attachments $attachments): JsonResponse
    {
        $this->allow($request);
        $validated = $request->validate([
            'kind' => ['required', Rule::in(BrandAsset::KINDS)],
            'note' => ['nullable', 'string', 'max:300'],
            'file' => ['required_without:attachment_id', 'file', 'max:'.intdiv(Attachments::MAX_IMAGE_BYTES, 1024)],
            'attachment_id' => ['required_without:file', 'integer'],
        ], ['file.max' => 'Նկարը պետք է լինի մինչև 5 ՄԲ։']);

        $asset = isset($validated['attachment_id'])
            ? $attachments->toBrandAsset(
                AssistantAttachment::query()->of($request->user())->findOrFail($validated['attachment_id']),
                $validated['kind'],
                $validated['note'] ?? null,
                $request->user(),
            )
            : $attachments->uploadBrandAsset($request->file('file'), $validated['kind'], $validated['note'] ?? null, $request->user());

        return response()->json(['data' => ['asset' => $asset->present(), 'assets' => self::assets()]], 201);
    }

    public function assetFile(Request $request, BrandAsset $brandAsset): FileResponse
    {
        $this->allow($request);

        return AssistantAttachmentController::serve($brandAsset->path, $brandAsset->name, $brandAsset->mime);
    }

    public function destroyAsset(Request $request, BrandAsset $brandAsset, Attachments $attachments): Response
    {
        $this->allow($request);
        $brandAsset->delete();
        $attachments->delete([$brandAsset->path]);

        return response()->noContent();
    }

    /** @return list<array<string, mixed>> the logo first, then the examples */
    private static function assets(): array
    {
        return BrandAsset::query()
            ->orderByRaw("case when kind = 'logo' then 0 else 1 end")
            ->orderBy('id')
            ->get()
            ->map(fn (BrandAsset $asset) => $asset->present())
            ->values()
            ->all();
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
