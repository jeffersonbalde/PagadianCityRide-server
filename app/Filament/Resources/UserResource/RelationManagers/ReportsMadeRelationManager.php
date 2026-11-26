<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\RideReportResource;
use App\Models\RideReport;
use App\ReportCategory;
use App\ReportStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What this person reported about others.
 */
class ReportsMadeRelationManager extends RelationManager
{
    protected static string $relationship = 'reportsMade';

    protected static ?string $title = 'Reports they sent';

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('reportedUser'))
            ->columns([
                TextColumn::make('category')->label('Report')->formatStateUsing(fn (ReportCategory $state): string => RideReportResource::categoryLabel($state)),
                TextColumn::make('reportedUser.name')->label('About')->placeholder('—'),
                TextColumn::make('details')->placeholder('No details were written.')->limit(60),
                TextColumn::make('ride_id')->label('Trip')->prefix('#'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => RideReportResource::statusLabel($state))
                    ->color(fn (ReportStatus $state): string => RideReportResource::statusColor($state)),
                TextColumn::make('created_at')->label('Sent')->dateTime('M j, Y g:i A')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (RideReport $record): string => RideReportResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('This person sent no reports');
    }
}
