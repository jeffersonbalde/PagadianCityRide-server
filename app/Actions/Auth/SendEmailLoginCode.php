<?php

namespace App\Actions\Auth;

use App\Exceptions\EmailDeliveryFailed;
use App\Mail\LoginCodeMail;
use App\Models\EmailLoginCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendEmailLoginCode
{
    public const EXPIRES_IN_MINUTES = 10;

    /**
     * Create a new six digit code for the email address and send it.
     *
     * Older codes for the same address stop working. When the email cannot be
     * sent, the new code is removed too.
     *
     * @throws EmailDeliveryFailed
     */
    public function handle(string $email): void
    {
        $code = (string) random_int(100000, 999999);

        $loginCode = DB::transaction(function () use ($email, $code): EmailLoginCode {
            EmailLoginCode::where('email', $email)->delete();

            return EmailLoginCode::create([
                'email' => $email,
                'code' => $code,
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);
        });

        try {
            Mail::to($email)->send(new LoginCodeMail($code, self::EXPIRES_IN_MINUTES));
        } catch (TransportExceptionInterface $exception) {
            $loginCode->delete();

            throw EmailDeliveryFailed::because($exception);
        }
    }
}
