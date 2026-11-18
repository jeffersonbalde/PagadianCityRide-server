<?php

namespace App\Console\Commands;

use App\DriverStatus;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\Models\User;
use App\Support\Distance;
use Illuminate\Console\Command;

class DemoPassengers extends Command
{
    private const NAME_PREFIX = 'ZZ Temp Passenger';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:demo-passengers
        {count=12 : How many passengers wait}
        {--lat= : The latitude they wait around. Default: where an approved driver was last seen.}
        {--lng= : The longitude they wait around.}
        {--clear : Remove all demo passengers and their rides instead}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For testing the driver app: make passengers who wait for a tricycle around a point, or remove them again.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This is only for testing, not for the live app.');

            return self::FAILURE;
        }

        $demo = User::query()->where('name', 'like', self::NAME_PREFIX.'%');

        if ($this->option('clear')) {
            $ids = $demo->pluck('id');

            \DB::table('personal_access_tokens')->whereIn('tokenable_id', $ids)->delete();
            User::query()->whereIn('id', $ids)->delete();

            $this->info("Removed {$ids->count()} demo passengers and their rides.");

            return self::SUCCESS;
        }

        [$latitude, $longitude] = $this->center();
        $count = max(1, min(20, (int) $this->argument('count')));
        $start = $demo->count();

        for ($i = 1; $i <= $count; $i++) {
            $number = $start + $i;
            $passenger = User::create([
                'name' => self::NAME_PREFIX.' '.$number,
                'phone' => '+639990'.sprintf('%06d', $number),
                'role' => 'passenger',
            ]);

            // Spread around the point: the first are close, the last are about 1.5 km away.
            $angle = $i * 2.4;
            $meters = 120 * $i;
            $box = Distance::boundingBox($latitude, $longitude, (int) $meters);

            Ride::factory()
                ->pickupAt($latitude + ($box['maxLatitude'] - $latitude) * sin($angle), $longitude + ($box['maxLongitude'] - $longitude) * cos($angle))
                ->create(['passenger_id' => $passenger->id]);
        }

        $this->info("{$count} demo passengers wait around {$latitude}, {$longitude}. Remove them with: php artisan app:demo-passengers --clear");

        return self::SUCCESS;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function center(): array
    {
        if ($this->option('lat') !== null && $this->option('lng') !== null) {
            return [(float) $this->option('lat'), (float) $this->option('lng')];
        }

        $driver = DriverProfile::query()
            ->where('status', DriverStatus::Approved)
            ->whereNotNull('latitude')
            ->orderByDesc('located_at')
            ->first();

        return $driver !== null ? [$driver->latitude, $driver->longitude] : [7.8231, 123.4402];
    }
}
