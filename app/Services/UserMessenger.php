<?php

namespace App\Services;

use App\Exceptions\SmsDeliveryFailed;
use App\Mail\PlainMessageMail;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Tells one person something in words, such as the answer to a driver application.
 *
 * A mobile number that was proven by a text message gets a text. A person who signed in with an email
 * gets an email. A failure is reported and does not stop the work that was being done.
 */
class UserMessenger
{
    public function __construct(private SmsGateway $sms) {}

    public function send(User $user, string $title, string $message): void
    {
        if ($user->phone !== null && $user->phone_verified_at !== null) {
            try {
                $this->sms->send($user->phone, config('services.sms.prefix').': '.$message);
            } catch (SmsDeliveryFailed $exception) {
                report($exception);
            }

            return;
        }

        if ($user->email === null) {
            return;
        }

        try {
            Mail::to($user->email)->send(new PlainMessageMail($title, $message));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }
    }
}
