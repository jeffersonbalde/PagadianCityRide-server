<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneAlerts extends Command
{
    /**
     * An alert that was read is deleted after this many days.
     */
    public const READ_DAYS = 30;

    /**
     * An alert that was never read is deleted after this many days, so the list cannot grow without end.
     */
    public const UNREAD_DAYS = 90;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the old alerts: the ones that were read after 30 days, and the rest after 90 days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $read = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(self::READ_DAYS))
            ->delete();

        $unread = DatabaseNotification::query()
            ->where('created_at', '<', now()->subDays(self::UNREAD_DAYS))
            ->delete();

        $this->info('Deleted '.($read + $unread).' old alerts.');

        return self::SUCCESS;
    }
}
