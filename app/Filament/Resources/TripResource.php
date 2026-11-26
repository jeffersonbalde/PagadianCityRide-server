<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\ReadOnlyForAdmins;
use App\Filament\Resources\TripResource\Pages\ListTrips;
use App\Filament\Resources\TripResource\Pages\ViewTrip;
use App\Models\Ride;
use App\Models\RideDriverCancellation;
use App\Models\RideMessage;
use App\Models\RideReport;
use App\ReportCategory;
use App\ReportStatus;
use App\RideStatus;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Every trip that was asked for, with all that happened on it. An admin only reads it.
 */
class TripResource extends Resource
{
    use ReadOnlyForAdmins;

    protected static ?string $model = Ride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Trips';

    protected static ?string $modelLabel = 'trip';

    protected static ?string $slug = 'trips';

    protected static ?int $navigationSort = 5;

    /**
     * The days in the filter are the days in the Philippines.
     */
    public const TIMEZONE = 'Asia/Manila';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['passenger', 'driver.driverProfile']);
    }

    /**
     * The rules of a ride in the app let only its passenger and its driver look at it. Only admins reach the panel,
     * and they may read every trip.
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Response::allow();
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Response::allow();
    }

    public static function statusLabel(RideStatus $status): string
    {
        return match ($status) {
            RideStatus::Requested => 'Waiting',
            RideStatus::Accepted => 'On the way',
            RideStatus::Arrived => 'Arrived',
            RideStatus::InProgress => 'On a trip',
            RideStatus::Completed => 'Completed',
            RideStatus::Cancelled => 'Cancelled',
            RideStatus::Expired => 'No driver',
        };
    }

    public static function statusColor(RideStatus $status): string
    {
        return match ($status) {
            RideStatus::Completed => 'success',
            RideStatus::Cancelled, RideStatus::Expired => 'danger',
            default => 'warning',
        };
    }

    /**
     * The columns of a list of trips. The pages of one person use them too.
     *
     * @return list<TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('id')->label('Trip')->prefix('#')->sortable()->searchable(),
            TextColumn::make('created_at')->label('Requested')->dateTime('M j, Y g:i A')->sortable(),
            TextColumn::make('pickup_label')->label('From')->placeholder('Pinned spot')->limit(30)->searchable(),
            TextColumn::make('destination_label')->label('To')->limit(30)->searchable(),
            TextColumn::make('passenger.name')->label('Passenger')->placeholder('—')->searchable(),
            TextColumn::make('driver.name')
                ->label('Driver')
                ->placeholder('—')
                ->description(fn (Ride $record): ?string => $record->driver?->driverProfile?->tricycle_number)
                ->searchable(),
            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (RideStatus $state): string => static::statusLabel($state))
                ->color(fn (RideStatus $state): string => static::statusColor($state)),
            TextColumn::make('rating')->placeholder('—')->suffix(' ★')->sortable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->filters([
                Filter::make('requested')
                    ->schema([
                        DatePicker::make('from')->label('Requested from'),
                        DatePicker::make('until')->label('Requested until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $inner, string $date): Builder => $inner
                            ->where('created_at', '>=', Carbon::parse($date, self::TIMEZONE)->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn (Builder $inner, string $date): Builder => $inner
                            ->where('created_at', '<=', Carbon::parse($date, self::TIMEZONE)->endOfDay()->utc()))),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Ride $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No trips')
            ->emptyStateDescription('Trips show up here as soon as passengers ask for a ride.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trip')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RideStatus $state): string => static::statusLabel($state))
                    ->color(fn (RideStatus $state): string => static::statusColor($state)),
                TextEntry::make('passengers_count')->label('People'),
                TextEntry::make('note')->label('Note for the driver')->placeholder('No note.'),
                TextEntry::make('distance_meters')
                    ->label('Distance')
                    ->placeholder('—')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1000, 1).' km'),
                TextEntry::make('duration_seconds')
                    ->label('Expected time')
                    ->placeholder('—')
                    ->formatStateUsing(fn (int $state): string => max(1, (int) round($state / 60)).' min'),
                TextEntry::make('cancel_reason')->label('Why it was cancelled')->placeholder('—'),
            ]),
            Section::make('People')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('passenger.name')
                    ->label('Passenger')
                    ->placeholder('No name')
                    ->url(fn (Ride $record): string => UserResource::getUrl('view', ['record' => $record->passenger_id])),
                TextEntry::make('passenger.phone')->label('Passenger phone')->placeholder('—')->copyable(),
                TextEntry::make('passenger.email')->label('Passenger email')->placeholder('—')->copyable(),
                TextEntry::make('driver.name')
                    ->label('Driver')
                    ->placeholder('No driver')
                    ->url(fn (Ride $record): ?string => $record->driver_id === null ? null : UserResource::getUrl('view', ['record' => $record->driver_id])),
                TextEntry::make('driver.phone')->label('Driver phone')->placeholder('—')->copyable(),
                TextEntry::make('driver.driverProfile.tricycle_number')->label('Tricycle')->placeholder('—'),
            ]),
            Section::make('Places')->columns(2)->columnSpanFull()->description('Click a place to open it on the map.')->schema([
                TextEntry::make('pickup_label')
                    ->label('Pick-up')
                    ->state(fn (Ride $record): string => $record->pickup_label ?? 'Pinned spot')
                    ->url(fn (Ride $record): string => static::mapUrl($record->pickup_latitude, $record->pickup_longitude), shouldOpenInNewTab: true),
                TextEntry::make('destination_label')
                    ->label('Destination')
                    ->url(fn (Ride $record): string => static::mapUrl($record->destination_latitude, $record->destination_longitude), shouldOpenInNewTab: true),
            ]),
            Section::make('Times')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('created_at')->label('Requested')->dateTime('M j, Y g:i A'),
                TextEntry::make('accepted_at')->label('Accepted')->dateTime('M j, Y g:i A')->placeholder('—'),
                TextEntry::make('arrived_at')->label('Driver arrived')->dateTime('M j, Y g:i A')->placeholder('—'),
                TextEntry::make('started_at')->label('Started')->dateTime('M j, Y g:i A')->placeholder('—'),
                TextEntry::make('completed_at')->label('Completed')->dateTime('M j, Y g:i A')->placeholder('—'),
                TextEntry::make('cancelled_at')->label('Cancelled')->dateTime('M j, Y g:i A')->placeholder('—'),
            ]),
            Section::make('Rating')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (Ride $record): bool => $record->rating !== null)
                ->schema([
                    TextEntry::make('rating')->suffix(' of 5 ★'),
                    TextEntry::make('review')->label('Review')->placeholder('No words were written.'),
                    TextEntry::make('rated_at')->label('Rated')->dateTime('M j, Y g:i A'),
                ]),
            Section::make('Drivers who gave this pick-up up')
                ->columnSpanFull()
                ->visible(fn (Ride $record): bool => $record->driverCancellations()->exists())
                ->schema([
                    RepeatableEntry::make('driverCancellations')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('driver_id')
                            ->label('Driver')
                            ->state(fn (RideDriverCancellation $record): string => $record->driver?->name ?? '—'),
                        TextEntry::make('reason')->state(fn (RideDriverCancellation $record): string => $record->reason->words($record->note)),
                        TextEntry::make('created_at')->label('When')->dateTime('M j, Y g:i A'),
                    ]),
                ]),
            Section::make('Reports')
                ->columnSpanFull()
                ->visible(fn (Ride $record): bool => $record->reports()->exists())
                ->schema([
                    RepeatableEntry::make('reports')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('category')
                            ->label('What happened')
                            ->formatStateUsing(fn (ReportCategory $state): string => RideReportResource::categoryLabel($state))
                            ->url(fn (RideReport $record): string => RideReportResource::getUrl('view', ['record' => $record])),
                        TextEntry::make('reporter.name')->label('From')->placeholder('—'),
                        TextEntry::make('details')->placeholder('No details were written.'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (ReportStatus $state): string => RideReportResource::statusLabel($state))
                            ->color(fn (ReportStatus $state): string => RideReportResource::statusColor($state)),
                    ]),
                ]),
            // The chat is private. It is shown only when somebody on the trip reported it.
            Section::make('Chat of the reported trip')
                ->columnSpanFull()
                ->visible(fn (Ride $record): bool => $record->reports()->exists() && $record->messages()->exists())
                ->schema([
                    RepeatableEntry::make('messages')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('sender.name')->label('From')->placeholder('—'),
                        TextEntry::make('body')->label('Message')->placeholder('—')->columnSpan(2),
                        TextEntry::make('created_at')->label('Sent')->dateTime('M j, g:i A'),
                        ImageEntry::make('image_path')
                            ->label('Photo')
                            ->state(fn (RideMessage $record): ?string => $record->imageUrl() === null ? null : url($record->imageUrl()))
                            ->visible(fn (RideMessage $record): bool => $record->image_path !== null)
                            ->imageHeight(180)
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    private static function mapUrl(float $latitude, float $longitude): string
    {
        return 'https://www.google.com/maps?q='.$latitude.','.$longitude;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrips::route('/'),
            'view' => ViewTrip::route('/{record}'),
        ];
    }
}
