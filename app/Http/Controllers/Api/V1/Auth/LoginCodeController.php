<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StoreLoginCodeRequest;
use Illuminate\Http\JsonResponse;

class LoginCodeController extends Controller
{
    /**
     * Text a login code to the phone number.
     *
     * The answer is the same whether or not the number already has an account.
     */
    public function store(StoreLoginCodeRequest $request, SendLoginCode $sendLoginCode): JsonResponse
    {
        $sendLoginCode->handle($request->phoneNumber());

        return response()->json(['message' => 'We sent a code to your phone.'], 202);
    }
}
