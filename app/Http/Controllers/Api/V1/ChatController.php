<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideMessage;
use App\RideStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * How many chats the list shows.
     */
    private const LIMIT = 50;

    /**
     * The chats of the signed in person, like the list of Messenger: one for each ride that had a driver and has
     * messages, or is going on. The one with the newest message is first. Each has the other person, the last
     * message and how many messages were not read yet.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rides = Ride::query()
            ->where(fn ($query) => $query->where('passenger_id', $user->id)->orWhere('driver_id', $user->id))
            ->whereNotNull('driver_id')
            ->where(fn ($query) => $query->whereHas('messages')->orWhereIn('status', [RideStatus::Accepted, RideStatus::Arrived, RideStatus::InProgress]))
            ->with(['passenger', 'driver.driverProfile.documents'])
            ->withMax('messages as last_message_id', 'id')
            ->withCount(['messages as unread_count' => fn ($messages) => $messages->where('sender_id', '!=', $user->id)->whereNull('read_at')])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get();

        $last = RideMessage::query()
            ->whereIn('id', $rides->pluck('last_message_id')->filter())
            ->get()
            ->keyBy('ride_id');

        $chats = $rides->map(function (Ride $ride) use ($user, $last): array {
            $asPassenger = $ride->passenger_id === $user->id;
            $message = $last->get($ride->id);
            $profile = $ride->driver?->driverProfile;

            return [
                'ride_id' => $ride->id,
                // Every chat of the list can be written in: it is of a ride that had a driver, also after the trip or a cancel.
                'open' => true,
                'ongoing' => in_array($ride->status, [RideStatus::Accepted, RideStatus::Arrived, RideStatus::InProgress], true),
                'name' => $asPassenger ? $ride->driver?->name : $ride->passenger?->name,
                'photo_url' => $asPassenger ? $ride->driverPhotoUrl() : null,
                'subtitle' => $asPassenger
                    ? collect([$profile?->tricycle_number, $profile?->plate_number])->filter()->implode(' · ')
                    : $ride->pickup_label,
                'unread_count' => (int) $ride->unread_count,
                'last_message' => $message === null ? null : [
                    'body' => $message->body,
                    'has_photo' => $message->image_path !== null,
                    'mine' => $message->sender_id === $user->id,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
                'active_at' => ($message?->created_at ?? $ride->accepted_at ?? $ride->created_at)->toIso8601String(),
            ];
        })->sortByDesc('active_at')->values();

        return response()->json($chats);
    }
}
