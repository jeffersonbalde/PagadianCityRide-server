<?php

namespace App\Filament\Resources;

use App\DocumentStatus;
use App\DocumentType;
use App\DriverStatus;
use App\Filament\Resources\Concerns\ReadOnlyForAdmins;
use App\Filament\Resources\DriverApprovalResource\Pages\ListDriverApprovals;
use App\Filament\Resources\DriverApprovalResource\Pages\ViewDriverApproval;
use App\Models\DriverProfile;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The driver applications that wait for review, or that were sent back. An approved driver moves to the Drivers page.
 */
class DriverApprovalResource extends Resource
{
    use ReadOnlyForAdmins;

    protected static ?string $model = DriverProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Driver approvals';

    protected static ?string $modelLabel = 'driver application';

    protected static ?string $pluralModelLabel = 'driver approvals';

    protected static ?string $slug = 'driver-approvals';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $waiting = DriverProfile::query()->where('status', DriverStatus::Submitted)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [DriverStatus::Submitted, DriverStatus::Rejected])
            ->with(['user', 'documents']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Driver')->searchable()->weight('bold'),
                TextColumn::make('user.phone')->label('Phone')->searchable(),
                TextColumn::make('tricycle_number')->label('Tricycle'),
                TextColumn::make('plate_number')->label('Plate'),
                TextColumn::make('franchise_number')->label('Franchise'),
                TextColumn::make('documents_summary')
                    ->label('Documents')
                    ->state(fn (DriverProfile $record): string => $record->documents->count().' of '.count(DocumentType::cases())),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DriverStatus $state): string => $state === DriverStatus::Submitted ? 'Waiting' : 'Sent back')
                    ->color(fn (DriverStatus $state): string => $state === DriverStatus::Submitted ? 'warning' : 'danger'),
                TextColumn::make('submitted_at')->label('Sent')->since()->sortable(),
            ])
            ->defaultSort('submitted_at')
            ->recordUrl(fn (DriverProfile $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Nothing to review')
            ->emptyStateDescription('New driver applications show up here.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('user.name')->label('Driver'),
                TextEntry::make('user.phone')->label('Phone')->copyable(),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn (DriverStatus $state): string => $state === DriverStatus::Submitted ? 'Waiting for review' : 'Sent back')
                    ->color(fn (DriverStatus $state): string => $state === DriverStatus::Submitted ? 'warning' : 'danger'),
                TextEntry::make('tricycle_number')->label('Tricycle number'),
                TextEntry::make('plate_number')->label('Plate number'),
                TextEntry::make('franchise_number')->label('Franchise number'),
                TextEntry::make('submitted_at')->label('Sent')->dateTime(),
                TextEntry::make('rejection_reason')
                    ->label('Reason it was sent back')
                    ->visible(fn (DriverProfile $record): bool => $record->rejection_reason !== null)
                    ->columnSpan(2),
            ]),
            Section::make('Documents')->columns(4)->columnSpanFull()->schema(
                array_map(fn (DocumentType $type): ImageEntry => static::documentEntry($type), DocumentType::cases()),
            ),
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

        $url = fn (DriverProfile $record): ?string => ($document = $record->documents->firstWhere('type', $type)) === null
            ? null
            : route('admin.driver-documents.show', $document);

        return ImageEntry::make('document_'.$type->value)
            ->label($labels[$type->value])
            ->state($url)
            ->imageHeight(220)
            ->extraImgAttributes(['class' => 'rounded-lg object-cover'])
            ->url($url, shouldOpenInNewTab: true)
            ->placeholder('Not uploaded')
            ->hintIcon(fn (DriverProfile $record): ?Heroicon => $record->documents->firstWhere('type', $type)?->status === DocumentStatus::Rejected ? Heroicon::OutlinedExclamationTriangle : null)
            ->hintColor('danger');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverApprovals::route('/'),
            'view' => ViewDriverApproval::route('/{record}'),
        ];
    }
}
