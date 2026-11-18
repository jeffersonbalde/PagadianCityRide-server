<?php

namespace App\Actions\Ride;

use App\AlertType;
use App\Events\RideMessageSent;
use App\Models\Ride;
use App\Models\RideMessage;
use App\Models\User;
use App\Notifications\Alert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SendRideMessage
{
    /**
     * Write a message to the other person of the ride: words, a photo, or both. They hear it live when the
     * app is open, and get a push notification when it is not. The phone numbers stay private.
     */
    public function handle(Ride $ride, User $sender, ?string $body, ?UploadedFile $image = null): RideMessage
    {
        $message = $ride->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
            'image_path' => $image?->store('ride-message-photos/'.$ride->id),
        ]);

        $recipient = $ride->passenger_id === $sender->id ? $ride->driver : $ride->passenger;

        RideMessageSent::dispatch($message, $recipient->id);

        $first = Str::of($sender->name ?? '')->before(' ')->toString();
        $words = $body !== null ? Str::limit($body, 80) : 'Sent a photo';

        $recipient->notify(new Alert(AlertType::NewMessage, $ride, ($first !== '' ? $first.': ' : '').$words));

        return $message;
    }
}
