<?php

namespace App\Filament\Resources\DriverApprovalResource\Pages;

use App\DriverStatus;
use App\Filament\Resources\DriverApprovalResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDriverApprovals extends ListRecords
{
    protected static string $resource = DriverApprovalResource::class;

    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DriverStatus::Submitted)),
            'sent-back' => Tab::make('Sent back')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DriverStatus::Rejected)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'waiting';
    }
}
