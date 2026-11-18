<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyDeviceRequest;
use App\Http\Requests\Api\V1\StoreDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    /**
     * Remember the phone for push notifications. The app sends the token every
     * time it opens, so this is safe to repeat. A phone that was used by
     * another person before now belongs to the person who signed in.
     */
    public function store(StoreDeviceRequest $request): Response
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
            ['user_id' => $request->user()->id, 'platform' => $request->validated('platform')],
        );

        return response()->noContent();
    }

    /**
     * Forget the phone, for example when the person signs out. It only
     * removes a phone of the signed in person.
     */
    public function destroy(DestroyDeviceRequest $request): Response
    {
        $request->user()->deviceTokens()->where('token', $request->validated('token'))->delete();

        return response()->noContent();
    }
}
