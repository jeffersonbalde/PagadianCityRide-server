<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StoreTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Exchange a login code for an API token.
     */
    public function store(StoreTokenRequest $request, VerifyLoginCode $verifyLoginCode): JsonResponse
    {
        $user = $verifyLoginCode->handle(
            $request->phoneNumber(),
            $request->validated('code'),
            $request->signUpRole(),
        );

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => 'The code is wrong or has expired.',
            ]);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages([
                'code' => 'This account is blocked. Contact the PagadianCityRide office.',
            ]);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user->load('driverProfile')),
        ]);
    }

    /**
     * Sign out by revoking the token that made the request.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
