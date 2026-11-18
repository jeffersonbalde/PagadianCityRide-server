<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class SmsDeliveryFailed extends RuntimeException
{
    /**
     * Create the exception from the error the SMS provider returned.
     */
    public static function because(Throwable $previous): self
    {
        return new self('The SMS provider could not deliver the message.', 0, $previous);
    }

    /**
     * Render the exception as a response, without exposing provider details.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'We could not send the text message. Please try again in a moment.',
        ], 503);
    }
}
