<?php

namespace App\Filament\Resources;

use App\DriverStatus;
use App\Filament\Resources\Concerns\ReadOnlyForAdmins;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Models\DriverProfile;
use App\Models\Ride;
use App\RideStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The approved drivers: who is online now, how they are rated and how many trips they finished.
 */
class DriverResource extends Resource
{
    use ReadOnlyForAdmins;

    protected static ?string $model = DriverProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Drivers';

    protected static ?string $modelLabel = 'driver';

    protected static ?string $slug = 'drivers';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', DriverStatus::Approved)
            ->with('user')
            ->addSelect([
                'rating_average' => Ride::query()
                    ->selectRaw('avg(rating)')
                    ->whereColumn('driver_id', 'driver_profiles.user_id'),
                'completed_trips_count' => Ride::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('driver_id', 'driver_profiles.user_id')
                    ->where('status', RideStatus::Completed),
            ]);
    }

    private static function isOnline(DriverProfile $record): bool
    {
        return $record->is_online
            && $record->located_at?->greaterThanOrEqualTo(now()->subMinutes(config('rides.driver_location_stale_after_minutes'))) === true;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Driver')->searchable()->weight('bold'),
                TextColumn::make('user.phone')->label('Phone')->searchable(),
                TextColumn::make('tricycle_number')->label('Tricycle')->searchable(),
                TextColumn::make('plate_number')->label('Plate')->searchable(),
                IconColumn::make('online')
                    ->label('Online')
                    ->boolean()
                    ->state(fn (DriverProfile $record): bool => static::isOnline($record)),
                TextColumn::make('rating_average')
                    ->label('Rating')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state === null ? 'No rating yet' : number_format((float) $state, 1).' ★'),
                TextColumn::make('completed_trips_count')->label('Trips')->sortable(),
                TextColumn::make('reviewed_at')->label('Approved')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('online')
                    ->label('Online now')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->where('is_online', true)
                            ->where('located_at', '>=', now()->subMinutes(config('rides.driver_location_stale_after_minutes'))),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $inner): Builder => $inner
                                ->where('is_online', false)
                                ->orWhereNull('located_at')
                                ->orWhere('located_at', '<', now()->subMinutes(config('rides.driver_location_stale_after_minutes'))),
                        ),
                    ),
            ])
            ->defaultSort('reviewed_at', 'desc')
            ->poll('30s')
            ->emptyStateHeading('No approved drivers yet')
            ->emptyStateDescription('Approve a driver application and the driver shows up here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
        ];
    }
}
