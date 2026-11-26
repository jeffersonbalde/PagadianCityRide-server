<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TripResource;
use App\Models\Ride;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The trips this person asked for.
 */
class PassengerTripsRelationManager extends RelationManager
{
    protected static string $relationship = 'ridesAsPassenger';

    protected static ?string $title = 'Trips taken';

    /**
     * The admin panel is for admins only, so every admin may look.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['passenger', 'driver.driverProfile']))
            ->columns(TripResource::columns())
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Ride $record): string => TripResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No trips taken');
    }
}
