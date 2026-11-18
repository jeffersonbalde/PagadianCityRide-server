<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Emergency\SendEmergencyAlert;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmergencyAlertRequest;
use App\Http\Resources\Api\V1\EmergencyContactResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmergencyAlertController extends Controller
{
    /**
     * Send the emergency alert: every contact of the person gets a text message with the
     * location. The answer lists the contacts who were texted, so the app can show who was told.
     */
    public function store(StoreEmergencyAlertRequest $request, SendEmergencyAlert $sendEmergencyAlert): AnonymousResourceCollection
    {
        abort_unless($request->user()->emergencyContacts()->exists(), 422, 'Add an emergency contact first.');

        $texted = $sendEmergencyAlert->handle(
            $request->user(),
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        return EmergencyContactResource::collection($texted);
    }
}
