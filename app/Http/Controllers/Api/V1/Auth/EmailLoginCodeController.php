<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendEmailLoginCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\StoreEmailLoginCodeRequest;
use Illuminate\Http\JsonResponse;

class EmailLoginCodeController extends Controller
{
    /**
     * Email a login code to the address.
     *
     * The answer is the same whether or not the address already has an account.
     */
    public function store(StoreEmailLoginCodeRequest $request, SendEmailLoginCode $sendEmailLoginCode): JsonResponse
    {
        $sendEmailLoginCode->handle($request->emailAddress());

        return response()->json(['message' => 'We sent a code to your email.'], 202);
    }
}
