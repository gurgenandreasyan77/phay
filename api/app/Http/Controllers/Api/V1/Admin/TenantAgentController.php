<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Agents\AgentCatalog;
use App\Agents\Agents;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Any business's AI agents from the platform level: the super admin turns
 * them on and off (like modules); an agent that is on adds its price to
 * the business's monthly payment unless its plan includes it.
 */
class TenantAgentController extends Controller
{
    public function __construct(private readonly Agents $agents) {}

    public function index(Tenant $tenant): JsonResponse
    {
        return response()->json(['data' => array_map($this->withoutNotes(...), $this->agents->overview($tenant))]);
    }

    public function update(Request $request, Tenant $tenant, string $agent): JsonResponse
    {
        $found = AgentCatalog::find($agent) ?? abort(404);
        $validated = $request->validate(['active' => ['required', 'boolean']]);

        $this->agents->setActive($tenant, $found, $validated['active'], $request->user());

        return response()->json(['data' => $this->withoutNotes(collect($this->agents->overview($tenant))->firstWhere('key', $found->key()))]);
    }

    /**
     * What an agent remembers is the business's own (its plans, clients,
     * prices): the platform sees only whether the agent is on.
     *
     * @param  array<string, mixed>  $agent
     * @return array<string, mixed>
     */
    private function withoutNotes(array $agent): array
    {
        unset($agent['notes']);

        return $agent;
    }
}
