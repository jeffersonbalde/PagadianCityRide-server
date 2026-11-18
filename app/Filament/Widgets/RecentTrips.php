<?php

namespace App\Filament\Widgets;

use App\Models\Ride;
use App\RideStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The last trips, newest first.
 */
class RecentTrips extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent trips';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Ride::query()->with(['passenger', 'driver.driverProfile'])->latest()->latest('id'))
            ->columns([
                TextColumn::make('created_at')->label('Time')->since(),
                TextColumn::make('pickup_label')->label('From')->placeholder('Pinned spot')->limit(30),
                TextColumn::make('destination_label')->label('To')->placeholder('Pinned spot')->limit(30),
                TextColumn::make('passenger.name')->label('Passenger'),
                TextColumn::make('driver.name')
                    ->label('Driver')
                    ->placeholder('—')
                    ->description(fn (Ride $record): ?string => $record->driver?->driverProfile?->tricycle_number),
                TextColumn::make('wait')
                    ->label('Wait')
                    ->state(fn (Ride $record): string => $record->accepted_at === null ? '—' : round($record->created_at->diffInSeconds($record->accepted_at, true) / 60, 1).' min'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RideStatus $state): string => match ($state) {
                        RideStatus::Requested => 'Waiting',
                        RideStatus::Accepted => 'On the way',
                        RideStatus::Arrived => 'Arrived',
                        RideStatus::InProgress => 'On a trip',
                        RideStatus::Completed => 'Completed',
                        RideStatus::Cancelled => 'Cancelled',
                        RideStatus::Expired => 'No driver',
                    })
                    ->color(fn (RideStatus $state): string => match ($state) {
                        RideStatus::Completed => 'success',
                        RideStatus::Cancelled, RideStatus::Expired => 'danger',
                        default => 'warning',
                    }),
            ])
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->poll('30s')
            ->emptyStateHeading('No trips yet');
    }
}
