<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyEmailLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StoreEmailTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EmailTokenController extends Controller
{
    /**
     * Exchange the code that was emailed for an API token.
     */
    public function store(StoreEmailTokenRequest $request, VerifyEmailLoginCode $verifyEmailLoginCode): JsonResponse
    {
        $user = $verifyEmailLoginCode->handle(
            $request->emailAddress(),
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
}
