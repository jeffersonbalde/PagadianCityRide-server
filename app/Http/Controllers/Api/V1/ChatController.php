<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RideMessage;
use App\Models\User;
use App\RideStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;

class ChatController extends Controller
{
    /**
     * How many chats the list shows.
     */
    private const LIMIT = 50;

    /**
     * How many of the newest rides of the person are looked at to find those chats.
     */
    private const RIDES = 500;

    /**
     * The chats of the signed in person, like the list of Messenger: one for each other person they shared a ride
     * with, if somebody wrote or a ride of theirs is going on. The one with the newest message is first. Each has
     * the other person, the last message and how many messages were not read yet.
     *
     * A chat is opened by a ride number: that of the ride that is going on, or else of their newest ride.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rides = Ride::query()
            ->where(fn ($query) => $query->where('passenger_id', $user->id)->orWhere('driver_id', $user->id))
            ->whereNotNull('driver_id')
            ->where(fn ($query) => $query->whereHas('messages')->orWhereIn('status', $this->ongoing()))
            ->latest('id')
            ->limit(self::RIDES)
            ->get();

        $written = $this->written($rides, $user);

        $last = RideMessage::query()
            ->whereIn('id', $written->pluck('last_id'))
            ->get()
            ->keyBy('id');

        // The rides are newest first, so each group starts with the newest ride the two people shared.
        $together = $rides->groupBy(fn (Ride $ride): string => "{$ride->passenger_id}-{$ride->driver_id}");

        $opened = new Collection($together->map(
            fn (Collection $shared): Ride => $shared->first(fn (Ride $ride): bool => in_array($ride->status, $this->ongoing(), true)) ?? $shared->first(),
        )->values()->all());

        $opened->load(['passenger', 'driver.driverProfile.documents']);

        $chats = $opened->map(function (Ride $ride) use ($user, $written, $last): array {
            $asPassenger = $ride->passenger_id === $user->id;
            $summary = $written->get("{$ride->passenger_id}-{$ride->driver_id}");
            $message = $summary === null ? null : $last->get($summary->last_id);
            $profile = $ride->driver?->driverProfile;

            return [
                'ride_id' => $ride->id,
                // Every chat of the list can be written in: it is of a ride that had a driver, also after the trip or a cancel.
                'open' => true,
                'ongoing' => in_array($ride->status, $this->ongoing(), true),
                'name' => $asPassenger ? $ride->driver?->name : $ride->passenger?->name,
                'photo_url' => $asPassenger ? $ride->driverPhotoUrl() : null,
                'subtitle' => $asPassenger
                    ? collect([$profile?->tricycle_number, $profile?->plate_number])->filter()->implode(' · ')
                    : $ride->pickup_label,
                'unread_count' => (int) ($summary?->unread ?? 0),
                'last_message' => $message === null ? null : [
                    'body' => $message->body,
                    'has_photo' => $message->image_path !== null,
                    'mine' => $message->sender_id === $user->id,
                    'created_at' => $message->created_at->toIso8601String(),
                ],
                'active_at' => ($message?->created_at ?? $ride->accepted_at ?? $ride->created_at)->toIso8601String(),
            ];
        })->sortByDesc('active_at')->take(self::LIMIT)->values();

        return response()->json($chats);
    }

    /**
     * @return list<RideStatus>
     */
    private function ongoing(): array
    {
        return [RideStatus::Accepted, RideStatus::Arrived, RideStatus::InProgress];
    }

    /**
     * For each pair of people on these rides who wrote something: the number of their last message, and how many of
     * the other person's messages were not read.
     *
     * @param  Collection<int, Ride>  $rides
     * @return SupportCollection<string, object{passenger_id: int, driver_id: int, last_id: int, unread: int|string}>
     */
    private function written(Collection $rides, User $user): SupportCollection
    {
        return RideMessage::query()
            ->join('rides', 'rides.id', '=', 'ride_messages.ride_id')
            ->whereIn('ride_messages.ride_id', $rides->modelKeys())
            ->where(fn ($query) => $query
                ->whereColumn('ride_messages.sender_id', 'rides.passenger_id')
                ->orWhereColumn('ride_messages.sender_id', 'rides.driver_id'))
            ->groupBy('rides.passenger_id', 'rides.driver_id')
            ->selectRaw('rides.passenger_id, rides.driver_id, max(ride_messages.id) as last_id')
            ->selectRaw('sum(case when ride_messages.sender_id <> ? and ride_messages.read_at is null then 1 else 0 end) as unread', [$user->id])
            ->toBase()
            ->get()
            ->keyBy(fn (object $row): string => "{$row->passenger_id}-{$row->driver_id}");
    }
}
