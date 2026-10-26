<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MetaDataDeletion;
use App\Social\MetaAccounts;
use App\Social\SignedRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What Meta calls when a person removes the app from their Facebook
 * settings (deauthorize) or asks for their data to be deleted: every
 * business's connection made with that login is deleted, as on disconnect.
 * Both are set in the Meta app (App settings → Basic / Facebook Login →
 * Settings); the request is signed with the app secret.
 */
class MetaCallbackController extends Controller
{
    public function __construct(private readonly MetaAccounts $accounts) {}

    public function deauthorize(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $this->accounts->forgetMetaUser($userId, 'deauthorize');

        return response()->json(['ok' => true]);
    }

    /** Meta shows the person the URL and the code: they can check the deletion was done. */
    public function deletion(Request $request): JsonResponse
    {
        $deletion = $this->accounts->forgetMetaUser($this->userId($request), 'deletion');

        return response()->json([
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/meta/data-deletion/'.$deletion->confirmation_code,
            'confirmation_code' => $deletion->confirmation_code,
        ]);
    }

    public function status(string $code): JsonResponse
    {
        $deletion = MetaDataDeletion::query()->where('confirmation_code', $code)->firstOrFail();

        return response()->json([
            'confirmation_code' => $deletion->confirmation_code,
            'status' => 'completed',
            'message' => 'All data Phay received from Meta for this Facebook login was deleted.',
            'deleted_at' => $deletion->created_at?->toIso8601String(),
        ]);
    }

    private function userId(Request $request): string
    {
        $payload = SignedRequest::parse($request->input('signed_request'), config('services.meta.app_secret'));
        $userId = $payload['user_id'] ?? null;

        abort_unless(is_scalar($userId) && (string) $userId !== '', 400, 'Invalid signed_request.');

        return (string) $userId;
    }
}
