<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Ride;
use App\RideStatus;
use App\Support\CsvDownload;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Going on')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', RideStatus::active())),
            'completed' => Tab::make('Completed')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RideStatus::Completed)),
            'cancelled' => Tab::make('Cancelled')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RideStatus::Cancelled)),
            'expired' => Tab::make('No driver')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RideStatus::Expired)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Download Excel (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    /**
     * The trips of the list as it is filtered now.
     */
    private function export(): StreamedResponse
    {
        $time = fn ($moment): ?string => $moment?->timezone(TripResource::TIMEZONE)->format('Y-m-d H:i');

        $rows = $this->getFilteredSortedTableQuery()
            ->lazy(500)
            ->map(fn (Ride $ride): array => [
                $ride->id,
                $time($ride->created_at),
                TripResource::statusLabel($ride->status),
                $ride->passenger?->name,
                PhoneNumber::local($ride->passenger?->phone),
                $ride->driver?->name,
                PhoneNumber::local($ride->driver?->phone),
                $ride->driver?->driverProfile?->tricycle_number,
                $ride->pickup_label ?? 'Pinned spot',
                $ride->destination_label,
                $ride->passengers_count,
                $ride->distance_meters === null ? null : round($ride->distance_meters / 1000, 2),
                $time($ride->accepted_at),
                $time($ride->completed_at),
                $time($ride->cancelled_at),
                $ride->cancel_reason,
                $ride->rating,
                $ride->review,
            ]);

        return CsvDownload::response('trips-'.now(TripResource::TIMEZONE)->format('Y-m-d').'.csv', [
            'Trip', 'Requested', 'Status', 'Passenger', 'Passenger phone', 'Driver', 'Driver phone', 'Tricycle', 'From', 'To',
            'People', 'Distance (km)', 'Accepted', 'Completed', 'Cancelled', 'Cancel reason', 'Rating', 'Review',
        ], $rows);
    }
}
