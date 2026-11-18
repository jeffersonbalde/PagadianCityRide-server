<?php

namespace App\Console\Commands;

use App\Exceptions\SmsDeliveryFailed;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

class SendTestSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {phone : The mobile number to text, for example 0917 123 4567}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send one test text through the SMS provider that is set in SMS_DRIVER, to check that it reaches a phone.';

    /**
     * Execute the console command.
     */
    public function handle(SmsGateway $sms): int
    {
        $phone = PhoneNumber::normalize((string) $this->argument('phone'));

        if ($phone === null) {
            $this->error('That is not a Philippine mobile number. Try 0917 123 4567.');

            return self::FAILURE;
        }

        $driver = config('services.sms.driver');

        try {
            $sms->send($phone, config('app.name').': this is a test text. If you can read this, texts work.');
        } catch (SmsDeliveryFailed $exception) {
            $this->error("The \"{$driver}\" SMS provider did not take the text: ".($exception->getPrevious()?->getMessage() ?? $exception->getMessage()));

            return self::FAILURE;
        }

        $this->info("Handed to the \"{$driver}\" SMS provider for {$phone}.");

        if ($driver === 'log') {
            $this->warn('SMS_DRIVER is "log": the text was only written to storage/logs, no phone gets it.');
        } else {
            $this->line('Check the phone. A provider that takes a text has not yet delivered it.');
        }

        return self::SUCCESS;
    }
}
