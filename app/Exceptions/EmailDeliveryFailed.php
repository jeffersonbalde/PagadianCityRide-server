<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class EmailDeliveryFailed extends RuntimeException
{
    /**
     * Create the exception from the error the mail server returned.
     */
    public static function because(Throwable $previous): self
    {
        return new self('The mail server could not deliver the message.', 0, $previous);
    }

    /**
     * Render the exception as a response, without exposing mail server details.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'We could not send the email. Please try again in a moment.',
        ], 503);
    }
}
