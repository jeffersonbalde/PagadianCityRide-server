<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMeRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
    /**
     * Show the signed-in person.
     */
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('driverProfile'));
    }

    /**
     * Save the name of the signed-in person. A new account has no name until
     * the app asks for it after the first sign-in. The phone number and the
     * role cannot be changed here.
     */
    public function update(UpdateMeRequest $request): UserResource
    {
        $request->user()->update(['name' => trim($request->validated('name'))]);

        return UserResource::make($request->user()->load('driverProfile'));
    }

    /**
     * Delete the account of the signed-in person for good. The phone number can then sign up again
     * as a new account.
     */
    public function destroy(Request $request, DeleteAccount $deleteAccount): Response
    {
        $deleteAccount->handle($request->user());

        return response()->noContent();
    }
}
