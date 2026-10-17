<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** The token's name is the device name; logging in again from the same device replaces its token. */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // ponytail: the 2FA challenge is a web flow; add a code step here when the mobile app needs it
        if ($user->two_factor_confirmed_at) {
            throw ValidationException::withMessages(['email' => 'Two-factor authentication is on for this account, and mobile login does not support it yet. Turn it off in the web app to sign in on mobile.']);
        }

        $user->tokens()->where('name', $request->device_name)->delete();

        return response()->json([
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    /** Account deletion for the app (stores require it); needs the password like the web form. */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
