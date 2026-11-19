<?php

namespace App\Actions\Auth;

use App\Exceptions\SmsDeliveryFailed;
use App\Models\PhoneVerificationCode;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\Facades\DB;

class SendLoginCode
{
    public const EXPIRES_IN_MINUTES = 5;

    public function __construct(private SmsGateway $sms) {}

    /**
     * Create a new six digit code for the phone number and text it.
     *
     * Older codes for the same phone number stop working. When the text cannot
     * be sent, the new code is removed too.
     *
     * @throws SmsDeliveryFailed
     */
    public function handle(string $phone): void
    {
        $code = (string) random_int(100000, 999999);

        $verificationCode = DB::transaction(function () use ($phone, $code): PhoneVerificationCode {
            PhoneVerificationCode::where('phone', $phone)->delete();

            return PhoneVerificationCode::create([
                'phone' => $phone,
                'code' => $code,
                'expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ]);
        });

        try {
            $this->sms->send($phone, sprintf(
                '%s: Your code is %s. It expires in %d minutes. Do not share it with anyone.',
                config('services.sms.prefix'),
                $code,
                self::EXPIRES_IN_MINUTES,
            ));
        } catch (SmsDeliveryFailed $exception) {
            $verificationCode->delete();

            throw $exception;
        }
    }
}
