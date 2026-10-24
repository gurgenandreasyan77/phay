<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SecurityEvent;
use App\Http\Controllers\Controller;
use App\Security\AccessTokens;
use App\Security\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Anyone signed in changes their own password by typing the current one.
 * Other devices signed in with the old password are signed out; this one
 * stays in.
 */
class PasswordController extends Controller
{
    public function update(Request $request, AccessTokens $tokens, SecurityLog $log): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => $validated['password'], 'password_changed_at' => now()])->save();
        $tokens->revokeOthers($user);
        $log->record(SecurityEvent::PasswordChanged, $user);

        return response()->json(['data' => ['status' => 'ok']]);
    }
}
