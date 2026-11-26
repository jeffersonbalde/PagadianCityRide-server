<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\TripResource;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\CsvDownload;
use App\Support\PhoneNumber;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'passengers' => Tab::make('Passengers')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('role', UserRole::Passenger)),
            'drivers' => Tab::make('Drivers')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('role', UserRole::Driver)),
            'blocked' => Tab::make('Blocked')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('blocked_at')),
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
     * The people of the list as it is filtered now.
     */
    private function export(): StreamedResponse
    {
        $rows = $this->getFilteredSortedTableQuery()
            ->lazy(500)
            ->map(fn (User $user): array => [
                $user->id,
                $user->name,
                UserResource::roleLabel($user->role),
                PhoneNumber::local($user->phone),
                $user->email,
                $user->driverProfile === null ? null : UserResource::driverStatusLabel($user->driverProfile->status),
                $user->driverProfile?->tricycle_number,
                $user->driverProfile?->plate_number,
                $user->rides_as_passenger_count,
                $user->rides_as_driver_count,
                $user->reports_received_count,
                $user->isBlocked() ? 'Blocked' : 'Active',
                $user->blocked_reason,
                $user->created_at?->timezone(TripResource::TIMEZONE)->format('Y-m-d H:i'),
            ]);

        return CsvDownload::response('users-'.now(TripResource::TIMEZONE)->format('Y-m-d').'.csv', [
            'ID', 'Name', 'Role', 'Mobile', 'Email', 'Driver application', 'Tricycle', 'Plate', 'Trips taken', 'Trips driven',
            'Times reported', 'Account', 'Reason blocked', 'Joined',
        ], $rows);
    }
}
