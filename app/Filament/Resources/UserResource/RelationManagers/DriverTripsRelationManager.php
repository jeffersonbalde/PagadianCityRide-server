<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TripResource;
use App\Models\Ride;
use App\Models\User;
use App\UserRole;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The trips this person drove.
 */
class DriverTripsRelationManager extends RelationManager
{
    protected static string $relationship = 'ridesAsDriver';

    protected static ?string $title = 'Trips driven';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->role === UserRole::Driver;
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
            ->emptyStateHeading('No trips driven');
    }
}
