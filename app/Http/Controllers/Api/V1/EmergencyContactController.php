<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmergencyContactRequest;
use App\Http\Resources\Api\V1\EmergencyContactResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmergencyContactController extends Controller
{
    /**
     * The emergency contacts of the signed in person, oldest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return EmergencyContactResource::collection(
            $request->user()->emergencyContacts()->orderBy('id')->get(),
        );
    }

    /**
     * Add a person who gets a text message when an emergency alert is sent.
     */
    public function store(StoreEmergencyContactRequest $request): EmergencyContactResource
    {
        $contact = $request->user()->emergencyContacts()->create([
            'name' => $request->validated('name'),
            'relationship' => $request->validated('relationship'),
            'phone' => $request->phoneNumber(),
        ]);

        return new EmergencyContactResource($contact);
    }

    /**
     * Remove a contact. It only removes a contact of the signed in person.
     */
    public function destroy(Request $request, int $contact): Response
    {
        $request->user()->emergencyContacts()->findOrFail($contact)->delete();

        return response()->noContent();
    }
}
