<?php

namespace App\Console\Commands;

use App\Events\RideStatusChanged;
use App\Models\Ride;
use App\RideStatus;
use Illuminate\Console\Command;

class ExpireStaleRides extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rides:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire the ride requests that no driver picked up in time.';

    /**
     * Execute the console command. Each request is expired on its own and only
     * while it still waits, so a driver who accepts at the same moment wins.
     */
    public function handle(): int
    {
        $expired = 0;

        Ride::query()
            ->where('status', RideStatus::Requested)
            ->where('created_at', '<=', now()->subMinutes(config('rides.request_expires_after_minutes')))
            ->each(function (Ride $ride) use (&$expired): void {
                $changed = Ride::query()
                    ->whereKey($ride->id)
                    ->where('status', RideStatus::Requested)
                    ->update(['status' => RideStatus::Expired]);

                if ($changed === 1) {
                    $expired++;

                    RideStatusChanged::dispatch($ride->refresh(), RideStatus::Requested);
                }
            });

        $this->info("{$expired} ride request(s) expired.");

        return self::SUCCESS;
    }
}
