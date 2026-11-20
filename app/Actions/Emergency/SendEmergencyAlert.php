<?php

namespace App\Actions\Emergency;

use App\Exceptions\SmsDeliveryFailed;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\Sms\SmsGateway;

class SendEmergencyAlert
{
    public function __construct(private SmsGateway $sms) {}

    /**
     * Text every emergency contact of the person, with the name of the person and a map link of
     * where they are. A contact who cannot be reached does not stop the others.
     *
     * @return list<EmergencyContact> The contacts who were texted.
     */
    public function handle(User $user, float $latitude, float $longitude): array
    {
        $message = sprintf(
            '%s: %s sent an emergency alert from PagadianCityRide. Location: https://maps.google.com/?q=%s,%s',
            config('services.sms.prefix'),
            $user->name ?? $user->phone,
            round($latitude, 6),
            round($longitude, 6),
        );

        $texted = [];

        foreach ($user->emergencyContacts()->orderBy('id')->get() as $contact) {
            try {
                $this->sms->send($contact->phone, $message);
            } catch (SmsDeliveryFailed $exception) {
                report($exception);

                continue;
            }

            $texted[] = $contact;
        }

        return $texted;
    }
}
