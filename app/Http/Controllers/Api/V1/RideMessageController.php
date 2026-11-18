<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ride\SendRideMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRideMessageRequest;
use App\Http\Resources\Api\V1\RideMessageResource;
use App\Models\Ride;
use App\Models\RideMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RideMessageController extends Controller
{
    /**
     * The last messages of the chat, oldest first. With `after`, only the messages after that one. What the other
     * person wrote counts as read once it is fetched here.
     */
    public function index(Request $request, Ride $ride): AnonymousResourceCollection
    {
        Gate::authorize('readMessages', $ride);

        $after = $request->integer('after');

        $messages = $after > 0
            ? $ride->messages()->where('id', '>', $after)->orderBy('id')->limit(100)->get()
            : $ride->messages()->latest('id')->limit(100)->get()->reverse()->values();

        $ride->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return RideMessageResource::collection($messages);
    }

    /**
     * Write a message to the other person of the ride: words, a photo, or both.
     */
    public function store(StoreRideMessageRequest $request, Ride $ride, SendRideMessage $sendRideMessage): JsonResponse
    {
        Gate::authorize('sendMessage', $ride);

        $message = $sendRideMessage->handle($ride, $request->user(), $request->validated('body'), $request->file('image'));

        return RideMessageResource::make($message)->response()->setStatusCode(201);
    }

    /**
     * The photo of a message. The route is signed: only the link that the two people of the ride were given works.
     */
    public function image(Ride $ride, RideMessage $message): StreamedResponse
    {
        abort_unless($message->ride_id === $ride->id && $message->image_path !== null, 404);

        return Storage::response($message->image_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
