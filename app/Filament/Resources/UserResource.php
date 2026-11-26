<?php

namespace App\Filament\Resources;

use App\DocumentType;
use App\DriverStatus;
use App\Filament\Resources\Concerns\ReadOnlyForAdmins;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\DriverTripsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\PassengerTripsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ReportsMadeRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ReportsReceivedRelationManager;
use App\Models\User;
use App\RideStatus;
use App\UserRole;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everybody who uses the app. An admin reads all about a person, and can correct, block or delete the account
 * through the actions on the page of that person.
 */
class UserResource extends Resource
{
    use ReadOnlyForAdmins;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'user';

    protected static ?string $slug = 'users';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('driverProfile')
            ->withCount(['ridesAsPassenger', 'ridesAsDriver', 'reportsReceived']);
    }

    public static function roleLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::Passenger => 'Passenger',
            UserRole::Driver => 'Driver',
            UserRole::Admin => 'Admin',
        };
    }

    public static function roleColor(UserRole $role): string
    {
        return match ($role) {
            UserRole::Passenger => 'info',
            UserRole::Driver => 'warning',
            UserRole::Admin => 'gray',
        };
    }

    public static function driverStatusLabel(DriverStatus $status): string
    {
        return match ($status) {
            DriverStatus::Draft => 'Not sent yet',
            DriverStatus::Submitted => 'Waiting for review',
            DriverStatus::Approved => 'Approved',
            DriverStatus::Rejected => 'Sent back',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->placeholder('No name yet')->weight('bold')->searchable()->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => static::roleLabel($state))
                    ->color(fn (UserRole $state): string => static::roleColor($state)),
                TextColumn::make('phone')->placeholder('—')->searchable()->copyable(),
                TextColumn::make('email')->placeholder('—')->searchable()->copyable(),
                TextColumn::make('driverProfile.status')
                    ->label('Driver application')
                    ->placeholder('—')
                    ->formatStateUsing(fn (DriverStatus $state): string => static::driverStatusLabel($state)),
                TextColumn::make('rides_as_passenger_count')->label('Trips taken')->sortable(),
                TextColumn::make('rides_as_driver_count')->label('Trips driven')->sortable(),
                TextColumn::make('reports_received_count')
                    ->label('Reported')
                    ->sortable()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('account')
                    ->label('Account')
                    ->badge()
                    ->state(fn (User $record): string => $record->isBlocked() ? 'Blocked' : 'Active')
                    ->color(fn (string $state): string => $state === 'Blocked' ? 'danger' : 'success'),
                TextColumn::make('created_at')->label('Joined')->dateTime('M j, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    UserRole::Passenger->value => 'Passenger',
                    UserRole::Driver->value => 'Driver',
                    UserRole::Admin->value => 'Admin',
                ]),
                TernaryFilter::make('blocked')
                    ->label('Blocked')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('blocked_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('blocked_at'),
                    ),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (User $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No users yet');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Blocked')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (User $record): bool => $record->isBlocked())
                ->schema([
                    TextEntry::make('blocked_reason')->label('Reason')->color('danger'),
                    TextEntry::make('blocked_at')->label('Since')->dateTime('M j, Y g:i A'),
                    TextEntry::make('blocker.name')->label('Blocked by')->placeholder('—'),
                ]),
            Section::make('Account')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->placeholder('No name yet'),
                TextEntry::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => static::roleLabel($state))
                    ->color(fn (UserRole $state): string => static::roleColor($state)),
                TextEntry::make('created_at')->label('Joined')->dateTime('M j, Y g:i A'),
                TextEntry::make('email')
                    ->placeholder('—')
                    ->copyable()
                    ->helperText(fn (User $record): string => $record->email_verified_at === null ? 'Not verified' : 'Verified'),
                TextEntry::make('phone')
                    ->placeholder('—')
                    ->copyable()
                    ->helperText(fn (User $record): string => $record->phone_verified_at === null ? 'Not verified' : 'Verified'),
                TextEntry::make('emergency_contacts_count')
                    ->label('Emergency contacts')
                    ->state(fn (User $record): int => $record->emergencyContacts()->count()),
            ]),
            Section::make('Summary')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('completed_as_passenger')
                    ->label('Completed trips taken')
                    ->state(fn (User $record): int => $record->ridesAsPassenger()->where('status', RideStatus::Completed)->count()),
                TextEntry::make('cancelled_as_passenger')
                    ->label('Cancelled trips')
                    ->state(fn (User $record): int => $record->ridesAsPassenger()->where('status', RideStatus::Cancelled)->count()),
                TextEntry::make('completed_as_driver')
                    ->label('Completed trips driven')
                    ->state(fn (User $record): int => $record->ridesAsDriver()->where('status', RideStatus::Completed)->count()),
                TextEntry::make('rating_as_driver')
                    ->label('Rating as driver')
                    ->state(function (User $record): string {
                        $average = $record->ridesAsDriver()->avg('rating');

                        return $average === null ? 'No rating yet' : number_format((float) $average, 1).' ★';
                    }),
            ]),
            Section::make('Tricycle')
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn (User $record): bool => $record->driverProfile !== null)
                ->schema([
                    TextEntry::make('driverProfile.status')
                        ->label('Application')
                        ->badge()
                        ->formatStateUsing(fn (DriverStatus $state): string => static::driverStatusLabel($state))
                        ->color(fn (DriverStatus $state): string => match ($state) {
                            DriverStatus::Approved => 'success',
                            DriverStatus::Rejected => 'danger',
                            default => 'warning',
                        }),
                    TextEntry::make('driverProfile.tricycle_number')->label('Tricycle number'),
                    TextEntry::make('driverProfile.plate_number')->label('Plate number'),
                    TextEntry::make('driverProfile.franchise_number')->label('Franchise number'),
                    TextEntry::make('driverProfile.reviewed_at')->label('Reviewed')->dateTime('M j, Y g:i A')->placeholder('—'),
                    TextEntry::make('driverProfile.located_at')->label('Last location')->since()->placeholder('—'),
                ]),
            Section::make('Documents')
                ->columns(4)
                ->columnSpanFull()
                ->visible(fn (User $record): bool => (bool) $record->driverProfile?->documents()->exists())
                ->schema(array_map(fn (DocumentType $type): ImageEntry => static::documentEntry($type), DocumentType::cases())),
        ]);
    }

    private static function documentEntry(DocumentType $type): ImageEntry
    {
        $labels = [
            DocumentType::Photo->value => 'Driver photo',
            DocumentType::License->value => 'Driver license',
            DocumentType::PlatePapers->value => 'Plate and papers',
            DocumentType::Franchise->value => 'Franchise',
        ];

        $url = fn (User $record): ?string => ($document = $record->driverProfile?->documents->firstWhere('type', $type)) === null
            ? null
            : route('admin.driver-documents.show', $document);

        return ImageEntry::make('document_'.$type->value)
            ->label($labels[$type->value])
            ->state($url)
            ->imageHeight(220)
            ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
            ->url($url, shouldOpenInNewTab: true)
            ->placeholder('Not uploaded');
    }

    public static function getRelations(): array
    {
        return [
            PassengerTripsRelationManager::class,
            DriverTripsRelationManager::class,
            ReportsReceivedRelationManager::class,
            ReportsMadeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }
}
