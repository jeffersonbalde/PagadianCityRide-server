<?php

namespace App\Console\Commands;

use App\Actions\Place\ImportOpenStreetMapPlaces;
use Illuminate\Console\Command;
use Throwable;

class ImportPlaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bring the places of Pagadian City (barangays, malls, hospitals, schools and more) from OpenStreetMap.';

    /**
     * Execute the console command.
     */
    public function handle(ImportOpenStreetMapPlaces $import): int
    {
        try {
            $result = $import->handle();
        } catch (Throwable $exception) {
            $this->error('OpenStreetMap did not answer: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$result['created']} place(s) added, {$result['updated']} updated, {$result['skipped']} skipped.");
        $this->line('Places: © OpenStreetMap contributors (ODbL).');

        return self::SUCCESS;
    }
}
