<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ReadOnlyForAdmins;
use App\Filament\Resources\RideReportResource\Pages\ListRideReports;
use App\Filament\Resources\RideReportResource\Pages\ViewRideReport;
use App\Models\RideReport;
use App\ReportCategory;
use App\ReportStatus;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * What passengers and drivers reported about a ride. An admin reads it and closes it with a note.
 */
class RideReportResource extends Resource
{
    use ReadOnlyForAdmins;

    protected static ?string $model = RideReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $modelLabel = 'report';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $open = RideReport::query()->where('status', ReportStatus::Open)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['ride', 'reporter', 'reportedUser']);
    }

    /**
     * What the person chose from the list in the app.
     */
    public static function categoryLabel(ReportCategory $category): string
    {
        return match ($category) {
            ReportCategory::Safety => 'Safety',
            ReportCategory::Behavior => 'Behavior',
            ReportCategory::Route => 'Route',
            ReportCategory::NoShow => 'No show',
            ReportCategory::Other => 'Other',
        };
    }

    public static function statusLabel(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Open => 'Open',
            ReportStatus::Resolved => 'Resolved',
            ReportStatus::Dismissed => 'Dismissed',
        };
    }

    public static function statusColor(ReportStatus $status): string
    {
        return match ($status) {
            ReportStatus::Open => 'danger',
            ReportStatus::Resolved => 'success',
            ReportStatus::Dismissed => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('Report')
                    ->weight('bold')
                    ->formatStateUsing(fn (ReportCategory $state): string => static::categoryLabel($state)),
                TextColumn::make('reporter.name')->label('From')->description(fn (RideReport $record): string => $record->reporter->role->value)->searchable(),
                TextColumn::make('reportedUser.name')->label('About')->description(fn (RideReport $record): string => $record->reportedUser->role->value)->searchable(),
                TextColumn::make('ride_id')->label('Trip')->prefix('#'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => static::statusLabel($state))
                    ->color(fn (ReportStatus $state): string => static::statusColor($state)),
                TextColumn::make('created_at')->label('Sent')->since()->sortable(),
            ])
            ->defaultSort('created_at')
            ->recordUrl(fn (RideReport $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No reports')
            ->emptyStateDescription('Reports from passengers and drivers show up here.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report')->columns(3)->schema([
                TextEntry::make('category')->label('What happened')->formatStateUsing(fn (ReportCategory $state): string => static::categoryLabel($state)),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => static::statusLabel($state))
                    ->color(fn (ReportStatus $state): string => static::statusColor($state)),
                TextEntry::make('created_at')->label('Sent')->dateTime(),
                TextEntry::make('reporter.name')->label('From')->suffix(fn (RideReport $record): string => ' ('.$record->reporter->role->value.')'),
                TextEntry::make('reporter.phone')->label('Their phone')->copyable(),
                TextEntry::make('details')->label('Details')->placeholder('No details were written.')->columnSpanFull(),
            ]),
            Section::make('The person reported')->columns(3)->schema([
                TextEntry::make('reportedUser.name')->label('Name')->suffix(fn (RideReport $record): string => ' ('.$record->reportedUser->role->value.')'),
                TextEntry::make('reportedUser.phone')->label('Phone')->copyable(),
            ]),
            Section::make('The trip')->columns(3)->schema([
                TextEntry::make('ride.pickup_label')->label('From')->placeholder('Pinned spot'),
                TextEntry::make('ride.destination_label')->label('To')->placeholder('Pinned spot'),
                TextEntry::make('ride.created_at')->label('Requested')->dateTime(),
            ]),
            Section::make('How it was handled')
                ->columns(3)
                ->visible(fn (RideReport $record): bool => ! $record->status->isOpen())
                ->schema([
                    TextEntry::make('admin_note')->label('Note')->columnSpan(2),
                    TextEntry::make('handled_at')->label('Closed')->dateTime(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRideReports::route('/'),
            'view' => ViewRideReport::route('/{record}'),
        ];
    }
}
